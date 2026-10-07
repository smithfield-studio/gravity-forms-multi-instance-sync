import { defineConfig } from '@playwright/test';

// Locally this uses the installed Chrome; CI installs Playwright's Chromium
export default defineConfig({
  testDir: 'tests/e2e',
  globalSetup: './tests/e2e/global-setup.mjs',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  reporter: process.env.CI ? 'github' : 'list',
  use: {
    browserName: 'chromium',
    channel: process.env.CI ? undefined : 'chrome',
    viewport: { width: 1280, height: 800 },
  },
});
