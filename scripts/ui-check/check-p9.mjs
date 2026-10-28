// Phase 9 UI check: assistant page, customer notifications + astrologer chooser, staff push/AI/practitioner
// pages and the practitioner's own "My practice". Desktop + phone, axe + overflow + console errors.
// Usage: node check-p9.mjs <baseUrl> <laravelLogPath> <outDir> <opsFixturesJson> <p9FixturesJson> <ownerEmail> <ownerSecret> <ownerPassword>
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import { createHmac } from 'node:crypto';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';

const [base, logPath, out, opsJson, p9Json, ownerEmail, ownerSecret, ownerPassword] = process.argv.slice(2);
const ops = JSON.parse(opsJson), p9 = JSON.parse(p9Json);
mkdirSync(out, { recursive: true });
const latestCode = () => { const m = [...readFileSync(logPath, 'utf8').matchAll(/Subject: (\d{6}) is your/g)]; return m.length ? m[m.length - 1][1] : null; };
function base32(s) { const a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; let bits = ''; const bytes = []; for (const c of s.replace(/=+$/, '')) bits += a.indexOf(c).toString(2).padStart(5, '0'); for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2)); return Buffer.from(bytes); }
function totp(sec, offset = 0) { const step = Math.floor(Date.now() / 30000) + offset; const msg = Buffer.alloc(8); msg.writeBigUInt64BE(BigInt(step)); const h = createHmac('sha1', base32(sec)).update(msg).digest(); const o = h[h.length - 1] & 15; return String(((h.readUInt32BE(o) & 0x7fffffff) % 1e6)).padStart(6, '0'); }
const report = [];
const visit = async (page, vp, name, path) => {
  const res = await page.goto(base + path, { waitUntil: 'networkidle' });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  await page.screenshot({ path: `${out}/${vp}-${name}.png`, fullPage: true });
  report.push({ viewport: vp, page: name, status: res.status(), url: page.url().replace(base, ''), horizontalOverflowPx: overflow,
    axeViolations: axe.violations.map(v => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, sample: v.nodes[0]?.target })) });
};
const staffLogin = async (page, email, password, secret, offset) => {
  await page.goto(base + '/staff/login');
  await page.fill('#f-email', email); await page.fill('#f-password', password);
  await Promise.all([page.waitForURL('**/staff/mfa'), page.click('button:has-text("Continue")')]);
  await page.fill('#f-code', totp(secret, offset));
  await Promise.all([page.waitForURL('**/admin'), page.click('button:has-text("Verify")')]);
};
const browser = await chromium.launch();
const vps = { desktop: { width: 1440, height: 900 }, phone: { width: 390, height: 844, isMobile: true, hasTouch: true } };
// Each account may use a TOTP step once: wait for a fresh 30-second window before each viewport.
const nextWindow = async () => { const ms = 30000 - (Date.now() % 30000) + 500; await new Promise(r => setTimeout(r, ms)); };
let first = true;
for (const [vp, size] of Object.entries(vps)) {
  const ctx = await browser.newContext({ viewport: { width: size.width, height: size.height }, isMobile: size.isMobile, hasTouch: size.hasTouch });
  const page = await ctx.newPage();
  const errors = [];
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await visit(page, vp, 'assistant', '/assistant');
  // Crisis message exercises the chat end to end without an AI provider
  await page.fill('#f-message', 'I want to die');
  await page.click('button:has-text("Send")');
  await page.waitForSelector('.chat-bot', { timeout: 8000 });
  await page.screenshot({ path: `${out}/${vp}-assistant-chat.png`, fullPage: true });
  report.push({ viewport: vp, check: 'assistant-chat', reply: (await page.textContent('.chat-bot'))?.slice(0, 60), handoffShown: await page.isVisible('a:has-text("Talk to our team")') });

  await page.goto(base + '/account/sign-in', { waitUntil: 'networkidle' });
  await page.fill('#f-identifier', ops.email);
  await Promise.all([page.waitForURL('**/account/verify'), page.click('button:has-text("Send code")')]);
  await page.fill('#f-code', latestCode());
  if (await page.locator('input[name="terms"]').count()) await page.check('input[name="terms"]');
  await Promise.all([page.waitForURL('**/account**'), page.click('form button[type="submit"], form button.btn-primary')]);
  await visit(page, vp, 'acct-notifications', '/account/notifications');
  await visit(page, vp, 'acct-choose-astrologer', `/account/book/${p9.team_variant}`);
  await visit(page, vp, 'acct-astrologer-slots', `/account/book/${p9.team_variant}?practitioner=${p9.practitioner}`);
  await ctx.clearCookies();

  if (!first) await nextWindow();
  first = false;
  await staffLogin(page, ownerEmail, ownerPassword, ownerSecret, 0);
  for (const [name, path] of [['admin-push', '/admin/push'], ['admin-assistant', '/admin/assistant'], ['admin-practitioners', '/admin/practitioners'], ['admin-practitioner', `/admin/practitioners/${p9.practitioner}`]]) {
    await visit(page, vp, name, path);
  }
  await ctx.clearCookies();
  await staffLogin(page, p9.email, p9.password, p9.secret, 0);
  await visit(page, vp, 'practice', '/admin/practice');
  report.push({ viewport: vp, consoleErrors: errors });
  await ctx.close();
}
await browser.close();
writeFileSync(`${out}/report-p9.json`, JSON.stringify(report, null, 2));
const bad = report.filter(r => r.consoleErrors?.length || (r.status && r.status !== 200) || r.horizontalOverflowPx > 0 || r.axeViolations?.length);
console.log(JSON.stringify({ pages: report.filter(r => r.page).length, checks: report.filter(r => r.check), problems: bad }, null, 1));
