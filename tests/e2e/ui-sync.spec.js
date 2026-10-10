import { expect, test } from '@playwright/test';

test.beforeEach(async ({ page }) => {
    await page.goto('/login');
});

test('dark mode toggle persists after reload', async ({ page }) => {
    const html = page.locator('html');
    const toggleButton = page.getByRole('button', { name: 'Toggle dark mode' });

    await page.evaluate(() => {
        window.localStorage.removeItem('hoshishi_theme');
    });
    await page.reload();

    await expect(html).not.toHaveClass(/theme-dark/);

    await toggleButton.click();
    await expect(html).toHaveClass(/theme-dark/);

    await page.reload();
    await expect(html).toHaveClass(/theme-dark/);
});

test('legal modal opens and closes correctly in light and dark mode', async ({ page }) => {
    const html = page.locator('html');
    const toggleButton = page.getByRole('button', { name: 'Toggle dark mode' });
    const openTermsButton = page.getByRole('button', { name: 'Terms' }).first();
    const modal = page.getByRole('dialog', { name: 'Legal documents' });

    await page.evaluate(() => {
        window.localStorage.removeItem('hoshishi_theme');
    });
    await page.reload();

    await openTermsButton.click();
    await expect(modal).toBeVisible();
    await expect(modal).toHaveClass(/is-open/);

    await page.getByRole('button', { name: 'Close legal modal' }).click();
    await expect(modal).toBeHidden();

    await toggleButton.click();
    await expect(html).toHaveClass(/theme-dark/);

    await openTermsButton.click();
    await expect(modal).toBeVisible();

    const overlayColor = await modal
        .locator('.app-modal-overlay')
        .evaluate((node) => window.getComputedStyle(node).backgroundColor);
    expect(overlayColor).not.toBe('rgba(0, 0, 0, 0)');

    await page.keyboard.press('Escape');
    await expect(modal).toBeHidden();
});
