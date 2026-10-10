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

test('a knowledge base row opens the article in a popup and closes when clicking outside', async ({ page }) => {
    await loginAsSeededSuperUser(page);
    await page.goto('/admin/knowledge-base');

    const row = page.locator('.divide-y > div', { hasText: 'E2E popup article' });
    const popup = page.getByRole('dialog');
    await expect(row).toBeVisible();
    await expect(popup).toBeHidden();

    // Anywhere on the row opens it, not just the title text.
    const box = await row.boundingBox();
    await page.mouse.click(box.x + box.width * 0.55, box.y + box.height / 2);

    await expect(popup).toBeVisible();
    await expect(page).toHaveURL(/\/admin\/knowledge-base$/);
    await expect(popup.getByRole('heading', { name: 'E2E popup article' })).toBeVisible();
    await expect(popup.getByText('The seeded article has some text for the popup to show.')).toBeVisible();
    // The list stays on screen behind the popup.
    await expect(row).toBeVisible();

    // Clicking inside the popup keeps it open; clicking the dimmed page outside it closes it.
    await popup.getByRole('heading', { name: 'E2E popup article' }).click();
    await expect(popup).toBeVisible();
    await page.mouse.click(8, 8);
    await expect(popup).toBeHidden();

    // Escape and the X close it too.
    await page.mouse.click(box.x + box.width * 0.55, box.y + box.height / 2);
    await expect(popup).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(popup).toBeHidden();

    await page.mouse.click(box.x + box.width * 0.55, box.y + box.height / 2);
    await expect(popup).toBeVisible();
    await page.getByRole('button', { name: 'Close article' }).click();
    await expect(popup).toBeHidden();
});

test('Edit on a knowledge base row still goes to the edit page instead of opening the popup', async ({ page }) => {
    await loginAsSeededSuperUser(page);
    await page.goto('/admin/knowledge-base');

    const row = page.locator('.divide-y > div', { hasText: 'E2E popup article' });
    await row.getByRole('link', { name: 'Edit' }).click();

    await expect(page).toHaveURL(/\/admin\/knowledge-base\/\d+\/edit$/);
    await expect(page.getByRole('dialog')).toBeHidden();
});

test('the popup is a native modal dialog: page scroll locks and Tab stays inside it', async ({ page }) => {
    await loginAsSeededSuperUser(page);
    await page.goto('/admin/knowledge-base');

    const row = page.locator('.divide-y > div', { hasText: 'E2E popup article' });
    const popup = page.locator('dialog#kb-article-modal');
    await expect(popup).not.toHaveAttribute('open', '');

    const box = await row.boundingBox();
    await page.mouse.click(box.x + box.width * 0.55, box.y + box.height / 2);
    await expect(popup).toHaveAttribute('open', '');
    await expect(page.locator('body')).toHaveCSS('overflow', 'hidden');

    for (let i = 0; i < 12; i += 1) {
        await page.keyboard.press('Tab');
    }
    expect(await page.evaluate(() => document.activeElement?.closest('dialog') !== null)).toBe(true);

    await page.keyboard.press('Escape');
    await expect(popup).not.toHaveAttribute('open', '');
    await expect(page.locator('body')).not.toHaveCSS('overflow', 'hidden');
});
