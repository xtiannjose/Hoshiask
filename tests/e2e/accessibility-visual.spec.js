import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const resetTheme = async (page) => {
    await page.evaluate(() => {
        window.localStorage.removeItem('hoshishi_theme');
    });
    await page.reload();
};

const closeLegalModalIfOpen = async (page) => {
    const legalModal = page.getByRole('dialog', { name: 'Legal documents' });
    if (!await legalModal.isVisible()) return;

    const closeButton = page.getByRole('button', { name: 'Close legal modal' });
    if (await closeButton.isVisible()) {
        await closeButton.click();
    } else {
        await page.keyboard.press('Escape');
    }

    await expect(legalModal).toBeHidden();
};

test('login page has no critical accessibility violations', async ({ page }) => {
    await page.goto('/login');
    await closeLegalModalIfOpen(page);

    const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
    const criticalViolations = accessibilityScanResults.violations.filter(
        (violation) => violation.impact === 'critical',
    );

    expect(
        criticalViolations,
        JSON.stringify(criticalViolations, null, 2),
    ).toEqual([]);
});

test('login visual regression baseline for light and dark themes', async ({ page }) => {
    await page.goto('/login');
    await resetTheme(page);
    await closeLegalModalIfOpen(page);

    await expect(page).toHaveScreenshot('login-light.png', {
        animations: 'disabled',
        fullPage: true,
        maxDiffPixelRatio: 0.01,
    });

    await page.getByRole('button', { name: 'Toggle dark mode' }).click();
    await expect(page).toHaveScreenshot('login-dark.png', {
        animations: 'disabled',
        fullPage: true,
        maxDiffPixelRatio: 0.01,
    });
});

test('login visual baseline on mobile viewport', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await resetTheme(page);
    await closeLegalModalIfOpen(page);

    await expect(page).toHaveScreenshot('login-mobile-light.png', {
        animations: 'disabled',
        fullPage: true,
        maxDiffPixelRatio: 0.01,
    });
});
