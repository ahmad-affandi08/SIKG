import { defineConfig, devices } from '@playwright/test';

import './tests/e2e/env';

export default defineConfig({
  testDir: 'tests/e2e',
  fullyParallel: false,
  // Satu pekerja: semua spesifikasi memakai satu basis data uji bersama (total uang dan antrean hari ini dihitung sekali).
  workers: 1,
  retries: 0,
  reporter: [['list']],
  globalSetup: './tests/e2e/global-setup.ts',
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000',
    trace: 'retain-on-failure',
    // Biner Chromium sistem (mis. di lingkungan tanpa unduhan peramban); kosong = bawaan Playwright (dipasang CI).
    launchOptions: process.env.E2E_CHROMIUM
      ? { executablePath: process.env.E2E_CHROMIUM, args: ['--no-sandbox'] }
      : {},
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    // Tablet memakai Chromium (CI hanya memasang Chromium), bukan WebKit bawaan profil iPad. Hanya spesifikasi yang tidak
    // bergantung pada total uang basis data bersama (alur utama menghitung pendapatan dan kas hari ini hanya sekali per jalan).
    {
      name: 'tablet',
      use: { ...devices['iPad (gen 7)'], defaultBrowserType: 'chromium' },
      testMatch: /(otorisasi|booking-online|kesehatan-halaman)\.spec\.ts/,
    },
  ],
  webServer: {
    command: 'php artisan serve --env=testing --port=8000',
    url: 'http://127.0.0.1:8000/login',
    reuseExistingServer: true,
  },
});
