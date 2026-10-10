import { announcePopupOpened, onPopupOpened } from './exclusive-popups';

export const registerEnhancedSelects = () => {
    const canEnhanceSelect = (select) => {
        if (!(select instanceof HTMLSelectElement)) return false;
        if (select.dataset.nativeSelect !== undefined) return false;
        if (select.dataset.enhancedReady === '1') return false;
        if (select.closest('.app-select')) return false;

        if (select.multiple) {
            return select.dataset.enhancedMultiselect === '1';
        }

        const sizeAttr = Number(select.getAttribute('size') || '1');
        if (!Number.isNaN(sizeAttr) && sizeAttr > 1) return false;

        return true;
    };

    const enhancedSelects = new Set();
    const optionObservers = new Map();
    let enhancedSelectIndex = 0;
    let openDropdown = null;

    const closeDropdown = (dropdown) => {
        if (!dropdown) return;
        dropdown.classList.remove('is-open');
        const toggle = dropdown.querySelector('.app-select-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
            toggle.removeAttribute('aria-activedescendant');
        }
        if (openDropdown === dropdown) {
            openDropdown = null;
        }
    };

    const closeAllDropdowns = (except = null) => {
        enhancedSelects.forEach((select) => {
            const wrapper = select.nextElementSibling;
            if (!wrapper || !wrapper.classList.contains('app-select') || wrapper === except) return;
            closeDropdown(wrapper);
        });
    };

    const enhanceSelect = (select) => {
        if (!canEnhanceSelect(select)) return;
        select.dataset.enhancedReady = '1';
        enhancedSelects.add(select);
        const capitalizeDisplay = select.dataset.textTransform === 'capitalize';
        const isMultiSelect = select.multiple;
        const placeholder = select.dataset.placeholder || 'Select option';

        const minWidth = window.getComputedStyle(select).minWidth;
        const nativeArrow = select.nextElementSibling
            && select.nextElementSibling.tagName === 'SVG'
            && select.nextElementSibling.classList.contains('pointer-events-none')
            ? select.nextElementSibling
            : null;

        const wrapper = document.createElement('div');
        wrapper.className = 'app-select';
        const selectIndex = enhancedSelectIndex++;
        wrapper.dataset.enhancedIndex = String(selectIndex);
        if (isMultiSelect) {
            wrapper.classList.add('app-select--multiple');
        }
        if (select.classList.contains('w-full') || select.classList.contains('form-input')) {
            wrapper.classList.add('w-full');
        }
        if (select.classList.contains('form-input')) {
            wrapper.classList.add('app-select-form-field');
        }
        if (minWidth && minWidth !== '0px') {
            wrapper.style.minWidth = minWidth;
        }

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'app-select-toggle';
        toggle.setAttribute('aria-haspopup', 'listbox');
        toggle.setAttribute('aria-expanded', 'false');

        const label = document.createElement('span');
        label.className = 'truncate';
        if (capitalizeDisplay) {
            label.classList.add('capitalize');
        }

        const caret = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        caret.setAttribute('viewBox', '0 0 24 24');
        caret.setAttribute('fill', 'none');
        caret.setAttribute('stroke', 'currentColor');
        caret.classList.add('app-select-caret');
        const caretPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        caretPath.setAttribute('stroke-linecap', 'round');
        caretPath.setAttribute('stroke-linejoin', 'round');
        caretPath.setAttribute('stroke-width', '2');
        caretPath.setAttribute('d', 'M19 9l-7 7-7-7');
        caret.appendChild(caretPath);

        toggle.appendChild(label);
        toggle.appendChild(caret);

        const menu = document.createElement('div');
        const menuId = `app-select-menu-${selectIndex}`;
        menu.className = 'app-select-menu';
        menu.id = menuId;
        menu.setAttribute('role', 'listbox');
        const menuName = (
            select.getAttribute('aria-label')
            || select.labels?.[0]?.textContent
            || select.getAttribute('name')
            || 'Options'
        ).replace(/\s+/g, ' ').replace(/\s*\*$/, '').trim();
        menu.setAttribute('aria-label', menuName);
        toggle.setAttribute('aria-controls', menuId);
        if (isMultiSelect) {
            menu.setAttribute('aria-multiselectable', 'true');
        }

        const getSelectedOptions = () => Array.from(select.options).filter((option) => option.selected);
        const getEnabledOptionButtons = () => Array.from(
            menu.querySelectorAll('.app-select-option:not([disabled])')
        );
        const selectedOptionIndex = () => {
            const buttons = getEnabledOptionButtons();

            return Math.max(0, buttons.findIndex((button) => button.dataset.value === select.value));
        };
        const focusOptionAtIndex = (index) => {
            const buttons = getEnabledOptionButtons();
            if (buttons.length === 0) return;

            const boundedIndex = Math.max(0, Math.min(index, buttons.length - 1));
            const button = buttons[boundedIndex];
            button.focus();
            toggle.setAttribute('aria-activedescendant', button.id);
            button.scrollIntoView({ block: 'nearest' });
        };
        const openSelect = ({ focus = null } = {}) => {
            if (toggle.disabled) return;

            closeAllDropdowns(wrapper);
            wrapper.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
            openDropdown = wrapper;
            announcePopupOpened('styled-dropdown');

            if (focus === 'first') {
                window.requestAnimationFrame(() => focusOptionAtIndex(0));
                return;
            }

            if (focus === 'last') {
                window.requestAnimationFrame(() => {
                    const buttons = getEnabledOptionButtons();
                    focusOptionAtIndex(buttons.length - 1);
                });
                return;
            }

            if (focus === 'selected') {
                window.requestAnimationFrame(() => focusOptionAtIndex(selectedOptionIndex()));
            }
        };
        const closeSelect = ({ restoreFocus = false } = {}) => {
            closeDropdown(wrapper);
            if (restoreFocus) {
                toggle.focus();
            }
        };

        const syncLabel = () => {
            if (isMultiSelect) {
                const selectedLabels = getSelectedOptions()
                    .map((option) => option.textContent.trim())
                    .filter((text) => text !== '');

                label.textContent = selectedLabels.length > 0
                    ? selectedLabels.join(', ')
                    : placeholder;
            } else {
                const selectedOption = select.options[select.selectedIndex];
                label.textContent = selectedOption ? selectedOption.textContent.trim() : placeholder;
            }

            menu.querySelectorAll('.app-select-option').forEach((optionButton) => {
                const isSelected = isMultiSelect
                    ? getSelectedOptions().some((option) => option.value === optionButton.dataset.value)
                    : optionButton.dataset.value === select.value;

                optionButton.classList.toggle('is-active', isSelected);
                optionButton.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                optionButton.tabIndex = isSelected ? 0 : -1;
            });
        };

        const syncDisabledState = () => {
            const disabled = select.disabled;
            toggle.disabled = disabled;
            wrapper.classList.toggle('opacity-60', disabled);
            wrapper.classList.toggle('pointer-events-none', disabled);
            if (disabled) {
                closeDropdown(wrapper);
            }
        };

        const buildOptions = () => {
            menu.innerHTML = '';
            Array.from(select.options).forEach((option) => {
                const optionButton = document.createElement('button');
                const optionIndex = menu.childElementCount;
                optionButton.type = 'button';
                optionButton.className = 'app-select-option';
                optionButton.id = `${menuId}-option-${optionIndex}`;
                optionButton.setAttribute('role', 'option');
                optionButton.tabIndex = -1;
                if (capitalizeDisplay) {
                    optionButton.classList.add('capitalize');
                }
                optionButton.dataset.value = option.value;
                optionButton.textContent = option.textContent.trim();
                optionButton.disabled = option.disabled;
                if (option.disabled) {
                    optionButton.classList.add('opacity-50', 'cursor-not-allowed');
                }

                optionButton.addEventListener('click', () => {
                    if (option.disabled) return;

                    if (isMultiSelect) {
                        option.selected = !option.selected;
                    } else {
                        select.value = option.value;
                    }

                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    syncLabel();

                    if (!isMultiSelect) {
                        closeSelect({ restoreFocus: true });
                    }
                });

                menu.appendChild(optionButton);
            });

            syncLabel();
        };

        toggle.addEventListener('click', () => {
            if (toggle.disabled) return;

            const willOpen = !wrapper.classList.contains('is-open');
            if (!willOpen) {
                closeSelect();
                return;
            }

            openSelect();
        });

        toggle.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeSelect({ restoreFocus: true });
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();

                if (wrapper.classList.contains('is-open')) {
                    focusOptionAtIndex(selectedOptionIndex() + 1);
                    return;
                }

                openSelect({ focus: 'selected' });
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();

                if (wrapper.classList.contains('is-open')) {
                    focusOptionAtIndex(selectedOptionIndex() - 1);
                    return;
                }

                openSelect({ focus: 'last' });
                return;
            }

            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();

                if (wrapper.classList.contains('is-open')) {
                    closeSelect();
                    return;
                }

                openSelect({ focus: 'selected' });
                return;
            }

            if (event.key === 'Home') {
                event.preventDefault();
                openSelect({ focus: 'first' });
                return;
            }

            if (event.key === 'End') {
                event.preventDefault();
                openSelect({ focus: 'last' });
            }
        });

        menu.addEventListener('keydown', (event) => {
            const optionButtons = getEnabledOptionButtons();
            const activeElement = event.target.closest('.app-select-option');
            const currentIndex = Math.max(0, optionButtons.indexOf(activeElement));

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                focusOptionAtIndex(currentIndex + 1);
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                focusOptionAtIndex(currentIndex - 1);
                return;
            }

            if (event.key === 'Home') {
                event.preventDefault();
                focusOptionAtIndex(0);
                return;
            }

            if (event.key === 'End') {
                event.preventDefault();
                focusOptionAtIndex(optionButtons.length - 1);
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeSelect({ restoreFocus: true });
                return;
            }

            if (event.key === 'Tab') {
                closeSelect();
            }
        });

        select.addEventListener('change', syncLabel);
        const optionObserver = new MutationObserver(() => {
            buildOptions();
            syncLabel();
            syncDisabledState();
        });
        optionObservers.set(select, optionObserver);
        optionObserver.observe(select, {
            childList: true,
            subtree: false,
            attributes: true,
            attributeFilter: ['disabled'],
        });

        wrapper.appendChild(toggle);
        wrapper.appendChild(menu);
        select.insertAdjacentElement('afterend', wrapper);
        select.classList.add('app-native-select-hidden');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');
        if (nativeArrow) {
            nativeArrow.classList.add('hidden');
        }

        buildOptions();
        syncDisabledState();
    };

    Array.from(document.querySelectorAll('select')).forEach((select) => {
        enhanceSelect(select);
    });

    if (enhancedSelects.size === 0) return;

    const selectObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof Element)) return;
                if (node.matches('select')) {
                    enhanceSelect(node);
                }
                node.querySelectorAll('select').forEach((select) => {
                    enhanceSelect(select);
                });
            });
            mutation.removedNodes.forEach((node) => {
                if (!(node instanceof Element)) return;
                const selects = node.matches('select') ? [node] : Array.from(node.querySelectorAll('select'));
                selects.forEach((select) => {
                    const obs = optionObservers.get(select);
                    if (obs) {
                        obs.disconnect();
                        optionObservers.delete(select);
                        enhancedSelects.delete(select);
                    }
                });
            });
        });
    });
    selectObserver.observe(document.body, { childList: true, subtree: true });

    document.addEventListener('click', (event) => {
        if (!openDropdown) return;
        if (openDropdown.contains(event.target)) return;

        const toggle = openDropdown.querySelector('.app-select-toggle');
        closeDropdown(openDropdown);
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
    });

    onPopupOpened((source) => {
        if (source !== 'styled-dropdown') closeDropdown(openDropdown);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !openDropdown) return;

        const toggle = openDropdown.querySelector('.app-select-toggle');
        closeDropdown(openDropdown);
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus();
        }
    });
};
