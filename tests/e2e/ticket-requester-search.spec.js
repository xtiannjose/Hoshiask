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

// The page's own script attaches the search handler a moment after the page paints (app.js sets this marker when it is
// done); typing earlier would not search.
const waitForPageScripts = (page) => expect(page.locator('html')).toHaveAttribute('data-page-scripts-ready', 'true');

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

test('staff can search for the requester account on the new ticket form', async ({ page }) => {
    await loginAsSeededSuperUser(page);

    await page.goto('/admin/tickets/create');
    await expect(page.getByRole('heading', { name: 'Create Support Ticket' })).toBeVisible();
    await waitForPageScripts(page);

    const search = page.getByLabel('Search accounts by name, email, department or phone');
    const accountPicker = page.locator('#user_id + .app-select');

    // Enter must search, not send the whole ticket form.
    await search.fill('e2e-client@example.com');
    await search.press('Enter');
    await expect(page).toHaveURL(/\/admin\/tickets\/create$/);
    await expect(page.locator('[data-requester-account-help]')).toContainText('1 matching account');

    await accountPicker.locator('.app-select-toggle').click();
    await accountPicker.getByRole('option', { name: 'E2E Client', exact: true }).click();

    await expect(page.locator('#user_id')).not.toHaveValue('');
    await expect(page.locator('#email')).toHaveValue('e2e-client@example.com');
});

test('a search with no match says so and keeps the account that was already chosen', async ({ page }) => {
    await loginAsSeededSuperUser(page);

    await page.goto('/admin/tickets/create');
    await waitForPageScripts(page);

    const search = page.getByLabel('Search accounts by name, email, department or phone');
    const help = page.locator('[data-requester-account-help]');
    const accountPicker = page.locator('#user_id + .app-select');

    // Choose an account explicitly. (With a single client the form chooses it by itself, so the test cannot assume
    // that nothing is chosen at the start: it depends on how many clients the database holds.)
    await search.fill('e2e-client@example.com');
    await expect(help).toContainText('1 matching account');
    await accountPicker.locator('.app-select-toggle').click();
    await accountPicker.getByRole('option', { name: 'E2E Client', exact: true }).click();
    const chosen = await page.locator('#user_id').inputValue();
    expect(chosen).not.toBe('');

    await search.fill('no-such-account-anywhere');

    await expect(help).toContainText('No matching accounts');
    await expect(page.locator('#user_id')).toHaveValue(chosen);
});
