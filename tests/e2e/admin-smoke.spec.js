import { expect, test } from '@playwright/test';

const completeLegalAcceptanceIfNeeded = async (page) => {
    if (!page.url().includes('/legal/acceptance')) {
        return;
    }

    await page.getByLabel('I agree to the Terms of Service.').check();
    await page.getByLabel('I acknowledge and consent to the Privacy Notice.').check();
    await page.getByLabel('I confirm I am authorized to use this platform and provide data needed for support.').check();
    await page.getByRole('button', { name: 'Accept and Continue' }).click();
};

const loginAsSeededSuperUser = async (page) => {
    await page.goto('/login');
    await page.getByLabel('Username or Email').fill('e2e-super@example.com');
    await page.getByLabel('Password', { exact: true }).fill('Playwright123!');
    await page.getByRole('button', { name: 'Sign in' }).click();

    await Promise.race([
        page.waitForURL(/\/admin\/dashboard$/, { timeout: 7_000 }),
        page.waitForURL(/\/legal\/acceptance(?:\?.*)?$/, { timeout: 7_000 }),
    ]);

    await completeLegalAcceptanceIfNeeded(page);
    await expect(page).toHaveURL(/\/admin\/dashboard$/);
};

test('seeded super user can access tickets and reports and download monthly pdf', async ({ page }) => {
    await loginAsSeededSuperUser(page);

    await page.goto('/admin/tickets');
    await expect(page.getByRole('heading', { name: 'Tickets' })).toBeVisible();

    await page.goto('/admin/reports');
    await expect(page.getByRole('heading', { name: 'Statistics & Reports' })).toBeVisible();

    const downloadPromise = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Download Monthly PDF' }).click();
    const download = await downloadPromise;

    expect(download.suggestedFilename()).toContain('ticket-monthly-report-');
});
