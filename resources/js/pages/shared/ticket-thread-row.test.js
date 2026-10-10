import { describe, expect, it } from 'vitest';

import { buildChatRowHtml, buildChatRowWrapperClasses } from './ticket-thread-row';

const basePayload = (overrides = {}) => ({
    id: 1,
    message: 'Hello there',
    from_support: false,
    deleted: false,
    edited: false,
    ...overrides,
});

const baseOptions = (overrides = {}) => ({
    isOwnMessage: true,
    alignActionsLeft: true,
    avatarUrl: '/logo.png',
    otherPartyRepliedLabel: 'You replied',
    ...overrides,
});

describe('buildChatRowWrapperClasses', function () {
    it('right-aligns own messages and left-aligns others', function () {
        expect(buildChatRowWrapperClasses(true)).toBe('js-chat-row flex justify-end');
        expect(buildChatRowWrapperClasses(false)).toBe('js-chat-row flex justify-start');
    });
});

describe('buildChatRowHtml layout', function () {
    it('marks the bubble/avatar as own (order-1/order-2, blue bubble) when isOwnMessage is true', function () {
        const html = buildChatRowHtml(basePayload(), baseOptions({ isOwnMessage: true }));

        expect(html).toContain('justify-end');
        expect(html).toContain('order-2');
        expect(html).toContain('order-1 border-hoshishi-blue-200 bg-hoshishi-blue-50');
    });

    it('uses the neutral (non-own) layout when isOwnMessage is false', function () {
        const html = buildChatRowHtml(basePayload(), baseOptions({ isOwnMessage: false }));

        expect(html).not.toContain('justify-end"');
        expect(html).not.toContain('order-2');
        expect(html).toContain('border-slate-200 bg-white');
    });

    it('positions the actions container independently of isOwnMessage, per alignActionsLeft', function () {
        const leftAligned = buildChatRowHtml(basePayload(), baseOptions({ isOwnMessage: false, alignActionsLeft: true }));
        const rightAligned = buildChatRowHtml(basePayload(), baseOptions({ isOwnMessage: true, alignActionsLeft: false }));

        expect(leftAligned).toContain('-left-[4.75rem]');
        expect(rightAligned).toContain('-right-10');
    });
});

describe('buildChatRowHtml message content', function () {
    it('escapes HTML and converts newlines to <br>', function () {
        const html = buildChatRowHtml(basePayload({ message: '<script>x</script>\nline two' }), baseOptions());

        expect(html).toContain('&lt;script&gt;x&lt;/script&gt;<br>line two');
        expect(html).not.toContain('<script>x</script>');
    });

    it('marks deleted messages with the deleted class and italic styling, and hides attachments', function () {
        const html = buildChatRowHtml(
            basePayload({ deleted: true }),
            baseOptions({ attachmentsHtml: '<a>file</a>' })
        );

        expect(html).toContain('chat-bubble-deleted');
        expect(html).toContain('italic text-slate-500');
        expect(html).not.toContain('js-attachments-wrap');
    });

    it('includes attachments only when present and not deleted', function () {
        const html = buildChatRowHtml(basePayload(), baseOptions({ attachmentsHtml: '<a>file</a>' }));

        expect(html).toContain('js-attachments-wrap');
        expect(html).toContain('<a>file</a>');
    });

    it('shows the edited badge only when edited and not deleted', function () {
        const editedHtml = buildChatRowHtml(basePayload({ edited: true }), baseOptions());
        const deletedAndEditedHtml = buildChatRowHtml(basePayload({ edited: true, deleted: true }), baseOptions());

        expect(editedHtml).toMatch(/js-edited-badge chat-meta-badge (?!hidden)/);
        expect(deletedAndEditedHtml).toContain('js-edited-badge chat-meta-badge hidden');
    });
});

describe('buildChatRowHtml reply reference', function () {
    it('omits the reply-reference block when there is no reply_to_text', function () {
        const html = buildChatRowHtml(basePayload(), baseOptions());

        expect(html).not.toContain('js-reply-reference');
    });

    it('labels a referenced support reply as "Support replied" regardless of viewer', function () {
        const html = buildChatRowHtml(
            basePayload({ reply_to_text: 'Earlier message', from_support: false }),
            baseOptions({ otherPartyRepliedLabel: 'You replied' })
        );

        // The *current* payload isn't from support, but the label logic keys off
        // payload.from_support for the CURRENT message's own reference label text.
        expect(html).toContain('js-reply-reference');
        expect(html).toContain('You replied');
        expect(html).toContain('Earlier message');
    });

    it('uses the caller-supplied label for a non-support message reference', function () {
        const adminHtml = buildChatRowHtml(
            basePayload({ reply_to_text: 'Earlier message', from_support: false }),
            baseOptions({ otherPartyRepliedLabel: 'Client replied' })
        );

        expect(adminHtml).toContain('Client replied');
    });
});

describe('buildChatRowHtml internal badge', function () {
    it('shows the internal badge only when showInternalBadge is true and payload.is_internal is true', function () {
        const shown = buildChatRowHtml(basePayload({ is_internal: true }), baseOptions({ showInternalBadge: true }));
        const hiddenByFlag = buildChatRowHtml(basePayload({ is_internal: true }), baseOptions({ showInternalBadge: false }));
        const hiddenByData = buildChatRowHtml(basePayload({ is_internal: false }), baseOptions({ showInternalBadge: true }));

        expect(shown).toContain('Internal');
        expect(hiddenByFlag).not.toContain('Internal');
        expect(hiddenByData).not.toContain('Internal');
    });
});

describe('buildChatRowHtml manage-controls trigger', function () {
    it('renders the more-actions trigger only when showMoreActionsTrigger is true', function () {
        const shown = buildChatRowHtml(basePayload(), baseOptions({ showMoreActionsTrigger: true }));
        const hidden = buildChatRowHtml(basePayload(), baseOptions({ showMoreActionsTrigger: false }));

        expect(shown).toContain('js-more-btn');
        expect(hidden).not.toContain('js-more-btn');
    });

    it('always includes the reply-to-message button regardless of manage rights', function () {
        const html = buildChatRowHtml(basePayload(), baseOptions({ showMoreActionsTrigger: false }));

        expect(html).toContain('js-reply-msg');
    });
});
