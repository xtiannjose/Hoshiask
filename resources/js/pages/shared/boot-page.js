const runInitializer = (initializer, defer) => {
    if (defer) {
        window.setTimeout(initializer, 0);
        return;
    }

    initializer();
};

export const bootPage = (initializer, options = {}) => {
    if (typeof initializer !== 'function') return;

    const defer = options.defer !== false;
    const onReady = () => runInitializer(initializer, defer);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady, { once: true });
        return;
    }

    onReady();
};
