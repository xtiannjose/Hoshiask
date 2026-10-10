import { escapeHtml } from './html-utils.js';
import { announcePopupOpened, onPopupOpened } from './exclusive-popups.js';

export const registerSearchHistory = () => {
    const storagePrefix = 'hoshishi_recent_searches_';
    const maxHistoryItems = 5;
    const panelTransitionMs = 170;

    const normalizeTerm = (value) => value.trim().replace(/\s+/g, ' ');
    const buildKeySegment = (value) => String(value || '')
        .toLowerCase()
        .replace(/[^a-z0-9_-]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 80);

    const resolveSearchForms = () => {
        const explicitForms = Array.from(document.querySelectorAll('form[data-search-history-form]'));
        const inferredForms = Array.from(document.querySelectorAll('form')).filter((form) => (
            form.dataset.searchHistoryDisabled === undefined
            && form.querySelector('input[name="search"]:not([type="hidden"])')
        ));
        const merged = [];
        [...explicitForms, ...inferredForms].forEach((form) => {
            if (!merged.includes(form)) {
                merged.push(form);
            }
        });
        return merged;
    };

    const readHistory = (form) => {
        try {
            const raw = window.localStorage.getItem(form.dataset.searchHistoryStorageKey || '');
            const parsed = raw ? JSON.parse(raw) : [];
            if (!Array.isArray(parsed)) return [];
            return parsed
                .map((item) => normalizeTerm(String(item)))
                .filter((item) => item.length > 0)
                .slice(0, maxHistoryItems);
        } catch (error) {
            return [];
        }
    };

    const writeHistory = (form, items) => {
        const normalized = items
            .map((item) => normalizeTerm(String(item)))
            .filter((item) => item.length > 0)
            .slice(0, maxHistoryItems);
        try {
            window.localStorage.setItem(form.dataset.searchHistoryStorageKey || '', JSON.stringify(normalized));
        } catch (error) {
            if (typeof console !== 'undefined' && typeof console.warn === 'function') {
                console.warn('Failed to persist search history.', error);
            }
        }
        return normalized;
    };

    const searchForms = resolveSearchForms();
    if (searchForms.length === 0) return;
    const panelControllers = [];

    searchForms.forEach((form, formIndex) => {
        const popupName = `search-history-${formIndex}`;
        const input = form.querySelector('[data-search-history-input]')
            || form.querySelector('input[name="search"]:not([type="hidden"])');
        if (!input) return;

        input.dataset.searchHistoryInput = '1';
        input.setAttribute('autocomplete', 'off');

        let panel = form.querySelector('[data-search-history-panel]');
        if (!panel) {
            const anchor = input.closest('[data-search-history-anchor]') || input.parentElement;
            if (!anchor) return;

            const anchorStyle = window.getComputedStyle(anchor);
            if (anchorStyle.position === 'static') {
                anchor.style.position = 'relative';
            }

            panel = document.createElement('div');
            panel.className = 'search-history-panel hidden';
            panel.dataset.searchHistoryPanel = '1';
            anchor.appendChild(panel);
        }

        const configuredKey = form.dataset.searchHistoryKey;
        if (!configuredKey) {
            const actionSegment = buildKeySegment(form.getAttribute('action') || window.location.pathname || 'current');
            const pageSegment = buildKeySegment(window.location.pathname || 'app');
            const fieldSegment = buildKeySegment(input.id || input.name || 'search');
            form.dataset.searchHistoryKey = [pageSegment, actionSegment, fieldSegment].filter(Boolean).join('_');
        }
        form.dataset.searchHistoryStorageKey = storagePrefix + form.dataset.searchHistoryKey;

        let historyItems = readHistory(form);
        let closeTimer = null;

        const closePanel = () => {
            if (closeTimer !== null) {
                window.clearTimeout(closeTimer);
                closeTimer = null;
            }
            panel.classList.remove('is-open');
            closeTimer = window.setTimeout(() => {
                panel.classList.add('hidden');
            }, panelTransitionMs);
        };

        const openPanel = () => {
            if (panel.innerHTML.trim() === '') return;
            if (closeTimer !== null) {
                window.clearTimeout(closeTimer);
                closeTimer = null;
            }
            panel.classList.remove('hidden');
            requestAnimationFrame(() => {
                panel.classList.add('is-open');
            });
            announcePopupOpened(popupName);
        };

        const renderPanel = (filter = '') => {
            const normalizedFilter = normalizeTerm(filter).toLowerCase();
            const filteredItems = normalizedFilter
                ? historyItems.filter((item) => item.toLowerCase().includes(normalizedFilter))
                : historyItems;

            if (historyItems.length === 0) {
                panel.innerHTML = '<p class="search-history-empty">No recent searches yet.</p>';
                return;
            }

            if (filteredItems.length === 0) {
                panel.innerHTML = '<p class="search-history-empty">No matches in recent searches.</p>';
                return;
            }

            const itemButtons = filteredItems.map((item) => (
                '<button type="button" class="search-history-item" data-history-value="'
                + escapeHtml(item)
                + '"><strong>'
                + escapeHtml(item)
                + '</strong><span class="search-history-remove" data-history-remove="'
                + escapeHtml(item)
                + '" aria-label="Remove search">&times;</span></button>'
            )).join('');

            panel.innerHTML = '<div class="search-history-head"><span>Recent searches</span><button type="button" class="search-history-clear" data-history-clear>Clear</button></div>' + itemButtons;
        };

        input.addEventListener('focus', () => {
            renderPanel(input.value);
            if (historyItems.length > 0) {
                openPanel();
            }
        });

        input.addEventListener('input', () => {
            renderPanel(input.value);
            if (historyItems.length > 0) {
                openPanel();
            } else {
                closePanel();
            }
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closePanel();
            }
        });

        panel.addEventListener('mousedown', (event) => {
            event.preventDefault();
        });

        panel.addEventListener('click', (event) => {
            const clearButton = event.target.closest('[data-history-clear]');
            if (clearButton) {
                historyItems = writeHistory(form, []);
                renderPanel(input.value);
                closePanel();
                return;
            }

            const removeButton = event.target.closest('[data-history-remove]');
            if (removeButton) {
                const valueToRemove = normalizeTerm(removeButton.dataset.historyRemove || '');
                historyItems = writeHistory(
                    form,
                    historyItems.filter((item) => item.toLowerCase() !== valueToRemove.toLowerCase()),
                );
                renderPanel(input.value);
                if (historyItems.length === 0 || panel.querySelector('.search-history-item') === null) {
                    closePanel();
                }
                return;
            }

            const itemButton = event.target.closest('[data-history-value]');
            if (!itemButton) return;

            const selectedValue = normalizeTerm(itemButton.dataset.historyValue || '');
            input.value = selectedValue;
            closePanel();
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });

        form.addEventListener('submit', () => {
            const term = normalizeTerm(input.value || '');
            if (!term) return;

            historyItems = writeHistory(form, [
                term,
                ...historyItems.filter((item) => item.toLowerCase() !== term.toLowerCase()),
            ]);
        });
        panelControllers.push({ form, closePanel, popupName });
    });

    const ac = new AbortController();
    window.addEventListener('beforeunload', () => ac.abort(), { once: true });
    onPopupOpened((source) => {
        panelControllers.forEach(({ closePanel, popupName }) => {
            if (popupName !== source) closePanel();
        });
    });
    document.addEventListener('click', (event) => {
        panelControllers.forEach(({ form, closePanel }) => {
            if (form.contains(event.target)) {
                return;
            }

            closePanel();
        });
    }, { signal: ac.signal });
};
