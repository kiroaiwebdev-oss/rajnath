// Calling UI check: customer call page (join attempt before the window), bookings list, staff Calling settings.
// Usage: node check-call.mjs <baseUrl> <laravelLogPath> <outDir> <opsFixturesJson> <ownerEmail> <ownerSecret> <ownerPassword>
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import { createHmac } from 'node:crypto';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';

const [base, logPath, out, opsJson, ownerEmail, ownerSecret, ownerPassword] = process.argv.slice(2);
const ops = JSON.parse(opsJson);
mkdirSync(out, { recursive: true });
const latestCode = () => { const m = [...readFileSync(logPath, 'utf8').matchAll(/Subject: (\d{6}) is your/g)]; return m.length ? m[m.length - 1][1] : null; };
function base32(s) { const a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; let bits = ''; const bytes = []; for (const c of s.replace(/=+$/, '')) bits += a.indexOf(c).toString(2).padStart(5, '0'); for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2)); return Buffer.from(bytes); }
function totp(sec) { const step = Math.floor(Date.now() / 30000); const msg = Buffer.alloc(8); msg.writeBigUInt64BE(BigInt(step)); const h = createHmac('sha1', base32(sec)).update(msg).digest(); const o = h[h.length - 1] & 15; return String(((h.readUInt32BE(o) & 0x7fffffff) % 1e6)).padStart(6, '0'); }
const report = [];
const visit = async (page, vp, name, path) => {
  const res = await page.goto(base + path, { waitUntil: 'networkidle' });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  await page.screenshot({ path: `${out}/${vp}-${name}.png`, fullPage: true });
  report.push({ viewport: vp, page: name, status: res.status(), horizontalOverflowPx: overflow, axeViolations: axe.violations.map(v => ({ id: v.id, nodes: v.nodes.length, sample: v.nodes[0]?.target })) });
};
const browser = await chromium.launch();
const vps = { desktop: { width: 1440, height: 900 }, phone: { width: 390, height: 844, isMobile: true, hasTouch: true } };
let first = true;
for (const [vp, size] of Object.entries(vps)) {
  const ctx = await browser.newContext({ viewport: { width: size.width, height: size.height }, isMobile: size.isMobile, hasTouch: size.hasTouch, permissions: ['microphone', 'camera'] });
  const page = await ctx.newPage();
  const errors = [];
  page.on('console', m => { if (m.type() === 'error' && !/422|Unprocessable/.test(m.text())) errors.push(m.text()); });
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await page.goto(base + '/account/sign-in', { waitUntil: 'networkidle' });
  await page.fill('#f-identifier', ops.email);
  await Promise.all([page.waitForURL('**/account/verify'), page.click('button:has-text("Send code")')]);
  await page.fill('#f-code', latestCode());
  if (await page.locator('input[name="terms"]').count()) await page.check('input[name="terms"]');
  await Promise.all([page.waitForURL('**/account**'), page.click('form button[type="submit"], form button.btn-primary')]);
  await visit(page, vp, 'acct-bookings', '/account/bookings');
  await visit(page, vp, 'call-page', `/call/${ops.booking}`);
  await page.click('button:has-text("Join call")');
  await page.waitForSelector('[role=alert]:visible', { timeout: 8000 });
  report.push({ viewport: vp, check: 'join-before-window', message: (await page.textContent('[role=alert]'))?.trim().slice(0, 90) });
  await page.screenshot({ path: `${out}/${vp}-call-early.png`, fullPage: true });
  await ctx.clearCookies();
  if (!first) await new Promise(r => setTimeout(r, 30000 - (Date.now() % 30000) + 500));
  first = false;
  await page.goto(base + '/staff/login');
  await page.fill('#f-email', ownerEmail); await page.fill('#f-password', ownerPassword);
  await Promise.all([page.waitForURL('**/staff/mfa'), page.click('button:has-text("Continue")')]);
  await page.fill('#f-code', totp(ownerSecret));
  await Promise.all([page.waitForURL('**/admin'), page.click('button:has-text("Verify")')]);
  await visit(page, vp, 'admin-calling', '/admin/calling');
  await visit(page, vp, 'admin-bookings', '/admin/bookings');
  report.push({ viewport: vp, consoleErrors: errors });
  await ctx.close();
}
await browser.close();
writeFileSync(`${out}/report-call.json`, JSON.stringify(report, null, 2));
const bad = report.filter(r => r.consoleErrors?.length || (r.status && r.status !== 200) || r.horizontalOverflowPx > 0 || r.axeViolations?.length);
console.log(JSON.stringify({ pages: report.filter(r => r.page).length, checks: report.filter(r => r.check), problems: bad }, null, 1));
