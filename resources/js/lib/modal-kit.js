// Modals are native <dialog class="app-modal-root"> elements. showModal() gives the focus trap, Esc,
// inert page behind, top layer (so no hoisting out of transformed parents) and focus return on close.
// This kit only adds the fade (is-open / is-closing classes) and the open/close hooks.
export const registerModalKit = () => {
    const resolveTransitionDuration = () => {
        const durationToken = window.getComputedStyle(document.documentElement)
            .getPropertyValue('--motion-duration-base')
            .trim();
        const durationMatch = durationToken.match(/^([\d.]+)\s*(ms|s)?$/i);

        if (!durationMatch) return 220;

        const durationValue = Number.parseFloat(durationMatch[1]);
        if (!Number.isFinite(durationValue) || durationValue <= 0) return 220;

        const unit = (durationMatch[2] || 'ms').toLowerCase();
        return unit === 's' ? durationValue * 1000 : durationValue;
    };

    const toNode = (value) => {
        if (!value) return null;
        if (value instanceof Element) return value;
        if (typeof value === 'string') return document.querySelector(value);
        return null;
    };

    const toNodes = (items) => {
        if (!Array.isArray(items)) return [];
        return items.map((item) => toNode(item)).filter(Boolean);
    };

    const CLOSE_TRIGGER_SELECTOR = '[data-modal-close], [data-modal-overlay], [data-close-ticket-overlay], [data-resolve-ticket-overlay]';

    const bind = (modalRef, options = {}) => {
        const modal = toNode(modalRef);
        if (!modal) return null;

        const closeButtons = toNodes(options.closeButtons || []);
        const openButtons = toNodes(options.openButtons || []);
        const transitionDuration = resolveTransitionDuration();
        let closeTimer = null;

        const isOpen = () => modal.open;
        const clearCloseTimer = () => {
            if (closeTimer !== null) {
                window.clearTimeout(closeTimer);
                closeTimer = null;
            }
        };

        const open = () => {
            clearCloseTimer();
            modal.classList.remove('is-closing');
            if (!modal.open) modal.showModal();
            requestAnimationFrame(() => modal.classList.add('is-open'));

            if (typeof options.onOpen === 'function') options.onOpen();
        };

        const close = () => {
            clearCloseTimer();
            modal.classList.remove('is-open');
            modal.classList.add('is-closing');
            closeTimer = window.setTimeout(() => modal.close(), transitionDuration);
        };

        // Runs for every way the dialog closes: close(), or the browser closing it itself.
        const closedHandler = () => {
            clearCloseTimer();
            modal.classList.remove('is-open', 'is-closing');

            if (typeof options.onClose === 'function') options.onClose();
        };

        // Esc: run the fade-out instead of the instant native close.
        const cancelHandler = (event) => {
            event.preventDefault();
            close();
        };

        const clickHandler = (event) => {
            if (event.target.closest(CLOSE_TRIGGER_SELECTOR)) close();
        };

        modal.addEventListener('click', clickHandler);
        modal.addEventListener('cancel', cancelHandler);
        modal.addEventListener('close', closedHandler);

        openButtons.forEach((button) => button.addEventListener('click', open));
        closeButtons.forEach((button) => button.addEventListener('click', close));

        const destroy = () => {
            clearCloseTimer();
            modal.removeEventListener('click', clickHandler);
            modal.removeEventListener('cancel', cancelHandler);
            modal.removeEventListener('close', closedHandler);
        };

        return { modal, open, close, isOpen, destroy };
    };

    const bindById = (modalId, options = {}) => bind(document.getElementById(modalId), options);

    const bindAttachmentPreview = (options = {}) => {
        const modal = toNode(options.modal);
        const title = toNode(options.title);
        const image = toNode(options.image);
        const frame = toNode(options.frame);
        const closeButton = toNode(options.closeButton);
        const triggerSelector = options.triggerSelector || '.js-attachment-link';

        if (!modal || !image || !frame) return null;

        const controller = bind(modal, {
            closeButtons: closeButton ? [closeButton] : [],
            onClose: () => {
                image.classList.add('hidden');
                frame.classList.add('hidden');
                image.removeAttribute('src');
                frame.removeAttribute('src');
            },
        });

        const open = ({ url, fileName, mimeType }) => {
            if (title) title.textContent = fileName || 'Attachment Preview';

            if (mimeType && mimeType.startsWith('image/')) {
                image.src = url;
                image.classList.remove('hidden');
                frame.classList.add('hidden');
            } else {
                frame.src = url;
                frame.classList.remove('hidden');
                image.classList.add('hidden');
            }

            controller.open();
        };

        const openFromLink = (link) => {
            if (!link) return;
            open({
                url: link.dataset.fileUrl,
                fileName: link.dataset.fileName,
                mimeType: link.dataset.fileMime,
            });
        };

        if (triggerSelector) {
            document.addEventListener('click', (event) => {
                const link = event.target.closest(triggerSelector);
                if (!link) return;
                event.preventDefault();
                openFromLink(link);
            });
        }

        return { ...controller, open, openFromLink };
    };

    window.ModalKit = { bind, bindById, bindAttachmentPreview };
};
