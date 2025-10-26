import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  timeout: 30_000,
  expect: { timeout: 5_000 },
  retries: 0,
  use: {
    // Trailing slash is IMPORTANT so that relative navigations like page.goto('login')
    // resolve to http://localhost:8080/index_test.php/login (and not drop index_test.php)
    baseURL: process.env.BASE_URL || 'http://localhost:8080/index_test.php/',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 10_000,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
