import { escapeHtml } from '../../lib/html-utils';
import { moreActionsMenuHtml, replyReferenceText, replyToMessageButtonHtml } from './ticket-thread-helpers';

const nl2br = (value) => escapeHtml(value).replace(/\n/g, '<br>');

export const buildChatRowWrapperClasses = (isOwnMessage) => `js-chat-row flex ${isOwnMessage ? 'justify-end' : 'justify-start'}`;

/**
 * Renders the shared chat-bubble markup used by both the admin and client
 * ticket-thread views. The two viewers lay out layout/color the same way
 * (driven by `isOwnMessage`), but manage-controls position and visibility
 * follow each viewer's own rules, so those are passed in explicitly rather
 * than re-derived here.
 */
export const buildChatRowHtml = (payload, {
    isOwnMessage,
    alignActionsLeft,
    avatarUrl,
    otherPartyRepliedLabel,
    showInternalBadge = false,
    showMoreActionsTrigger = false,
    attachmentsHtml = '',
}) => {
    const isDeleted = Boolean(payload.deleted);
    const isEdited = Boolean(payload.edited);
    const showEditedBadge = isEdited && !isDeleted;
    const internalBadge = showInternalBadge && payload.is_internal
        ? '<div class="mb-1 flex items-center gap-2"><span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-amber-800">Internal</span></div>'
        : '';
    const replyRefText = replyReferenceText(payload);
    const replyReference = replyRefText
        ? `<div class="js-reply-reference mb-2"><p class="mb-1 flex items-center gap-1 text-xs font-semibold text-slate-500"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11a4 4 0 014 4v5m0 0 3-3m-3 3-3-3M3 10l4-4m-4 4 4 4"/></svg>${payload.from_support ? 'Support replied' : otherPartyRepliedLabel}</p><div class="js-reply-reference-text rounded-full bg-slate-100 px-3 py-1.5 text-xs text-slate-700">${escapeHtml(replyRefText)}</div></div>`
        : '';
    const attachmentsBlock = !isDeleted && attachmentsHtml
        ? `<div class="js-attachments-wrap mt-4 flex flex-wrap gap-2">${attachmentsHtml}</div>`
        : '';
    const stateRow = `<div class="js-state-row mb-1 flex items-center gap-2 ${(showEditedBadge || isDeleted) ? '' : 'hidden'}"><span class="js-edited-badge chat-meta-badge ${showEditedBadge ? '' : 'hidden'}">Edited</span><span class="js-deleted-badge chat-meta-badge chat-meta-badge--deleted ${isDeleted ? '' : 'hidden'}">Deleted</span></div>`;
    const moreActions = showMoreActionsTrigger
        ? moreActionsMenuHtml({ isDeleted, alignLeft: alignActionsLeft })
        : '';

    return (
        `<div class="flex w-full max-w-3xl items-start gap-2 ${isOwnMessage ? 'justify-end' : ''}">` +
            `<div class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white ${isOwnMessage ? 'order-2' : ''}">` +
                `<img src="${escapeHtml(avatarUrl)}" alt="${payload.from_support ? 'Support' : 'Client'} company logo" class="avatar-logo">` +
            '</div>' +
            `<div class="js-chat-bubble relative group w-fit max-w-[82%] rounded-2xl border px-4 py-3 shadow-sm ${isOwnMessage ? 'order-1 border-hoshishi-blue-200 bg-hoshishi-blue-50' : 'border-slate-200 bg-white'}${isDeleted ? ' chat-bubble-deleted' : ''}" data-message="${escapeHtml(payload.message || '')}" data-deleted="${isDeleted ? '1' : '0'}" data-edited="${isEdited ? '1' : '0'}">` +
                stateRow +
                internalBadge +
                replyReference +
                `<p class="js-message-text whitespace-pre-wrap text-sm leading-6 ${isDeleted ? 'italic text-slate-500' : 'text-slate-800'}">${nl2br(payload.message)}</p>` +
                attachmentsBlock +
                `<div class="js-message-actions absolute ${alignActionsLeft ? '-left-[4.75rem]' : '-right-10'} top-1.5 flex items-center gap-1 rounded-full border border-slate-200 bg-white/95 p-1 shadow-sm opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100">` +
                    moreActions +
                    replyToMessageButtonHtml +
                '</div>' +
            '</div>' +
        '</div>'
    );
};
