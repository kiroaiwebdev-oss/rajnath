// Customer-side UI check: public pages, real OTP sign-in (code read from the local mail log),
// birth-profile place combobox, account pages. Screenshots + axe + overflow at desktop and phone.
// Usage: node check-customer.mjs <baseUrl> <laravelLogPath> <outDir>
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';

const [base, logPath, out] = process.argv.slice(2);
mkdirSync(out, { recursive: true });
const latestCode = () => {
  const m = [...readFileSync(logPath, 'utf8').matchAll(/Subject: (\d{6}) is your/g)];
  return m.length ? m[m.length - 1][1] : null;
};
const report = [];
const audit = async (page, vp, name) => {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  await page.screenshot({ path: `${out}/${vp}-${name}.png`, fullPage: true });
  report.push({ viewport: vp, page: name, url: page.url().replace(base, ''), horizontalOverflowPx: overflow,
    axeViolations: axe.violations.map(v => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, sample: v.nodes[0]?.target })) });
};
const browser = await chromium.launch();
const vps = { desktop: { width: 1440, height: 900 }, phone: { width: 390, height: 844, isMobile: true, hasTouch: true } };
for (const [vp, size] of Object.entries(vps)) {
  const ctx = await browser.newContext({ viewport: { width: size.width, height: size.height }, isMobile: size.isMobile, hasTouch: size.hasTouch });
  const page = await ctx.newPage();
  const errors = [];
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  for (const [name, path] of [['home', '/'], ['services', '/services'], ['service-detail', '/services/vastu-consultation'], ['about', '/about'], ['contact', '/contact'], ['faq', '/faq'], ['sign-in', '/account/sign-in']]) {
    await page.goto(base + path, { waitUntil: 'networkidle' });
    await audit(page, vp, name);
  }
  // Real OTP sign-up.
  const email = `ui-${vp}-${Date.now()}@example.com`;
  await page.fill('#f-identifier', email);
  await Promise.all([page.waitForURL('**/account/verify'), page.click('button:has-text("Send code")')]);
  await audit(page, vp, 'verify');
  await page.fill('#f-code', latestCode());
  await page.fill('#f-name', 'UI Tester');
  await page.check('input[name="terms"]');
  await Promise.all([page.waitForURL('**/account'), page.click('button:has-text("Create account")')]);
  await audit(page, vp, 'account-dashboard');

  // Birth profile with the place combobox (keyboard selection).
  await page.goto(base + '/account/birth-profiles/create', { waitUntil: 'networkidle' });
  await page.fill('#f-name', 'UI Tester');
  await page.fill('#f-birth_date', '1990-05-14');
  await page.fill('#f-birth_time', '06:30');
  await page.click('#place-input');
  await page.keyboard.type('Patna', { delay: 40 });
  await page.waitForSelector('#place-list li', { timeout: 8000 });
  await audit(page, vp, 'birth-profile-form');
  await page.keyboard.press('ArrowDown');
  await page.keyboard.press('Enter');
  const placeId = await page.inputValue('input[name="place_id"]');
  await Promise.all([page.waitForURL('**/account/birth-profiles'), page.click('button:has-text("Save")')]);
  await audit(page, vp, 'birth-profiles');
  const saved = await page.textContent('main');
  report.push({ viewport: vp, check: 'place-combobox', placeIdSelected: placeId, savedShowsPatna: saved.includes('Patna'), savedShowsOffset: saved.includes('UTC+05:30') });
  for (const [name, path] of [['account-profile', '/account/profile'], ['account-devices', '/account/devices']]) {
    await page.goto(base + path, { waitUntil: 'networkidle' });
    await audit(page, vp, name);
  }
  // Enquiry from the service page.
  await page.goto(base + '/services/janma-kundali-reading', { waitUntil: 'networkidle' });
  await page.fill('#f-phone', '9123456789');
  await page.check('input[name="consent"]');
  await Promise.all([page.waitForURL('**/received'), page.click('button:has-text("Send request")')]);
  await audit(page, vp, 'enquiry-received');
  if (vp === 'phone') {
    await page.goto(base + '/');
    await page.click('button[aria-controls="mobile-nav"]');
    await page.waitForTimeout(300);
    await page.screenshot({ path: `${out}/phone-home-menu.png` });
  }
  report.push({ viewport: vp, consoleErrors: errors });
  await ctx.close();
}
await browser.close();
writeFileSync(`${out}/report-customer.json`, JSON.stringify(report, null, 2));
console.log(JSON.stringify(report.filter(r => r.check || r.consoleErrors)));
