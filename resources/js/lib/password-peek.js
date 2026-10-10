// Shows a password for a short moment, then hides it again by itself, so it is never left
// visible by accident. Clicking the eye while it is showing hides it straight away.

export const PEEK_DURATION_MS = 1500;

export const attachPasswordPeek = ({
    input,
    toggle,
    eyeOpen = null,
    eyeClosed = null,
    durationMs = PEEK_DURATION_MS,
    setTimer = (callback, ms) => window.setTimeout(callback, ms),
    clearTimer = (id) => window.clearTimeout(id),
}) => {
    let timer = null;

    const render = (revealed) => {
        const label = revealed ? 'Hide password' : 'Show password';

        input.type = revealed ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', revealed ? 'true' : 'false');
        toggle.setAttribute('aria-label', label);
        toggle.setAttribute('title', label);
        if (eyeOpen) eyeOpen.classList.toggle('hidden', revealed);
        if (eyeClosed) eyeClosed.classList.toggle('hidden', !revealed);
    };

    const hide = () => {
        if (timer !== null) {
            clearTimer(timer);
            timer = null;
        }
        render(false);
    };

    const reveal = () => {
        if (timer !== null) clearTimer(timer);
        render(true);
        timer = setTimer(hide, durationMs);
    };

    toggle.addEventListener('click', () => {
        if (input.type === 'password') {
            reveal();
        } else {
            hide();
        }
    });

    return { hide, reveal };
};
