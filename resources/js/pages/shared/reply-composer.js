const showBanner = (banner) => {
    if (!banner) return;
    banner.classList.remove('hidden');
    banner.classList.add('flex');
};

const hideBanner = (banner) => {
    if (!banner) return;
    banner.classList.remove('flex');
    banner.classList.add('hidden');
};

export const createReplyComposer = ({
    setEditingReplyId,
    replyToInput,
    replyTargetBanner,
    replyTargetText,
    editTargetBanner,
    editTargetText,
    messageInput,
    attachmentInput,
    setAttachmentsEnabled,
    updateAttachmentCount,
    refreshSubmitButtonLabel,
    autoResize,
    focusOnReplyTarget = false,
    onSetReplyTarget,
}) => {
    const clearReplyTarget = () => {
        if (!replyToInput || !replyTargetBanner || !replyTargetText) return;
        replyToInput.value = '';
        replyTargetText.textContent = '';
        hideBanner(replyTargetBanner);
    };

    const clearEditingTarget = (options) => {
        const shouldResetInput = options && options.resetInput;
        setEditingReplyId('');

        hideBanner(editTargetBanner);
        if (editTargetText) {
            editTargetText.textContent = '';
        }

        if (attachmentInput) {
            attachmentInput.value = '';
        }
        setAttachmentsEnabled(true);
        updateAttachmentCount();
        refreshSubmitButtonLabel();

        if (shouldResetInput && messageInput) {
            messageInput.value = '';
            autoResize();
        }
    };

    const setReplyTarget = (replyId, message, metadata = {}) => {
        clearEditingTarget({ resetInput: false });
        if (!replyToInput || !replyTargetBanner || !replyTargetText) return;
        replyToInput.value = String(replyId);
        replyTargetText.textContent = `Replying to: ${(message || '').slice(0, 120)}`;

        if (typeof onSetReplyTarget === 'function') {
            onSetReplyTarget(metadata);
        }

        showBanner(replyTargetBanner);
        if (focusOnReplyTarget && messageInput) {
            messageInput.focus();
        }
    };

    const setEditingTarget = (row) => {
        if (!row || !messageInput || !editTargetBanner || !editTargetText) return;

        const replyId = row.dataset.replyId;
        if (!replyId) return;

        const bubble = row.querySelector('.js-chat-bubble');
        const currentMessage = bubble ? (bubble.dataset.message || '') : '';
        if (!currentMessage.trim()) return;

        clearReplyTarget();
        setEditingReplyId(String(replyId));
        editTargetText.textContent = 'Editing message';
        showBanner(editTargetBanner);

        messageInput.value = currentMessage;
        autoResize();
        messageInput.focus();

        if (attachmentInput) {
            attachmentInput.value = '';
        }
        setAttachmentsEnabled(false);
        updateAttachmentCount();
        refreshSubmitButtonLabel();
    };

    return {
        clearReplyTarget,
        clearEditingTarget,
        setReplyTarget,
        setEditingTarget,
    };
};
