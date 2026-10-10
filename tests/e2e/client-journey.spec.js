import { expect, test } from '@playwright/test';

// The client portal from a client's side, in a real browser: file a ticket with an attachment, reply, mark it
// resolved with a rating, reopen it, and cancel another ticket. The seeded client comes from E2eSmokeSeeder.

const loginAsSeededClient = async (page) => {
    await page.goto('/login');
    await page.getByLabel('Username or Email').fill('e2e-client@example.com');
    await page.getByLabel('Password', { exact: true }).fill('Playwright123!');
    await page.getByRole('button', { name: 'Sign in' }).click();

    await Promise.race([
        page.waitForURL(/\/client\/dashboard$/, { timeout: 7_000 }),
        page.waitForURL(/\/legal\/acceptance(?:\?.*)?$/, { timeout: 7_000 }),
    ]);

    if (page.url().includes('/legal/acceptance')) {
        await page.getByLabel('I agree to the Terms of Service.').check();
        await page.getByLabel('I acknowledge and consent to the Privacy Notice.').check();
        await page.getByLabel('I confirm I am authorized to use this platform and provide data needed for support.').check();
        await page.getByRole('button', { name: 'Accept and Continue' }).click();
    }

    await expect(page).toHaveURL(/\/client\/dashboard$/);
};

// The page's own script attaches the button handlers a moment after the page paints (app.js sets this marker when
// it is done), so a click that comes earlier is lost.
const waitForPageScripts = (page) => expect(page.locator('html')).toHaveAttribute('data-page-scripts-ready', 'true');

// Files a ticket and returns its subject. Province and city are plain text boxes with suggestions.
const fileTicket = async (page, subject, { withAttachment = false } = {}) => {
    await page.goto('/client/tickets/create');
    await page.locator('#name').fill('E2E Client');
    await page.locator('#contact_number').fill('09180000999');
    await page.locator('#email').fill('e2e-client@example.com');
    await page.locator('#province').fill('Metro Manila');
    await page.locator('#municipality').fill('Pasig City');
    await page.locator('#subject').fill(subject);
    // The category list is a styled dropdown over a hidden native select; the native select is what the form sends.
    await page.locator('#category_id').selectOption({ label: 'E2E Category' }, { force: true });
    await page.locator('#description').fill('The printer in the field office jams on every second page.');
    if (withAttachment) {
        await page.locator('#attachments').setInputFiles({
            name: 'jam-report.txt',
            mimeType: 'text/plain',
            buffer: Buffer.from('Paper jam on tray 2, photographed on the 3rd.'),
        });
    }
    await page.locator('#ticket_consent').check();
    await page.getByRole('button', { name: 'Create Ticket' }).click();
    await expect(page.getByText(subject).first()).toBeVisible();
    await waitForPageScripts(page);

    return subject;
};

test('a client files a ticket with a file, replies, resolves it with a rating and reopens it', async ({ page }) => {
    const subject = `E2E journey ${Date.now()}`;
    await loginAsSeededClient(page);
    await fileTicket(page, subject, { withAttachment: true });

    // On the ticket page the attachment is listed and the conversation is there.
    await expect(page).toHaveURL(/\/client\/tickets\/\d+/);
    await expect(page.getByText('jam-report.txt').first()).toBeVisible();

    // Reply.
    const reply = `Any update? ${Date.now()}`;
    await page.locator('#message').fill(reply);
    await page.locator('#send-reply-btn').click();
    await expect(page.locator('#conversation-thread').getByText(reply)).toBeVisible();

    // Mark as resolved: the button stays disabled until the box is ticked and a rating is chosen.
    await page.locator('#open-resolve-ticket-modal').click();
    const resolveDialog = page.locator('dialog#resolve-ticket-modal');
    await expect(resolveDialog).toBeVisible();
    await expect(page.locator('#resolve-confirm-submit')).toBeDisabled();
    await resolveDialog.locator('label[for="resolve-rating-5"]').click();
    await page.locator('#resolve_confirm_checkbox').check();
    await expect(page.locator('#resolve-confirm-submit')).toBeEnabled();
    await page.locator('#resolve-confirm-submit').click();

    // Resolved: Reopen replaces Mark as Resolved, and the rating is shown.
    await expect(page.locator('#open-reopen-ticket-modal')).toBeVisible();
    await expect(page.locator('#open-resolve-ticket-modal')).toHaveCount(0);

    // Reopen needs a reason.
    await page.locator('#open-reopen-ticket-modal').click();
    const reopenDialog = page.locator('dialog#reopen-ticket-modal');
    await expect(reopenDialog).toBeVisible();
    await page.locator('#reopen_reason').fill('The jam came back after one day.');
    await reopenDialog.getByRole('button', { name: 'Reopen Ticket' }).click();

    await expect(page.locator('#open-resolve-ticket-modal')).toBeVisible();
    await expect(page.locator('#open-reopen-ticket-modal')).toHaveCount(0);
});

test('a low rating needs a comment before a ticket can be marked resolved, a good rating does not', async ({ page }) => {
    await loginAsSeededClient(page);
    await fileTicket(page, `E2E rating ${Date.now()}`);

    await page.locator('#open-resolve-ticket-modal').click();
    const resolveDialog = page.locator('dialog#resolve-ticket-modal');
    await expect(resolveDialog).toBeVisible();
    await page.locator('#resolve_confirm_checkbox').check();

    // 1 star: the comment is required, so Confirm waits for it.
    await resolveDialog.locator('label[for="resolve-rating-1"]').click();
    await expect(page.locator('#resolve-confirm-submit')).toBeDisabled();
    await page.locator('#resolve_comment').fill('It took far too long.');
    await expect(page.locator('#resolve-confirm-submit')).toBeEnabled();
    await page.locator('#resolve_comment').fill('');
    await expect(page.locator('#resolve-confirm-submit')).toBeDisabled();

    // 4 stars: the comment is optional.
    await resolveDialog.locator('label[for="resolve-rating-4"]').click();
    await expect(page.locator('#resolve-confirm-submit')).toBeEnabled();
});

test('a client can cancel an open ticket only with a reason, and it can then no longer be changed', async ({ page }) => {
    const subject = `E2E cancel ${Date.now()}`;
    await loginAsSeededClient(page);
    await fileTicket(page, subject);

    await page.locator('#open-cancel-ticket-modal').click();
    const cancelDialog = page.locator('dialog#cancel-ticket-modal');
    await expect(cancelDialog).toBeVisible();

    // Esc closes the dialog without cancelling anything.
    await page.keyboard.press('Escape');
    await expect(cancelDialog).toBeHidden();
    await expect(page.locator('#open-cancel-ticket-modal')).toBeVisible();

    // Without a reason the form is refused in the browser; with one the ticket is cancelled.
    await page.locator('#open-cancel-ticket-modal').click();
    await cancelDialog.getByRole('button', { name: 'Cancel Ticket' }).click();
    await expect(cancelDialog).toBeVisible();
    await page.locator('#cancel_reason').fill('Fixed it myself.');
    await cancelDialog.getByRole('button', { name: 'Cancel Ticket' }).click();

    await expect(page.getByText('This ticket was cancelled and can no longer be updated.')).toBeVisible();
    await expect(page.locator('#open-cancel-ticket-modal')).toHaveCount(0);
    await expect(page.locator('#open-resolve-ticket-modal')).toHaveCount(0);
});

test('a client cannot open staff pages', async ({ page }) => {
    await loginAsSeededClient(page);

    const response = await page.goto('/admin/tickets');
    expect(response?.status()).toBe(403);
});
