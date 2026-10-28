// Store + palmistry pages: desktop and phone, axe WCAG + horizontal overflow.
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import { writeFileSync, mkdirSync } from 'node:fs';
const [base, slug, out] = process.argv.slice(2);
mkdirSync(out, { recursive: true });
const report = [];
const b = await chromium.launch();
for (const [vp, size] of Object.entries({ desktop: { width: 1440, height: 900 }, phone: { width: 390, height: 844 } })) {
  const page = await (await b.newContext({ viewport: size })).newPage();
  const errors = []; page.on('pageerror', e => errors.push(e.message));
  for (const [name, path] of [['gems', '/gems'], ['gem-detail', '/gems/' + slug], ['palmistry-service', '/services/palmistry-photo-report'], ['home', '/']]) {
    await page.goto(base + path, { waitUntil: 'networkidle' });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
    await page.screenshot({ path: `${out}/${vp}-${name}.png`, fullPage: true });
    report.push({ viewport: vp, page: name, horizontalOverflowPx: overflow, axeViolations: axe.violations.map(v => ({ id: v.id, nodes: v.nodes.length, sample: v.nodes[0]?.target })) });
  }
  report.push({ viewport: vp, consoleErrors: errors });
}
await b.close();
writeFileSync(`${out}/report-store.json`, JSON.stringify(report, null, 2));
