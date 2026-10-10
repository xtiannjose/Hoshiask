// Knowledge base rows open the article in a popup on top of the list instead of going to another page.
// The rows stay real links to the article page, so middle-click, "open in new tab" and a page without
// JavaScript keep working.

/** A normal left click: not a new-tab or new-window gesture. */
export const isPlainPrimaryClick = (event) => (
    event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey
);

/**
 * Copies the article's content into the popup and opens it.
 *
 * @returns {boolean} false when there is no content for this article (the link then works as a normal link).
 */
export const showArticle = ({ template, content, controller }) => {
    if (!template || !template.content) return false;

    content.replaceChildren(template.content.cloneNode(true));
    content.scrollTop = 0;
    controller.open();

    return true;
};

export const registerKnowledgeBaseModal = () => {
    const modal = document.getElementById('kb-article-modal');
    const content = modal ? modal.querySelector('[data-kb-modal-content]') : null;
    if (!modal || !content || !window.ModalKit) return;

    // The modal kit closes on the X, on a click outside the panel and on Escape, and puts focus back on the row.
    const controller = window.ModalKit.bind(modal, {
        onClose: () => content.replaceChildren(),
    });
    if (!controller) return;

    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[data-kb-article]') : null;
        if (!link || event.defaultPrevented || !isPlainPrimaryClick(event)) return;

        const template = document.getElementById(link.dataset.kbArticle || '');
        if (showArticle({ template: template instanceof HTMLTemplateElement ? template : null, content, controller })) {
            event.preventDefault();
        }
    });
};
