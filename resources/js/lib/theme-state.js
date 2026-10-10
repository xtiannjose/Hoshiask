import { announcePopupOpened, onPopupOpened } from './exclusive-popups';

const THEME_MODES = ['light', 'dark', 'system'];

export const registerThemeGlobals = () => {
    window.AppTheme = (() => {
        const storageKey = 'hoshishi_theme';
        const root = document.documentElement;
        const systemQuery = window.matchMedia('(prefers-color-scheme: dark)');

        const hasReducedMotionPreference = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const isDark = () => root.classList.contains('theme-dark');

        const getMode = () => {
            try {
                const stored = window.localStorage.getItem(storageKey);
                return THEME_MODES.includes(stored) ? stored : 'system';
            } catch (error) {
                return 'system';
            }
        };

        const resolveIsDark = (mode) => {
            if (mode === 'dark') return true;
            if (mode === 'light') return false;
            return systemQuery.matches;
        };

        const persistMode = (mode) => {
            try {
                window.localStorage.setItem(storageKey, mode);
            } catch (error) {
                if (typeof console !== 'undefined' && typeof console.warn === 'function') {
                    console.warn('Failed to persist theme preference.', error);
                }
            }
        };

        const applyClass = (isDarkMode, options = {}) => {
            const shouldAnimate = options.animate !== false && !hasReducedMotionPreference();

            if (shouldAnimate) {
                // Both classes in the same step: the colours change in one repaint with transitions switched off,
                // instead of waiting a frame and repainting the whole page twice.
                root.classList.add('theme-switching');
                root.classList.toggle('theme-dark', isDarkMode);
                window.setTimeout(() => {
                    root.classList.remove('theme-switching');
                }, 120);

                return;
            }

            root.classList.toggle('theme-dark', isDarkMode);
            root.classList.remove('theme-switching');
        };

        const setMode = (mode, options = {}) => {
            const nextMode = THEME_MODES.includes(mode) ? mode : 'system';
            const nextIsDark = resolveIsDark(nextMode);

            if (options.persist !== false) persistMode(nextMode);
            applyClass(nextIsDark, options);

            return nextIsDark;
        };

        const toggle = () => {
            const nextMode = isDark() ? 'light' : 'dark';
            return setMode(nextMode);
        };

        systemQuery.addEventListener('change', () => {
            if (getMode() === 'system') {
                applyClass(systemQuery.matches);
            }
        });

        return { getMode, setMode, isDark, toggle };
    })();
};

const buildThemeState = () => ({
    darkMode: false,
    themeMode: 'system',
    initThemeState() {
        if (window.AppTheme) {
            this.themeMode = window.AppTheme.getMode();
            this.darkMode = window.AppTheme.isDark();
            return;
        }

        this.darkMode = document.documentElement.classList.contains('theme-dark');
    },
    toggleDarkMode() {
        if (window.AppTheme) {
            this.darkMode = window.AppTheme.toggle();
            this.themeMode = window.AppTheme.getMode();
            return;
        }

        this.darkMode = !this.darkMode;
        document.documentElement.classList.toggle('theme-dark', this.darkMode);
        window.localStorage.setItem('hoshishi_theme', this.darkMode ? 'dark' : 'light');
    },
    setThemeMode(mode) {
        this.themeMode = mode;

        if (window.AppTheme) {
            this.darkMode = window.AppTheme.setMode(mode);
            return;
        }

        this.darkMode = mode === 'dark';
        document.documentElement.classList.toggle('theme-dark', this.darkMode);
        window.localStorage.setItem('hoshishi_theme', mode);
    },
});

const buildLegalModalState = () => ({
    legalModalOpen: false,
    legalModalTab: 'terms',
    openLegalModal(tab = 'terms') {
        this.legalModalTab = tab;
        this.legalModalOpen = true;
        document.body.classList.add('overflow-hidden');
    },
    closeLegalModal() {
        this.legalModalOpen = false;
        document.body.classList.remove('overflow-hidden');
    },
});

export const registerAlpineStateCollections = (Alpine) => {
    Alpine.data('appShellState', () => ({
        sidebarOpen: false,
        ...buildThemeState(),
        ...buildLegalModalState(),
        init() {
            this.initThemeState();
            // The phone menu covers the page: keep the page from scrolling behind it.
            this.$watch('sidebarOpen', (open) => {
                document.body.classList.toggle('app-drawer-open', open);
            });
        },
        closeSidebar() {
            this.sidebarOpen = false;
        },
    }));

    Alpine.data('loginPageState', () => ({
        ...buildThemeState(),
        ...buildLegalModalState(),
        init() {
            this.initThemeState();
        },
    }));

    Alpine.data('publicLegalPageState', () => ({
        ...buildThemeState(),
        init() {
            this.initThemeState();
        },
    }));

    Alpine.data('headerNotificationDropdown', () => ({
        notificationOpen: false,
        init() {
            onPopupOpened((source) => {
                if (source !== 'notifications') this.notificationOpen = false;
            });
        },
        toggleNotifications() {
            this.notificationOpen = !this.notificationOpen;
            if (this.notificationOpen) announcePopupOpened('notifications');
        },
        closeNotifications() {
            this.notificationOpen = false;
        },
    }));

    Alpine.data('profileMenuDropdown', () => ({
        open: false,
        init() {
            onPopupOpened((source) => {
                if (source !== 'account-menu') this.open = false;
            });
        },
        toggleMenu() {
            this.open = !this.open;
            if (this.open) announcePopupOpened('account-menu');
        },
        closeMenu() {
            this.open = false;
        },
    }));
};
