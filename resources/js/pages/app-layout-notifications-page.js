import { bootPage } from './shared/boot-page';

const initAppLayoutNotificationsPage = () => {
    const notificationList = document.querySelector('.js-header-notification-list');
    const emptyState = document.querySelector('.js-header-notification-empty');
    const badge = document.querySelector('.js-header-notification-badge');
    const clearNotificationsWrap = document.querySelector('.js-header-notification-clear-wrap');
    const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
    let unreadCount = badge ? Number(badge.dataset.count || badge.textContent || 0) : 0;

    if (Number.isNaN(unreadCount) || unreadCount < 0) {
        unreadCount = 0;
    }

    if (!notificationList) {
        return;
    }

    const parseTimestamp = (value) => {
        const parsed = Date.parse(value || '');
        return Number.isNaN(parsed) ? 0 : parsed;
    };

    const removeNotificationItem = (item, delay) => new Promise((resolve) => {
        if (!item) {
            resolve();
            return;
        }

        const startRemoval = () => {
            item.classList.add('is-removing');
            window.setTimeout(() => {
                item.remove();
                resolve();
            }, 210);
        };

        if (!delay) {
            startRemoval();
            return;
        }

        window.setTimeout(startRemoval, delay);
    });

    const syncBadge = () => {
        if (!badge) {
            return;
        }

        if (unreadCount <= 0) {
            badge.remove();
            return;
        }

        badge.textContent = unreadCount > 9 ? '9+' : String(unreadCount);
        badge.dataset.count = String(unreadCount);
    };

    const updateNotificationUi = () => {
        const items = Array.from(notificationList.querySelectorAll('.js-header-notification'));

        if (items.length === 0) {
            notificationList.classList.add('hidden');
            if (emptyState) {
                emptyState.classList.remove('hidden');
            }
            if (clearNotificationsWrap) {
                clearNotificationsWrap.classList.add('hidden');
            }
        } else {
            notificationList.classList.remove('hidden');
            if (emptyState) {
                emptyState.classList.add('hidden');
            }
            if (clearNotificationsWrap) {
                clearNotificationsWrap.classList.remove('hidden');
            }
        }

        syncBadge();
    };

    const clearNotificationItems = () => {
        const items = Array.from(notificationList.querySelectorAll('.js-header-notification'));
        if (items.length === 0) {
            unreadCount = 0;
            updateNotificationUi();
            return Promise.resolve();
        }

        return Promise.all(items.map((item, index) => {
            const delay = Math.min(index * 24, 170);
            return removeNotificationItem(item, delay);
        })).then(() => {
            unreadCount = 0;
            updateNotificationUi();
        });
    };

    const removeNotificationsForSeenEvent = (ticketId, seenAt) => {
        const normalizedTicketId = Number(ticketId || 0);
        const seenTimestamp = parseTimestamp(seenAt);
        if (!normalizedTicketId || !seenTimestamp) {
            return;
        }

        let removedUnreadCount = 0;
        const removals = [];
        notificationList.querySelectorAll('.js-header-notification').forEach((item) => {
            const itemTicketId = Number(item.dataset.ticketId || 0);
            const itemActivityAt = parseTimestamp(item.dataset.activityAt || '');
            if (itemTicketId === normalizedTicketId && itemActivityAt > 0 && itemActivityAt <= seenTimestamp) {
                if (item.dataset.viewed !== '1') {
                    removedUnreadCount += 1;
                }
                removals.push(removeNotificationItem(item, 0));
            }
        });

        if (removedUnreadCount > 0) {
            unreadCount = Math.max(0, unreadCount - removedUnreadCount);
        }

        if (removals.length === 0) {
            updateNotificationUi();
            return;
        }

        Promise.all(removals).then(() => {
            updateNotificationUi();
        });
    };

    window.addEventListener('ticket-notification-seen', (event) => {
        const detail = event && event.detail ? event.detail : {};
        removeNotificationsForSeenEvent(detail.ticketId, detail.seenAt);
    });

    if (clearNotificationsWrap) {
        let allowNativeClearSubmit = false;
        clearNotificationsWrap.addEventListener('submit', (event) => {
            if (allowNativeClearSubmit) {
                return;
            }

            event.preventDefault();
            const clearButton = clearNotificationsWrap.querySelector('button[type="submit"]');
            const clearButtonOriginalText = clearButton ? clearButton.textContent : '';
            if (clearButton) {
                clearButton.disabled = true;
                clearButton.classList.add('app-submit-busy');
                clearButton.textContent = 'Clearing...';
            }

            fetch(clearNotificationsWrap.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfTokenMeta ? (csrfTokenMeta.getAttribute('content') || '') : '',
                },
                credentials: 'same-origin',
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Unable to clear notifications.');
                    }

                    return clearNotificationItems();
                })
                .catch(() => {
                    allowNativeClearSubmit = true;
                    clearNotificationsWrap.submit();
                })
                .finally(() => {
                    if (clearButton) {
                        clearButton.disabled = false;
                        clearButton.classList.remove('app-submit-busy');
                        clearButton.textContent = clearButtonOriginalText;
                    }
                });
        });
    }

    notificationList.addEventListener('submit', (event) => {
        const dismissForm = event.target.closest('form');
        if (!dismissForm || dismissForm === clearNotificationsWrap) {
            return;
        }

        const ticketInput = dismissForm.querySelector('input[name="ticket_id"]');
        const activityInput = dismissForm.querySelector('input[name="activity_at"]');
        if (!ticketInput || !activityInput) {
            return;
        }

        event.preventDefault();

        const row = dismissForm.closest('.js-header-notification');
        const dismissButton = dismissForm.querySelector('button[type="submit"]');
        if (dismissButton) {
            dismissButton.disabled = true;
            dismissButton.classList.add('app-submit-busy');
        }

        fetch(dismissForm.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfTokenMeta ? (csrfTokenMeta.getAttribute('content') || '') : '',
            },
            body: new FormData(dismissForm),
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Unable to dismiss notification.');
                }

                if (row && row.dataset.viewed !== '1') {
                    unreadCount = Math.max(0, unreadCount - 1);
                }

                return removeNotificationItem(row, 0).then(() => {
                    updateNotificationUi();
                });
            })
            .catch(() => {
                dismissForm.submit();
            })
            .finally(() => {
                if (dismissButton) {
                    dismissButton.disabled = false;
                    dismissButton.classList.remove('app-submit-busy');
                }
            });
    });

    updateNotificationUi();
};

bootPage(initAppLayoutNotificationsPage);

