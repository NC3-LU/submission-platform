import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    workers: 1,
    use: {
        baseURL: 'http://127.0.0.1:8767',
        headless: true,
        launchOptions: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE } : {},
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    webServer: { command: 'bash scripts/browser-server.sh', url: 'http://127.0.0.1:8767/up', reuseExistingServer: false, timeout: 60000 },
});
