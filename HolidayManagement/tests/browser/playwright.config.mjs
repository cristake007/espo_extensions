import {defineConfig} from '../../../AttendanceManagement/tests/browser/node_modules/@playwright/test/index.mjs';

export default defineConfig({
    testDir: '.',
    testMatch: '**/*.spec.mjs',
    fullyParallel: false,
    forbidOnly: true,
    retries: 0,
    workers: 1,
    timeout: 30_000,
    outputDir: '/tmp/holiday-management-playwright-results',
    reporter: [['list']],
    use: {
        screenshot: 'only-on-failure',
        trace: 'off',
        video: 'off',
    },
});
