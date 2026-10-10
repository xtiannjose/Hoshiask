import { bootPage } from './shared/boot-page';

const initAccountSettingsPage = () => {
    const pageRoot = document.querySelector('[data-account-settings-page]');
    if (!pageRoot) return;

    const peekButtons = pageRoot.querySelectorAll('[data-password-peek]');

    peekButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const selector = button.getAttribute('data-password-peek');
            if (!selector) return;

            const input = pageRoot.querySelector(selector) || document.querySelector(selector);
            if (!input || input.type !== 'password') return;

            input.type = 'text';

            window.setTimeout(() => {
                input.type = 'password';
            }, 1000);
        });
    });
};

bootPage(initAccountSettingsPage);

