import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    timeout: 30_000,
    expect: {
        timeout: 7_000,
    },
    retries: process.env.CI ? 2 : 0,
    reporter: process.env.CI
        ? [['list'], ['html', { open: 'never' }]]
        : 'list',
    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    webServer: process.env.PLAYWRIGHT_BASE_URL
        ? undefined
        : {
            command: 'php artisan serve --host=127.0.0.1 --port=8000',
            url: `${baseURL}/login`,
            timeout: 120_000,
            reuseExistingServer: !process.env.CI,
        },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
            },
        },
    ],
});
