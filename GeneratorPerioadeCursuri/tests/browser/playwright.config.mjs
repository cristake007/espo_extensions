import {defineConfig} from '@playwright/test';

export default defineConfig({
    testDir: '.',
    testMatch: '**/*.spec.mjs',
    fullyParallel: false,
    forbidOnly: true,
    retries: 0,
    workers: 1,
    timeout: 120_000,
    expect: {timeout: 10_000},
    outputDir: '/tmp/gpc-playwright-results',
    reporter: [['list']],
    use: {
        baseURL: process.env.ESPO_BASE_URL || 'https://crm.cursurituv.ro',
        screenshot: 'only-on-failure',
        trace: 'off',
        video: 'off',
        ignoreHTTPSErrors: false,
    },
});
