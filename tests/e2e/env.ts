/**
 * Nilai bawaan lingkungan untuk E2E (konstanta uji, sama dengan phpunit.xml dan layanan MySQL di CI). Variabel yang sudah diset
 * dari luar tidak ditimpa. Dipakai oleh playwright.config.ts (server) dan global-setup.ts (migrasi dan seeder), sehingga keduanya
 * memakai basis data uji yang sama tanpa bergantung pada isi berkas konfigurasi lokal.
 */
const DEFAULTS: Record<string, string> = {
  APP_ENV: 'testing',
  APP_DEBUG: 'false',
  DB_CONNECTION: 'mysql',
  DB_HOST: '127.0.0.1',
  DB_PORT: '3306',
  DB_DATABASE: 'sikg_test',
  DB_USERNAME: 'sikg',
  DB_PASSWORD: 'sikg_local_only',
  SESSION_DRIVER: 'file',
  CACHE_STORE: 'file',
  QUEUE_CONNECTION: 'sync',
  MAIL_MAILER: 'array',
  PII_HASH_KEY: 'kunci-hash-e2e-saja',
  DEMO_PASSWORD: 'sandi-uji-lokal',
  DEMO_ALL_DAY: '1',
};

for (const [name, value] of Object.entries(DEFAULTS)) {
  process.env[name] ??= value;
}

// Kata sandi pengguna contoh yang dipakai tes untuk masuk; selalu sama dengan yang ditanam seeder.
process.env.E2E_PASSWORD ??= process.env.DEMO_PASSWORD;
