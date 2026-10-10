// Only one popup is open at a time: notifications, the account menu, recent searches, a styled dropdown,
// a message's "more" menu, the canned-response picker.
//
// A popup calls announcePopupOpened('its-name') when it opens. Every other popup listens with onPopupOpened()
// and closes itself when the name is not its own. This is needed because the header buttons stop their click
// from travelling further (so their own "click away" does not close them straight away), which also stops the
// other popups from seeing the click.

const EVENT_NAME = 'app:popup-opened';

export const announcePopupOpened = (source, bus = window) => {
    bus.dispatchEvent(new CustomEvent(EVENT_NAME, { detail: { source } }));
};

/**
 * @param {(source: string) => void} handler Called with the name of the popup that just opened.
 * @returns {() => void} Stops listening.
 */
export const onPopupOpened = (handler, bus = window) => {
    const listener = (event) => handler(String(event.detail?.source ?? ''));
    bus.addEventListener(EVENT_NAME, listener);

    return () => bus.removeEventListener(EVENT_NAME, listener);
};
