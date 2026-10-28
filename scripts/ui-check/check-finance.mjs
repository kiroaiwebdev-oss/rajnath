// Wallet + finance UI check: customer OTP sign-up, seeded wallet data, wallet/payment/receipt/
// checkout screens (Razorpay script blocked to exercise the failure message), then staff finance
// screens. Screenshots + axe WCAG 2.2 AA + horizontal overflow + console errors, desktop and phone.
// Usage: node check-finance.mjs <baseUrl> <laravelLogPath> <outDir> <staffEmail> <totpSecret> <password>
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { createHmac } from 'node:crypto';

const [base, logPath, out, staffEmail, secret, password] = process.argv.slice(2);
mkdirSync(out, { recursive: true });
const here = new URL('.', import.meta.url).pathname;
const latestCode = () => { const m = [...readFileSync(logPath, 'utf8').matchAll(/Subject: (\d{6}) is your/g)]; return m.at(-1)?.[1]; };
function base32(s) { const a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; let bits = ''; const bytes = [];
  for (const c of s.replace(/=+$/, '')) bits += a.indexOf(c).toString(2).padStart(5, '0');
  for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2)); return Buffer.from(bytes); }
function totp(sec, offset = 0) { const msg = Buffer.alloc(8); msg.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000) + offset));
  const h = createHmac('sha1', base32(sec)).update(msg).digest(); const o = h[h.length - 1] & 15;
  return String(((h.readUInt32BE(o) & 0x7fffffff) % 1e6)).padStart(6, '0'); }

const report = [];
const audit = async (page, vp, name, errors) => {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  await page.screenshot({ path: `${out}/${vp}-${name}.png`, fullPage: true });
  report.push({ viewport: vp, page: name, url: page.url().replace(base, ''), horizontalOverflowPx: overflow, consoleErrors: errors.splice(0),
    axeViolations: axe.violations.map(v => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, sample: v.nodes[0]?.target })) });
};
const browser = await chromium.launch();
const vps = { desktop: { width: 1440, height: 900 }, phone: { width: 390, height: 844, isMobile: true, hasTouch: true } };
let step = 0;
for (const [vp, size] of Object.entries(vps)) {
  const ctx = await browser.newContext({ viewport: { width: size.width, height: size.height }, isMobile: size.isMobile, hasTouch: size.hasTouch });
  await ctx.route('https://checkout.razorpay.com/**', r => r.abort()); // simulate the payment script failing to load
  const page = await ctx.newPage();
  const errors = [];
  page.on('console', m => { if (m.type() === 'error' && !m.text().includes('checkout.razorpay.com') && !m.text().includes('ERR_FAILED')) errors.push(m.text()); });
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));

  const email = `wallet-${vp}-${Date.now()}@example.com`;
  await page.goto(base + '/account/sign-in');
  await page.fill('#f-identifier', email);
  await Promise.all([page.waitForURL('**/account/verify'), page.click('button:has-text("Send code")')]);
  await page.fill('#f-code', latestCode()); await page.fill('#f-name', 'Wallet Tester'); await page.check('input[name="terms"]');
  await Promise.all([page.waitForURL('**/account'), page.click('button:has-text("Create account")')]);
  const createdRef = execFileSync('php', [here + 'seed-finance-demo.php', email]).toString().trim();

  for (const [name, path] of [['account-dashboard', '/account'], ['wallet', '/account/wallet']]) {
    await page.goto(base + path, { waitUntil: 'networkidle' }); await audit(page, vp, name, errors);
  }
  await page.click('a:has-text("Details") >> nth=0'); await page.waitForLoadState('networkidle');
  await audit(page, vp, 'payment-detail', errors);
  await page.goto(base + '/account/wallet', { waitUntil: 'networkidle' });
  const capturedHref = await page.evaluate(() => [...document.querySelectorAll('tbody tr')].find(r => r.textContent.includes('Paid'))?.querySelector('a')?.getAttribute('href'));
  await page.goto(new URL(capturedHref + '/receipt', base).href, { waitUntil: 'networkidle' }); await audit(page, vp, 'receipt', errors);
  await page.goto(base + `/account/payments/${createdRef}/checkout`, { waitUntil: 'networkidle' });
  await page.waitForFunction(() => document.querySelector('[role=status][aria-live]')?.textContent.includes('could not load'), null, { timeout: 8000 });
  report.push({ viewport: vp, check: 'checkout-script-blocked-message', ok: true });
  await audit(page, vp, 'checkout-load-failure', errors);
  await page.click('form[action$="sign-out"] button');

  // Staff finance screens.
  await page.goto(base + '/staff/login');
  await page.fill('#f-email', staffEmail); await page.fill('#f-password', password);
  await Promise.all([page.waitForURL('**/staff/mfa'), page.click('button:has-text("Continue")')]);
  await page.fill('#f-code', totp(secret, step++));
  await Promise.all([page.waitForURL('**/admin'), page.click('button:has-text("Verify")')]);
  for (const [name, path] of [['admin-finance', '/admin/finance'], ['admin-payments', '/admin/finance/payments'], ['admin-refunds', '/admin/finance/refunds'],
    ['admin-disputes', '/admin/finance/disputes'], ['admin-packages', '/admin/finance/packages'], ['admin-razorpay-setup', '/admin/payments-setup']]) {
    await page.goto(base + path, { waitUntil: 'networkidle' }); await audit(page, vp, name, errors);
  }
  await page.goto(base + '/admin/finance/payments', { waitUntil: 'networkidle' });
  await page.click('tbody a >> nth=0'); await page.waitForLoadState('networkidle'); await audit(page, vp, 'admin-payment-detail', errors);
  await ctx.close();
}
await browser.close();
writeFileSync(`${out}/report.json`, JSON.stringify(report, null, 2));
const bad = report.filter(r => (r.axeViolations?.length) || r.horizontalOverflowPx > 0 || r.consoleErrors?.length);
console.log(`${report.length} checks, ${bad.length} with problems`);
for (const b of bad) console.log(JSON.stringify(b));
