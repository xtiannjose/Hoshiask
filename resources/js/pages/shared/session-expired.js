const DRAFT_KEY_PREFIX = 'helpdesk:reply-draft:';

// 401: not signed in any more. 419: the page's security token no longer matches (session ended).
export const isSessionExpiredStatus = (status) => status === 401 || status === 419;

const getStorage = () => {
    try {
        return window.sessionStorage;
    } catch {
        return null;
    }
};

const draftKey = (path) => `${DRAFT_KEY_PREFIX}${path}`;

export const saveReplyDraft = (text, path = window.location.pathname) => {
    const storage = getStorage();
    if (!storage || !text) return;

    try {
        storage.setItem(draftKey(path), text);
    } catch {
        // Storage full or blocked: the text is still in the box on this page.
    }
};

// Puts back a message saved when the session expired, once, and only into an empty box.
export const restoreReplyDraft = (input, path = window.location.pathname) => {
    const storage = getStorage();
    if (!storage || !input) return false;

    try {
        const draft = storage.getItem(draftKey(path));
        if (!draft) return false;

        storage.removeItem(draftKey(path));
        if (input.value.trim() !== '') return false;

        input.value = draft;
        return true;
    } catch {
        return false;
    }
};

// Tells the user why the reply was not sent, keeps their text, and links back to this ticket:
// opening it while signed out goes to sign-in and returns here afterwards.
export const showSessionExpiredNotice = (errorElement, draftText, ticketUrl = window.location.href) => {
    if (!errorElement) return;

    saveReplyDraft(draftText);

    const link = document.createElement('a');
    link.href = ticketUrl;
    link.className = 'font-semibold underline';
    link.textContent = 'Sign in again';

    errorElement.textContent = '';
    errorElement.append(
        'Your session expired, so your message was not sent. It is saved. ',
        link,
        ' and it will be waiting in the message box.',
    );
    errorElement.classList.remove('hidden');
};
