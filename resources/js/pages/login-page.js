import { attachPasswordPeek } from '../lib/password-peek';
import { bootPage } from './shared/boot-page';

const initLoginPage = () => {
    const pageRoot = document.querySelector('[data-login-page]');
    if (!pageRoot) return;

    const input = pageRoot.querySelector('#password');
    const toggle = pageRoot.querySelector('[data-login-show-password]');
    if (!input || !toggle) return;

    const peek = attachPasswordPeek({
        input,
        toggle,
        eyeOpen: toggle.querySelector('[data-eye-open]'),
        eyeClosed: toggle.querySelector('[data-eye-closed]'),
    });

    // Never send the form, or leave the page, with the password showing.
    input.form?.addEventListener('submit', peek.hide);
    window.addEventListener('pagehide', peek.hide);
};

bootPage(initLoginPage);
