import { registerConfirmForms } from './confirm-forms';
export const registerFormBehaviors = () => {
    registerConfirmForms();

    document.querySelectorAll('form[data-checkbox-confirm-form]').forEach((form) => {
        const checkbox = form.querySelector('[data-checkbox-confirm]');
        const submitter = form.querySelector('[data-checkbox-confirm-submit]');

        if (!(checkbox instanceof HTMLInputElement) || !(submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement)) {
            return;
        }

        const syncSubmitState = () => {
            submitter.disabled = !checkbox.checked;
        };

        checkbox.addEventListener('change', syncSubmitState);
        syncSubmitState();
    });

    const formNodes = document.querySelectorAll('form[data-submit-feedback]');
    formNodes.forEach((form) => {
        form.addEventListener('submit', (event) => {
            const submitter = event.submitter
                || form.querySelector('button[type="submit"], input[type="submit"]');
            if (!submitter || submitter.disabled) return;

            requestAnimationFrame(() => {
                if (event.defaultPrevented || submitter.disabled) return;

                const loadingText = submitter.dataset.loadingText || 'Loading...';
                const originalText = submitter.tagName === 'INPUT'
                    ? (submitter.value || '')
                    : (submitter.textContent || '');
                submitter.dataset.originalText = originalText;
                submitter.disabled = true;
                submitter.classList.add('app-submit-busy');

                if (submitter.tagName === 'INPUT') {
                    submitter.value = loadingText;
                } else {
                    submitter.textContent = loadingText;
                }
            });
        });
    });

    // After a failed submit the page reloads at the top: bring the list of problems into view
    // and to the screen reader's attention.
    const errorSummary = document.querySelector('[data-error-summary]');
    if (errorSummary instanceof HTMLElement) {
        errorSummary.focus();
        errorSummary.scrollIntoView({ block: 'center' });
    }

    document.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement || target instanceof HTMLSelectElement || target instanceof HTMLTextAreaElement)) {
            return;
        }
        if (target.dataset.autoSubmitChange === undefined) return;

        const form = target.form || target.closest('form');
        if (!form) return;

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }

        form.submit();
    });
};
