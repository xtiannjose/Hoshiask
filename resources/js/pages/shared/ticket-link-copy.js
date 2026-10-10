const copyTextToClipboard = async (text) => {
    if (globalThis.navigator?.clipboard?.writeText) {
        await globalThis.navigator.clipboard.writeText(text);

        return true;
    }

    throw new Error('Clipboard API is unavailable.');
};

export const copyTicketLink = async (button, {
    copyText = copyTextToClipboard,
    resetDelayMs = 1500,
    setTimeoutFn = globalThis.window?.setTimeout ?? globalThis.setTimeout,
} = {}) => {
    const ticketUrl = button?.dataset?.ticketUrl || '';
    if (!button || ticketUrl === '') {
        return false;
    }

    const originalLabel = button.dataset.copyOriginalLabel || button.textContent || 'Copy link';
    button.dataset.copyOriginalLabel = originalLabel;

    try {
        await copyText(ticketUrl);
        button.textContent = 'Copied';
        button.disabled = true;

        setTimeoutFn(() => {
            button.textContent = originalLabel;
            button.disabled = false;
        }, resetDelayMs);

        return true;
    } catch (error) {
        button.textContent = 'Copy failed';

        setTimeoutFn(() => {
            button.textContent = originalLabel;
        }, resetDelayMs);

        return false;
    }
};
