// Asks "are you sure?" before a form is sent, for forms marked data-confirm="Question". This replaces an
// inline onsubmit="return confirm(...)", which the live site's security policy blocks (so the question was
// never asked and the form went straight through).

export const shouldBlockSubmit = (form, confirmFn = (message) => window.confirm(message)) => {
    const message = form && form.dataset ? form.dataset.confirm : undefined;
    if (!message) return false;

    return !confirmFn(message);
};

export const registerConfirmForms = () => {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (form instanceof HTMLFormElement && shouldBlockSubmit(form)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);
};
