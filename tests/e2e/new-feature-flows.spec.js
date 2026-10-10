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

const login = async (page, { email, expectedDashboard }) => {
    await page.context().clearCookies();
    await page.goto('/login');
    await page.getByLabel('Username or Email').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('Playwright123!');
    await page.getByRole('button', { name: 'Sign in' }).click();

    await Promise.race([
        page.waitForURL(new RegExp(`${expectedDashboard}$`), { timeout: 7_000 }),
        page.waitForURL(/\/legal\/acceptance(?:\?.*)?$/, { timeout: 7_000 }),
    ]);

    await completeLegalAcceptanceIfNeeded(page);
    await expect(page).toHaveURL(new RegExp(`${expectedDashboard}$`));
};

const loginAsSeededSuperUser = async (page) => {
    await login(page, {
        email: 'e2e-super@example.com',
        expectedDashboard: '/admin/dashboard',
    });
};

test('saved ticket views work in the browser', async ({ page }) => {
    const stamp = Date.now();
    const viewName = `E2E Severity View ${stamp}`;

    await loginAsSeededSuperUser(page);

    await page.goto('/admin/tickets?tab=tickets&priority=severity_1');
    await expect(page.getByRole('heading', { name: 'Tickets' })).toBeVisible();
    await page.getByPlaceholder('Name current view').fill(viewName);
    await page.getByRole('button', { name: 'Save View' }).click();

    await expect(page).toHaveURL(/\/admin\/tickets\?tab=tickets&priority=severity_1$/);
    await expect(page.getByText(`Saved view "${viewName}".`)).toBeVisible();

    const savedViewLink = page.getByRole('link', { name: viewName });
    await expect(savedViewLink).toBeVisible();

    await page.goto('/admin/tickets?tab=tickets');
    await savedViewLink.click();

    await expect(page).toHaveURL(/\/admin\/tickets\?tab=tickets&priority=severity_1$/);
    await expect(page.getByRole('link', { name: viewName })).toHaveAttribute('aria-current', 'true');
});
