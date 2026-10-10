(function () {
    try {
        var stored = window.localStorage.getItem('hoshishi_theme');
        var mode = (stored === 'light' || stored === 'dark') ? stored : 'system';
        var isDark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

        if (isDark) {
            document.documentElement.classList.add('theme-dark');
        }
    } catch (error) {
    }

    // A refresh or back/forward is not a "new page arriving": skip the entrance animation (see app.css).
    try {
        var entries = window.performance.getEntriesByType('navigation');
        if (entries.length > 0 && entries[0].type !== 'navigate') {
            document.documentElement.classList.add('no-enter-motion');
        }
    } catch (error) {
    }
})();
