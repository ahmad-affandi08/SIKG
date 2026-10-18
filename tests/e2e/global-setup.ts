import { execFileSync } from 'node:child_process';

import './env';

/**
 * Menyiapkan basis data uji sebelum E2E: tabel dibuat ulang dan diisi data sintetis DemoSeeder (kata sandi diketahui, jadwal dokter
 * sehari penuh). Menolak berjalan bila lingkungan aplikasi produksi (M17). Lewati dengan E2E_SKIP_DB=1 bila basis data sudah siap.
 */
export default function globalSetup(): void {
  if (process.env.E2E_SKIP_DB === '1') {
    return;
  }
  if (process.env.APP_ENV === 'production') {
    throw new Error('E2E menolak berjalan dengan APP_ENV produksi.');
  }
  execFileSync(
    'php',
    ['artisan', 'migrate:fresh', '--force', '--no-interaction', '--env=testing'],
    { stdio: 'inherit' },
  );
  execFileSync('php', ['artisan', 'db:seed', '--class=DemoSeeder', '--force', '--no-interaction'], {
    stdio: 'inherit',
  });
}
