export const EDIT_DELETE_WINDOW_MS = 3 * 60 * 60 * 1000;
export const REPLY_POLL_INTERVAL_MS = 5000;

export const replyToMessageButtonHtml =
    '<button type="button" class="js-reply-msg inline-flex h-7 w-7 items-center justify-center rounded-full bg-hoshishi-accent text-white hover:bg-hoshishi-accent-dark" aria-label="Reply to this message"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11a4 4 0 014 4v5m0 0 3-3m-3 3-3-3M3 10l4-4m-4 4 4 4"/></svg></button>';

export const moreActionsMenuHtml = function ({ isDeleted = false, alignLeft = false }) {
    const menuItems = isDeleted
        ? ''
        : '<button type="button" class="js-edit-msg block w-full px-3 py-2 text-left text-xs font-medium text-slate-700 hover:bg-slate-50" role="menuitem" aria-label="Edit message">Edit</button><button type="button" class="js-delete-msg block w-full px-3 py-2 text-left text-xs font-medium text-red-600 hover:bg-red-50" role="menuitem" aria-label="Delete message">Delete</button>';

    return `<div class="relative"><button type="button" class="js-more-btn inline-flex h-7 w-7 items-center justify-center rounded-full bg-hoshishi-accent text-white hover:bg-hoshishi-accent-dark" aria-label="More actions" aria-haspopup="true" aria-expanded="false"><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/></svg></button><div class="js-more-menu absolute ${alignLeft ? 'left-0' : 'right-0'} z-20 mt-1 hidden min-w-[110px] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg" role="menu">${menuItems}</div></div>`;
};

export const formatAttachmentCountLabel = function ({ disabled = false, fileCount = 0 }) {
    if (disabled) {
        return 'Attachments disabled while editing';
    }

    return fileCount === 0
        ? 'No files selected'
        : fileCount + (fileCount === 1 ? ' file selected' : ' files selected');
};

export const canSubmitReply = function ({ isEditing = false, message = '', attachmentCount = 0 }) {
    const hasMessage = String(message).trim().length > 0;

    if (isEditing) {
        return hasMessage;
    }

    return hasMessage || attachmentCount > 0;
};

export const isWithinReplyEditWindow = function (
    isoDate,
    nowMs = Date.now(),
    windowMs = EDIT_DELETE_WINDOW_MS
) {
    const createdAt = new Date(isoDate);
    if (Number.isNaN(createdAt.getTime())) {
        return false;
    }

    return (nowMs - createdAt.getTime()) <= windowMs;
};

export const nextMessageCountLabel = function (currentLabel) {
    const value = parseInt(String(currentLabel || '').replace(/\D/g, ''), 10);
    if (Number.isNaN(value)) {
        return null;
    }

    const next = value + 1;

    return next + (next === 1 ? ' message' : ' messages');
};

export const buildReplyEndpoint = function (template, replyId) {
    return template ? template.replace('__REPLY__', String(replyId)) : '';
};

export const autoResizeTextarea = function (textarea, maxHeight = 192) {
    if (!textarea) return;

    textarea.style.height = 'auto';
    // scrollHeight leaves out the border; the box is sized border-box, so add it back or the box shrinks a little on first use.
    const borderHeight = textarea.offsetHeight - textarea.clientHeight;
    const nextHeight = Math.min(textarea.scrollHeight + borderHeight, maxHeight);
    textarea.style.height = `${nextHeight}px`;
    textarea.style.overflowY = textarea.scrollHeight > maxHeight ? 'auto' : 'hidden';
};

export const createCloseMenus = function (scopeElement) {
    return () => {
        if (!scopeElement) return;

        scopeElement.querySelectorAll('.js-more-menu').forEach((menu) => {
            menu.classList.add('hidden');
            const btn = menu.parentElement?.querySelector('.js-more-btn');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });
    };
};

/**
 * Shared delete-reply behavior for the admin and client ticket-show pages: same
 * endpoint shape, same CSRF header, same "surface the server's 422 message when
 * present" fallback chain.
 */
export const createDeleteReplyController = function ({
    deleteUrlTemplate,
    csrfToken,
    replyError,
    getThreadView,
    deleteReplySubmit,
    deleteReplyConfirm,
}) {
    const syncDeleteReplySubmitState = () => {
        if (!deleteReplySubmit || !deleteReplyConfirm) return;
        deleteReplySubmit.disabled = !deleteReplyConfirm.checked;
    };

    const deleteReply = async (row) => {
        if (!row) return;

        try {
            const response = await fetch(buildReplyEndpoint(deleteUrlTemplate, row.dataset.replyId), {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                let errorMessage = 'Unable to delete message right now.';
                try {
                    const errorPayload = await response.json();
                    if (errorPayload && errorPayload.message) {
                        errorMessage = errorPayload.message;
                    }
                } catch (parseError) {
                }
                throw new Error(errorMessage);
            }

            const data = await response.json();
            if (data.reply) {
                getThreadView().applyReplyState(row, data.reply);
            }
        } catch (error) {
            if (replyError) {
                replyError.textContent = error && error.message ? error.message : 'Unable to delete message right now.';
                replyError.classList.remove('hidden');
            }
        }
    };

    return { deleteReply, syncDeleteReplySubmitState };
};

export const buildReplyFeedUrl = function (baseUrl, updatedAfter = '') {
    if (!baseUrl) return '';

    const url = new URL(baseUrl, window.location.origin);
    if (updatedAfter) {
        url.searchParams.set('updated_after', updatedAfter);
    }

    return url.toString();
};

export const replyReferenceText = function (reply) {
    return String(reply && reply.reply_to_text ? reply.reply_to_text : '');
};

export const incrementMessageCount = function (messageCountNode) {
    if (!messageCountNode) return;

    const nextLabel = nextMessageCountLabel(messageCountNode.textContent || '');
    if (nextLabel) {
        messageCountNode.textContent = nextLabel;
    }
};

const syncReplyReference = function (reference, reply) {
    if (!reference) return;

    const text = replyReferenceText(reply);
    const referenceTextNode = reference.querySelector('.js-reply-reference-text');
    if (referenceTextNode) {
        referenceTextNode.textContent = text;
    }
    reference.classList.toggle('hidden', text === '');
};

export const applyReplyStateToRow = function ({ row, reply, onDeleted }) {
    const bubble = row ? row.querySelector('.js-chat-bubble') : null;
    if (!bubble || !reply) return;

    bubble.dataset.message = reply.message || '';
    bubble.dataset.deleted = reply.deleted ? '1' : '0';
    bubble.dataset.edited = reply.edited ? '1' : '0';
    bubble.classList.toggle('chat-bubble-deleted', !!reply.deleted);
    const showEditedBadge = !!reply.edited && !reply.deleted;

    const messageText = row.querySelector('.js-message-text');
    if (messageText) {
        messageText.textContent = reply.message || '';
        messageText.classList.toggle('italic', !!reply.deleted);
        messageText.classList.toggle('text-slate-500', !!reply.deleted);
        messageText.classList.toggle('text-slate-800', !reply.deleted);
    }

    const editedBadge = row.querySelector('.js-edited-badge');
    if (editedBadge) editedBadge.classList.toggle('hidden', !showEditedBadge);

    const deletedBadge = row.querySelector('.js-deleted-badge');
    if (deletedBadge) deletedBadge.classList.toggle('hidden', !reply.deleted);

    const stateRow = row.querySelector('.js-state-row');
    if (stateRow) stateRow.classList.toggle('hidden', !(showEditedBadge || reply.deleted));

    syncReplyReference(row.querySelector('.js-reply-reference'), reply);

    const attachmentsWrap = row.querySelector('.js-attachments-wrap');
    if (attachmentsWrap && reply.deleted) {
        attachmentsWrap.remove();
    }

    if (reply.deleted && typeof onDeleted === 'function') {
        onDeleted(row, reply);
    }
};

export const announceNewReply = function (liveRegionNode, reply, fromLabel) {
    if (!liveRegionNode || !reply) return;

    const preview = String(reply.message || '').trim().slice(0, 120);
    liveRegionNode.textContent = preview
        ? `New message from ${fromLabel}: ${preview}`
        : `New message from ${fromLabel}`;
};

export const syncThreadReplies = function ({
    thread,
    replies,
    appendReply,
    applyReplyState,
    onReplyAdded,
}) {
    if (!thread || !Array.isArray(replies)) return;

    replies.forEach(function (reply) {
        const replyId = String((reply && reply.id) || '');
        if (!replyId) return;

        const existingRow = thread.querySelector('.js-chat-row[data-reply-id="' + replyId + '"]');
        if (existingRow) {
            applyReplyState(existingRow, reply);
            return;
        }

        appendReply(reply);
        if (typeof onReplyAdded === 'function') {
            onReplyAdded(reply);
        }
    });
};
