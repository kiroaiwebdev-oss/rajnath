// Calculator UI check: Kundli, Matching, Numerology, Panchang — desktop + phone, axe + overflow.
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import { writeFileSync, mkdirSync } from 'node:fs';
const [base, out] = process.argv.slice(2);
mkdirSync(out, { recursive: true });
const report = [];
const audit = async (page, vp, name) => {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  await page.screenshot({ path: `${out}/${vp}-${name}.png`, fullPage: true });
  report.push({ viewport: vp, page: name, horizontalOverflowPx: overflow, axeViolations: axe.violations.map(v => ({ id: v.id, nodes: v.nodes.length, sample: v.nodes[0]?.target })) });
};
const pickPlace = async (page, inputSel) => {
  await page.click(inputSel); await page.keyboard.type('Patna', { delay: 30 });
  const list = '#' + (await page.getAttribute(inputSel, 'aria-controls'));
  await page.waitForSelector(list + ' li', { state: 'visible', timeout: 8000 });
  await page.keyboard.press('ArrowDown'); await page.keyboard.press('Enter');
};
const browser = await chromium.launch();
for (const [vp, size] of Object.entries({ desktop: { width: 1440, height: 900 }, phone: { width: 390, height: 844 } })) {
  const page = await (await browser.newContext({ viewport: size })).newPage();
  const errors = []; page.on('pageerror', e => errors.push(e.message)); page.on('console', m => m.type() === 'error' && errors.push(m.text()));
  await page.goto(base + '/tools', { waitUntil: 'networkidle' }); await audit(page, vp, 'tools');
  await page.goto(base + '/tools/kundli', { waitUntil: 'networkidle' });
  await page.fill('#f-birth_date', '1990-05-14'); await page.fill('#f-birth_time', '06:30'); await pickPlace(page, '#place-');
  await Promise.all([page.waitForLoadState('networkidle'), page.click('button:has-text("Calculate")')]);
  await page.waitForSelector('text=Rashi chart (D1)'); await audit(page, vp, 'kundli-result');
  await page.goto(base + '/tools/kundli-matching', { waitUntil: 'networkidle' });
  await page.fill('#f-a_birth_date', '1990-05-14'); await page.fill('#f-a_birth_time', '06:30'); await pickPlace(page, '#place-a_');
  await page.fill('#f-b_birth_date', '1992-08-20'); await page.fill('#f-b_birth_time', '14:10'); await pickPlace(page, '#place-b_');
  await Promise.all([page.waitForLoadState('networkidle'), page.click('button:has-text("Calculate")')]);
  await page.waitForSelector('text=/ 36 gunas'); await audit(page, vp, 'matching-result');
  await page.goto(base + '/tools/lo-shu-grid', { waitUntil: 'networkidle' });
  await page.fill('#f-birth_date', '1990-05-14');
  await Promise.all([page.waitForLoadState('networkidle'), page.click('button:has-text("Calculate")')]);
  await audit(page, vp, 'loshu-result');
  await page.goto(base + '/panchang', { waitUntil: 'networkidle' }); await audit(page, vp, 'panchang');
  report.push({ viewport: vp, consoleErrors: errors });
}
await browser.close();
writeFileSync(`${out}/report-tools.json`, JSON.stringify(report, null, 2));
