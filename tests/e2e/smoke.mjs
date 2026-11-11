import { chromium } from 'playwright';
const base = process.env.E2E_BASE_URL || 'http://localhost:8080';
const shots = process.env.E2E_SHOTS || '/tmp';
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push('console: ' + m.text()); });
page.on('response', (r) => { if (r.status() >= 500) errors.push(`HTTP ${r.status()} ${r.url()}`); });
let failed = 0;
const step = async (name, fn) => { try { await fn(); console.log('OK  ', name); } catch (e) { failed++; console.log('FAIL', name, '-', e.message.split('\n')[0]); } };

await step('login', async () => {
  await page.goto(base + '/login');
  await page.fill('#email', 'student@demo.test');
  await page.fill('#password', 'password');
  await page.click('button[type=submit]');
  await page.waitForURL('**/dashboard', { timeout: 15000 });
});
await page.screenshot({ path: `${shots}/dashboard.png`, fullPage: true });
await step('open lesson from dashboard', async () => {
  await page.click('text=Mulai lesson');
  await page.waitForURL('**/learn/lessons/**');
});
await step('practice mcq check', async () => {
  const card = page.locator('section[aria-labelledby=practice-title] .card').first();
  await card.locator('[role=radio]').first().click();
  await card.locator('text=Periksa').click();
  await card.locator('text=/Benar|Belum tepat/').waitFor({ timeout: 10000 });
});
await step('word order + fill blank render', async () => {
  await page.locator('text=Susun kata-kata').first().waitFor();
  await page.locator('input[aria-label="Isian 1"]').first().waitFor();
});
await page.screenshot({ path: `${shots}/lesson.png`, fullPage: true });
await step('complete lesson -> quiz', async () => {
  await page.click('text=Tandai selesai');
  await page.click('text=Mulai quiz');
  await page.waitForURL('**/exams/**');
});
await step('start quiz', async () => {
  await page.click('text=Mulai ujian');
  await page.waitForURL('**/exams/attempts/**', { timeout: 15000 });
});
await step('answer all + submit', async () => {
  const total = Number(await page.locator('nav[aria-label="Navigasi soal"] button').count());
  for (let i = 0; i < total; i++) {
    const opts = page.locator('section [role=radio], section [role=checkbox]');
    if (await opts.count()) await opts.first().click();
    else {
      const input = page.locator('section input[type=text]').first();
      if (await input.count()) await input.fill('am');
    }
    await page.waitForTimeout(400);
    if (i < total - 1) await page.click('text=Berikutnya');
  }
  await page.locator('text=Tersimpan ✓').first().waitFor({ timeout: 5000 });
  page.once('dialog', (d) => d.accept());
  await page.locator('button:has-text("Kumpulkan")').first().click();
  await page.waitForURL('**/result', { timeout: 15000 });
});
await page.screenshot({ path: `${shots}/result.png`, fullPage: true });
await step('flashcards', async () => {
  await page.goto(base + '/flashcards');
  await page.locator('text=/Ketuk untuk melihat arti|Tidak ada kartu/').first().waitFor({ timeout: 10000 });
});
await step('path', async () => { await page.goto(base + '/learn/A1'); await page.locator('text=Unit Exam').first().waitFor(); });
await page.screenshot({ path: `${shots}/path.png`, fullPage: true });
await step('tenses', async () => { await page.goto(base + '/tenses'); await page.locator('text=Simple Present').waitFor(); });
console.log('ERRORS:', errors.length ? '\n' + [...new Set(errors)].join('\n') : 'none');
await browser.close();
process.exit(errors.length || failed ? 1 : 0);
