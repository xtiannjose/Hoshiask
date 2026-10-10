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

// The shared delete dialog is wired by app.js; a click before it is done would do nothing.
const waitForPageScripts = (page) => expect(page.locator('html')).toHaveAttribute('data-page-scripts-ready', 'true');

// Opens the dialog from a row's trash icon, shows that Cancel and a missing tick send nothing, then deletes.
const deleteThroughTheDialog = async (page, { name, title }) => {
    const dialog = page.getByRole('dialog', { name: title });
    const confirmButton = dialog.getByRole('button', { name: title });
    const trashIcon = page.getByRole('button', { name: `${title}: ${name}` });

    await expect(trashIcon).toBeVisible();
    await trashIcon.click();
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText(name);
    await expect(confirmButton).toBeDisabled();

    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(dialog).toBeHidden();
    await expect(trashIcon).toBeVisible();

    await trashIcon.click();
    await expect(confirmButton).toBeDisabled();
    await dialog.getByLabel('I understand this action is permanent.').check();
    await expect(confirmButton).toBeEnabled();
    await confirmButton.click();
};

test('a knowledge base article is deleted only after the confirmation dialog', async ({ page }) => {
    const title = `Delete me article ${Date.now()}`;

    await loginAsSeededSuperUser(page);
    await page.goto('/admin/knowledge-base/create');
    await waitForPageScripts(page);
    await page.getByLabel('Title').fill(title);
    await page.getByLabel('Content').fill('Temporary article for the delete confirmation test.');
    await page.getByRole('button', { name: 'Create Article' }).click();

    await page.goto('/admin/knowledge-base');
    await waitForPageScripts(page);
    await deleteThroughTheDialog(page, { name: title, title: 'Delete Article' });

    await expect(page.getByText('Article deleted.')).toBeVisible();
    await expect(page.getByRole('button', { name: `Delete Article: ${title}` })).toHaveCount(0);
});

test('a canned response is deleted only after the confirmation dialog', async ({ page }) => {
    const title = `Delete me response ${Date.now()}`;

    await loginAsSeededSuperUser(page);
    await page.goto('/admin/canned-responses');
    await waitForPageScripts(page);
    await page.getByLabel('Title').first().fill(title);
    await page.getByLabel('Body').first().fill('Temporary response for the delete confirmation test.');
    await page.getByRole('button', { name: 'Create Response' }).click();
    await expect(page.getByText('Canned response created.')).toBeVisible();
    await waitForPageScripts(page);

    // The row's buttons show on hover; hovering also keeps the test close to what a person does.
    await page.locator('.group', { hasText: title }).first().hover();
    await deleteThroughTheDialog(page, { name: title, title: 'Delete Canned Response' });

    await expect(page.getByText('Canned response deleted.')).toBeVisible();
    await expect(page.getByRole('button', { name: `Delete Canned Response: ${title}` })).toHaveCount(0);
});
