// UI check: screenshots (desktop + phone), horizontal-overflow detection and axe-core WCAG scan.
// Usage: node check.mjs <baseUrl> <email> <totpSecret> <password> <outDir>
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import { createHmac } from 'node:crypto';
import { mkdirSync, writeFileSync } from 'node:fs';

const [base, email, secret, password, out] = process.argv.slice(2);
mkdirSync(out, { recursive: true });

function base32(s) {
  const a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; let bits = ''; const bytes = [];
  for (const c of s.replace(/=+$/, '')) bits += a.indexOf(c).toString(2).padStart(5, '0');
  for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2));
  return Buffer.from(bytes);
}
function totp(sec, offset = 0) {
  const step = Math.floor(Date.now() / 30000) + offset; const msg = Buffer.alloc(8);
  msg.writeBigUInt64BE(BigInt(step));
  const h = createHmac('sha1', base32(sec)).update(msg).digest(); const o = h[h.length - 1] & 15;
  return String(((h.readUInt32BE(o) & 0x7fffffff) % 1e6)).padStart(6, '0');
}

const pages = [
  ['home', '/'], ['design-system', '/design-system'], ['staff-login', '/staff/login'],
  ['admin-dashboard', '/admin'], ['admin-team', '/admin/team'], ['admin-settings', '/admin/settings'], ['admin-audit', '/admin/audit'], ['admin-providers', '/admin/providers'],
];
const viewports = { desktop: { width: 1440, height: 900 }, phone: { width: 390, height: 844, isMobile: true, hasTouch: true, deviceScaleFactor: 2 } };
const report = [];
const browser = await chromium.launch();
for (const [vpName, vp] of Object.entries(viewports)) {
  const ctx = await browser.newContext({ viewport: { width: vp.width, height: vp.height }, isMobile: vp.isMobile, hasTouch: vp.hasTouch, deviceScaleFactor: vp.deviceScaleFactor ?? 1 });
  const page = await ctx.newPage();
  const consoleErrors = [];
  page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
  // Sign in through the real staff flow (password + TOTP). Each viewport uses a later TOTP step to avoid replay rejection.
  await page.goto(base + '/staff/login');
  await page.fill('#f-email', email); await page.fill('#f-password', password);
  await Promise.all([page.waitForURL('**/staff/mfa'), page.click('button:has-text("Continue")')]);
  await page.fill('#f-code', totp(secret, vpName === 'desktop' ? 0 : 1));
  await Promise.all([page.waitForURL('**/admin'), page.click('button:has-text("Verify")')]);
  for (const [name, path] of pages) {
    if (name === 'staff-login') continue; // already exercised; re-visiting while signed in is not meaningful
    const res = await page.goto(base + path, { waitUntil: 'networkidle' });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
    await page.screenshot({ path: `${out}/${vpName}-${name}.png`, fullPage: true });
    report.push({ viewport: vpName, page: path, status: res.status(), horizontalOverflowPx: overflow,
      axeViolations: axe.violations.map(v => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, sample: v.nodes[0]?.target })) });
  }
  if (vpName === 'phone') {
    await page.goto(base + '/admin');
    await page.click('button[aria-controls="admin-nav"]');
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${out}/phone-admin-nav-open.png` });
    await page.goto(base + '/');
    await page.click('button[aria-controls="mobile-nav"]');
    await page.waitForTimeout(200);
    await page.screenshot({ path: `${out}/phone-home-nav-open.png` });
  }
  report.push({ viewport: vpName, consoleErrors });
  await ctx.close();
}
// Logged-out login page.
const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
const p = await ctx.newPage();
await p.goto(base + '/staff/login');
const axe = await new AxeBuilder({ page: p }).withTags(['wcag2a', 'wcag2aa', 'wcag22aa']).analyze();
await p.screenshot({ path: `${out}/phone-staff-login.png`, fullPage: true });
report.push({ viewport: 'phone', page: '/staff/login', axeViolations: axe.violations.map(v => ({ id: v.id, nodes: v.nodes.length })) });
await browser.close();
writeFileSync(`${out}/report.json`, JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 1));
