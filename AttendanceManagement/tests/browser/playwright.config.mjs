import {defineConfig} from '@playwright/test';

export default defineConfig({
    testDir: '.',
    testMatch: '**/*.spec.mjs',
    fullyParallel: false,
    forbidOnly: true,
    retries: 0,
    workers: 1,
    timeout: 30_000,
    outputDir: '/tmp/attendance-playwright-results',
    reporter: [['list']],
    use: {
        screenshot: 'only-on-failure',
        trace: 'off',
        video: 'off',
    },
});
