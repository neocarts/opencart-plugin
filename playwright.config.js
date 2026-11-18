import { defineConfig, devices } from '@playwright/test'

// One suite, three shops of the Docker stand (make up). The Shop API is tests/e2e/mock-core.mjs
// (compose service), the widget a stub (tests/e2e/fixtures.js). docs/ai/testing.md
export default defineConfig({
  testDir: 'tests/e2e',
  // one mock core and three shop databases are shared state
  workers: 1,
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  // one output folder per shop set: parallel runs on different shops must not clean each other's traces
  outputDir: `test-results/run-${process.env.E2E_SHOPS ?? 'all'}`,
  globalSetup: './tests/e2e/global-setup.js',
  globalTeardown: './tests/e2e/global-teardown.js',
  use: { trace: 'retain-on-failure', ...devices['Desktop Chrome'] },
  projects: ['oc2', 'oc3', 'oc4'].map((name) => ({
    name,
    use: { baseURL: `http://localhost:${{ oc2: 8082, oc3: 8083, oc4: 8084 }[name]}` },
  })),
})
