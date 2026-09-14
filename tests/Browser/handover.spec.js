import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { readFileSync } from 'node:fs';

const fixture = () => JSON.parse(readFileSync('.browser/fixture.json', 'utf8'));
async function accessible(page) {
    const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    expect(result.violations.map(v => ({ id: v.id, nodes: v.nodes.map(n => ({ html: n.html, summary: n.failureSummary })) }))).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)).toBe(false);
}
async function login(page) {
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill('audit-admin@nc3.lu');
    await page.getByLabel('Password', { exact: true }).fill('SyntheticAuditPassword123!');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).toHaveURL(/dashboard/);
}

test('public pages and form remain accessible on desktop and mobile', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 900 });
        for (const url of ['/', '/login', `/forms/${fixture().form}/submit`]) {
            expect((await page.goto(url)).status()).toBe(200);
            await accessible(page);
            await page.emulateMedia({ colorScheme: 'dark', reducedMotion: 'reduce' });
            await page.reload();
            await accessible(page);
            await page.emulateMedia({ colorScheme: 'light' });
        }
    }
    expect(errors).toEqual([]);
});

test('conditional fields, validation focus, checkbox selections and receipt', async ({ page }) => {
    const seed = fixture();
    await page.goto(`/forms/${seed.form}/submit`);
    await expect(page.getByRole('button', { name: 'Save as Draft', exact: true })).toHaveCount(0);
    await page.locator(`#field_${seed.select_field}`).selectOption('Yes');
    await expect(page.locator(`#field_${seed.conditional_field}`)).toBeVisible();
    await page.getByRole('button', { name: 'Next', exact: true }).click();
    await page.getByLabel('Report details', { exact: false }).fill('Synthetic browser report');
    await page.getByLabel('Security', { exact: true }).check();
    await page.getByRole('button', { name: 'Submit', exact: true }).click();
    await expect(page.locator(`#field_${seed.name_field}`)).toBeVisible();
    await expect(page.locator(`#field_${seed.name_field}`)).toBeFocused();
    await expect(page.getByRole('alert')).toContainText('Your name');
    await accessible(page);
    await page.locator(`#field_${seed.name_field}`).fill('Browser submitter');
    await page.getByRole('button', { name: 'Next', exact: true }).click();
    await page.getByRole('button', { name: 'Submit', exact: true }).click();
    await expect(page).toHaveURL(/thank-you/);
    await expect(page.getByText('Reference:', { exact: false })).toBeVisible();
});

test('dashboard, locked forms and keyboard builder operations', async ({ page }) => {
    await login(page);
    await accessible(page);
    await page.goto(`/forms/${fixture().form}/edit`);
    await expect(page.getByText('Its questions are locked', { exact: false })).toBeVisible();
    await page.goto(`/forms/${fixture().builder}/edit`);
    await accessible(page);
    await page.setViewportSize({ width: 390, height: 900 });
    await accessible(page);
    await page.setViewportSize({ width: 1440, height: 1000 });
    const move = page.getByRole('button', { name: 'Move Second section section up', exact: true });
    await move.focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('[wire\\:sortable\\.item]').first()).toContainText('Second section');
    const popupPromise = page.waitForEvent('popup');
    await page.getByRole('link', { name: 'Preview Form', exact: true }).click();
    const preview = await popupPromise;
    await expect(preview).toHaveURL(/preview/);
    await preview.close();
    await page.goto('/admin');
    await accessible(page);
});
