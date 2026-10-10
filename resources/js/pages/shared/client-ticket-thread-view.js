import { createAttachmentLink } from './ticket-thread-attachments';
import { applyReplyStateToRow, incrementMessageCount } from './ticket-thread-helpers';
import { createThreadTimeSeparator, shouldInsertTimeSeparator } from './ticket-thread-time';
import { buildChatRowHtml, buildChatRowWrapperClasses } from './ticket-thread-row';

export const createClientTicketThreadView = ({
    thread,
    clientLogo,
    supportLogo,
    messageCount,
    queueSeenSync,
    getEditingReplyId,
    clearEditingTarget,
    isWithinEditDeleteWindow,
    timestampBreakMinutes = 15,
}) => {
    const renderTimeSeparators = () => {
        if (!thread) return;

        thread.querySelectorAll('.js-time-separator').forEach((el) => {
            el.remove();
        });

        const rows = Array.from(thread.querySelectorAll('.js-chat-row'));
        let lastCreatedAtIso = '';

        rows.forEach((row) => {
            const createdAt = row.dataset.createdAt;
            if (!createdAt) return;

            if (!lastCreatedAtIso || shouldInsertTimeSeparator(lastCreatedAtIso, createdAt, timestampBreakMinutes)) {
                const separator = createThreadTimeSeparator(createdAt);
                if (separator) {
                    row.parentNode.insertBefore(separator, row);
                }
            }

            lastCreatedAtIso = createdAt;
        });
    };

    const appendReplyToThread = (reply) => {
        if (!thread || !reply) return;

        const fromSupport = Boolean(reply.from_support);
        const canManage = Boolean(reply.can_manage);
        const createdAt = reply.created_at_iso || new Date().toISOString();
        const replyId = String(reply.id || '');
        const isDeleted = Boolean(reply.deleted);
        const isOwnMessage = !fromSupport;
        const hasManageRights = isOwnMessage && canManage;
        const attachmentsHtml = !isDeleted && Array.isArray(reply.attachments) && reply.attachments.length > 0
            ? reply.attachments.map((attachment) => createAttachmentLink(attachment, { borderClass: 'border-hoshishi-blue-200' }).outerHTML).join('')
            : '';

        const wrap = document.createElement('div');
        wrap.className = buildChatRowWrapperClasses(isOwnMessage);
        wrap.dataset.createdAt = createdAt;
        wrap.dataset.replyId = replyId;
        wrap.dataset.isSupport = fromSupport ? '1' : '0';
        wrap.dataset.canManage = canManage ? '1' : '0';
        wrap.innerHTML = buildChatRowHtml(reply, {
            isOwnMessage,
            alignActionsLeft: hasManageRights,
            avatarUrl: reply.avatar_logo || (fromSupport ? supportLogo : clientLogo),
            otherPartyRepliedLabel: 'You replied',
            showInternalBadge: false,
            showMoreActionsTrigger: hasManageRights && !isDeleted && isWithinEditDeleteWindow(createdAt),
            attachmentsHtml,
        });

        thread.appendChild(wrap);
        renderTimeSeparators();
        thread.scrollTop = thread.scrollHeight;
        queueSeenSync(createdAt);
    };

    const applyReplyState = (row, reply) => {
        applyReplyStateToRow({
            row,
            reply,
            onDeleted: (currentRow) => {
                const menu = currentRow.querySelector('.js-more-menu');
                if (menu) menu.innerHTML = '';

                if (getEditingReplyId() && String(currentRow.dataset.replyId) === String(getEditingReplyId())) {
                    clearEditingTarget({ resetInput: true });
                }
            },
        });
    };

    const appendReplyAndIncrement = (reply) => {
        appendReplyToThread(reply);
        incrementMessageCount(messageCount);
    };

    return {
        appendReplyAndIncrement,
        applyReplyState,
        renderTimeSeparators,
    };
};
