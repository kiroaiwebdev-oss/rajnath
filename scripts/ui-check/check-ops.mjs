// Phase 8 UI check: public Rashifal + vrat calendar, customer booking/puja/report/support/privacy pages
// (real OTP sign-in) and staff operations pages (password + TOTP). Desktop + phone, axe + overflow.
// Usage: node check-ops.mjs <baseUrl> <laravelLogPath> <outDir> <fixturesJson> <staffEmail> <totpSecret> <staffPassword>
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import { createHmac } from 'node:crypto';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';

const [base, logPath, out, fixturesJson, staffEmail, secret, password] = process.argv.slice(2);
const fx = JSON.parse(fixturesJson);
mkdirSync(out, { recursive: true });
const latestCode = () => {
  const m = [...readFileSync(logPath, 'utf8').matchAll(/Subject: (\d{6}) is your/g)];
  return m.length ? m[m.length - 1][1] : null;
};
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
const report = [];
const audit = async (page, vp, name, status) => {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  await page.screenshot({ path: `${out}/${vp}-${name}.png`, fullPage: true });
  report.push({ viewport: vp, page: name, status, url: page.url().replace(base, ''), horizontalOverflowPx: overflow,
    axeViolations: axe.violations.map(v => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, sample: v.nodes[0]?.target })) });
};
const visit = async (page, vp, name, path) => {
  const res = await page.goto(base + path, { waitUntil: 'networkidle' });
  await audit(page, vp, name, res.status());
};
const browser = await chromium.launch();
const vps = { desktop: { width: 1440, height: 900 }, phone: { width: 390, height: 844, isMobile: true, hasTouch: true } };
let step = 0;
for (const [vp, size] of Object.entries(vps)) {
  const ctx = await browser.newContext({ viewport: { width: size.width, height: size.height }, isMobile: size.isMobile, hasTouch: size.hasTouch });
  const page = await ctx.newPage();
  const errors = [];
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await visit(page, vp, 'rashifal', '/rashifal');
  await visit(page, vp, 'calendar', '/calendar');

  // Customer sign-in via OTP
  await page.goto(base + '/account/sign-in', { waitUntil: 'networkidle' });
  await page.fill('#f-identifier', fx.email);
  await Promise.all([page.waitForURL('**/account/verify'), page.click('button:has-text("Send code")')]);
  await page.fill('#f-code', latestCode());
  if (await page.locator('input[name="terms"]').count()) await page.check('input[name="terms"]');
  await Promise.all([page.waitForURL('**/account**'), page.click('form button[type="submit"], form button.btn-primary')]);
  for (const [name, path] of [['acct-bookings', '/account/bookings'], ['acct-book-pick', `/account/book/${fx.variant}`], ['acct-reschedule', `/account/bookings/${fx.booking}/reschedule`],
    ['acct-puja-list', '/account/puja'], ['acct-puja-show', `/account/puja/${fx.puja}`], ['acct-reports', '/account/reports'], ['acct-report-show', `/account/reports/${fx.report}`],
    ['acct-support', '/account/support'], ['acct-ticket', `/account/support/${fx.ticket}`], ['acct-privacy', '/account/privacy']]) {
    await visit(page, vp, name, path);
  }
  // Interact: pick a slot (keyboard focus ring + checked state) without submitting
  await page.goto(base + `/account/book/${fx.variant}`, { waitUntil: 'networkidle' });
  const firstSlot = page.locator('input[name="start"]').first();
  if (await firstSlot.count()) { await firstSlot.check({ force: true }); await page.screenshot({ path: `${out}/${vp}-acct-book-picked.png` }); }
  await ctx.clearCookies();

  // Staff
  await page.goto(base + '/staff/login');
  await page.fill('#f-email', staffEmail); await page.fill('#f-password', password);
  await Promise.all([page.waitForURL('**/staff/mfa'), page.click('button:has-text("Continue")')]);
  await page.fill('#f-code', totp(secret, step++));
  await Promise.all([page.waitForURL('**/admin'), page.click('button:has-text("Verify")')]);
  for (const [name, path] of [['admin-bookings', '/admin/bookings'], ['admin-suppliers', '/admin/suppliers'], ['admin-pujas', '/admin/pujas?status=all'], ['admin-puja', `/admin/pujas/${fx.puja}`],
    ['admin-reports', '/admin/reports?status=all'], ['admin-report', `/admin/reports/${fx.report}`], ['admin-support', '/admin/support?status=all'], ['admin-ticket', `/admin/support/${fx.ticket}`], ['admin-rashifal', '/admin/rashifal']]) {
    await visit(page, vp, name, path);
  }
  report.push({ viewport: vp, consoleErrors: errors });
  await ctx.close();
}
await browser.close();
writeFileSync(`${out}/report-ops.json`, JSON.stringify(report, null, 2));
const bad = report.filter(r => r.consoleErrors?.length || (r.status && r.status !== 200) || r.horizontalOverflowPx > 0 || r.axeViolations?.length);
console.log(JSON.stringify({ pages: report.filter(r => r.page).length, problems: bad }, null, 1));
