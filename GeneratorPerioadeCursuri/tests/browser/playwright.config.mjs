import {defineConfig} from '@playwright/test';

const approvedBaseURL = 'http://178.104.183.29';

if (process.env.ESPO_BASE_URL !== approvedBaseURL) {
    throw new Error(`Set ESPO_BASE_URL explicitly to the approved test instance: ${approvedBaseURL}`);
}

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
        baseURL: approvedBaseURL,
        screenshot: 'only-on-failure',
        trace: 'off',
        video: 'off',
        ignoreHTTPSErrors: false,
    },
});
