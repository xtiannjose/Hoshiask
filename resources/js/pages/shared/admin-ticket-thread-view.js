import { createAttachmentLink } from './ticket-thread-attachments';
import { applyReplyStateToRow, incrementMessageCount } from './ticket-thread-helpers';
import { appendThreadSeparatorIfNeeded } from './ticket-thread-time';
import { buildChatRowHtml, buildChatRowWrapperClasses } from './ticket-thread-row';

export const createAdminTicketThreadView = ({
    thread,
    supportLogo,
    defaultClientLogo,
    messageCountNode,
    queueSeenSync,
    getEditingReplyId,
    clearEditingTarget,
}) => {
    const appendReply = (payload) => {
        if (!thread || !payload) return;

        appendThreadSeparatorIfNeeded(thread, payload.created_at_iso, 15);

        const fromSupport = Boolean(payload.from_support);
        const canManage = Boolean(payload.can_manage);
        const isDeleted = Boolean(payload.deleted);
        const attachmentsHtml = !isDeleted && Array.isArray(payload.attachments) && payload.attachments.length
            ? payload.attachments.map((attachment) => createAttachmentLink(attachment).outerHTML).join('')
            : '';

        const row = document.createElement('div');
        row.className = buildChatRowWrapperClasses(fromSupport);
        row.dataset.createdAt = payload.created_at_iso;
        row.dataset.replyId = payload.id;
        row.dataset.canManage = canManage ? '1' : '0';
        row.dataset.isInternal = payload.is_internal ? '1' : '0';
        row.innerHTML = buildChatRowHtml(payload, {
            isOwnMessage: fromSupport,
            alignActionsLeft: fromSupport,
            avatarUrl: payload.avatar_logo || (fromSupport ? supportLogo : defaultClientLogo),
            otherPartyRepliedLabel: 'Client replied',
            showInternalBadge: true,
            showMoreActionsTrigger: canManage,
            attachmentsHtml,
        });

        thread.appendChild(row);
        thread.scrollTop = thread.scrollHeight;
        queueSeenSync(payload.created_at_iso || '');
        incrementMessageCount(messageCountNode);
    };

    const applyReplyState = (row, reply) => {
        applyReplyStateToRow({
            row,
            reply,
            onDeleted: (currentRow) => {
                currentRow.querySelectorAll('.js-edit-msg, .js-delete-msg').forEach((btn) => {
                    btn.remove();
                });

                if (getEditingReplyId() && String(currentRow.dataset.replyId) === String(getEditingReplyId())) {
                    clearEditingTarget({ resetInput: true });
                }
            },
        });
    };

    return {
        appendReply,
        applyReplyState,
    };
};
