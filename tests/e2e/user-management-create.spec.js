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

test('super user can create a client account from the browser flow', async ({ page }) => {
    await loginAsSeededSuperUser(page);

    await page.goto('/admin/users/create');
    await expect(page.getByRole('heading', { name: 'Create New User' })).toBeVisible();

    const stamp = Date.now();
    const email = `e2e-client-${stamp}@example.com`;
    const username = `e2e.client.${stamp}`;
    const password = `PlaywrightClient!${stamp}`;

    await page.getByLabel('Username').fill(username);
    await page.getByLabel('Display Name').fill(`E2E Client ${stamp}`);
    await page.getByLabel('Email Address').fill(email);
    await page.getByLabel('Phone Number').fill('09175550000');
    await page.locator('#role').selectOption('client');
    await page.locator('#department').selectOption('Hoshishi');
    await page.getByLabel('Password *', { exact: true }).fill(password);
    await page.getByLabel('Confirm Password *', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Create User' }).click();

    await expect(page).toHaveURL(/\/admin\/users\/clients\?search=e2e\.client\./);
    await expect(page.getByText('User created successfully.')).toBeVisible();

    await page.getByLabel('Search users').fill(email);
    await page.getByRole('button', { name: 'Filter' }).click();

    // The users list renders both a mobile card list and a desktop table (one is hidden
    // by CSS at any width), so assert on whichever copy is visible.
    await expect(page.getByText(email).filter({ visible: true })).toBeVisible();
    await expect(page.getByText(`@${username}`).filter({ visible: true })).toBeVisible();
});

test('enhanced select supports keyboard navigation on the create user form', async ({ page }) => {
    await loginAsSeededSuperUser(page);

    await page.goto('/admin/users/create');
    await expect(page.getByRole('heading', { name: 'Create New User' })).toBeVisible();

    const departmentSelect = page.locator('.app-select').first();
    const departmentToggle = departmentSelect.locator('.app-select-toggle');

    await departmentToggle.focus();
    await departmentToggle.press('ArrowDown');
    await expect(departmentSelect).toHaveClass(/is-open/);

    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');

    await expect(departmentToggle).toContainText('Glade');
    await expect(page.locator('#department')).toHaveValue('Glade');
});

