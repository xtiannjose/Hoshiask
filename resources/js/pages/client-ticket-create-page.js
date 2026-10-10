import { bootPage } from './shared/boot-page';
import { normalizeLeadingUppercase } from '../lib/html-utils';

const initClientTicketCreatePage = () => {
    const pageRoot = document.querySelector('[data-client-ticket-create-page]');
    if (!pageRoot) return;

    const form = document.getElementById('ticket-create-form');
    if (!form) return;

    const fields = [
        document.getElementById('province'),
        document.getElementById('municipality'),
        document.getElementById('subject'),
    ].filter(Boolean);

    const normalizeField = (field) => {
        field.value = normalizeLeadingUppercase(field.value);
    };

    fields.forEach((field) => {
        field.addEventListener('blur', () => {
            normalizeField(field);
        });
    });

    form.addEventListener('submit', () => {
        fields.forEach(normalizeField);
    });
};

bootPage(initClientTicketCreatePage);
