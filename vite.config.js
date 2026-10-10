import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    // Pre-bundle known dependencies and pre-transform the entry files at
    // startup so the first page load doesn't wait on Vite (and Tailwind).
    optimizeDeps: {
        include: ['@alpinejs/csp'],
    },
    server: {
        host: '127.0.0.1',
        warmup: {
            clientFiles: ['./resources/js/app.js', './resources/css/app.css'],
        },
    },
});