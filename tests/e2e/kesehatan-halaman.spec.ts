import { expect, test } from '@playwright/test';

import { login } from './helpers';

// Menangkap galat yang tidak terlihat oleh tes server atau komponen: galat konsol (mis. CSP menolak gaya), respons 5xx, dan
// permintaan data (internal-api) yang gagal, di halaman-halaman utama tiap peran.

const HALAMAN_PEMILIK = [
  '/dashboard',
  '/patients',
  '/schedule',
  '/queue',
  '/invoices',
  '/reports',
  '/reports/revenue',
  '/settings/booking',
  '/audit',
];

test('NFR-08 halaman utama pemilik terbuka tanpa galat konsol, respons 5xx, atau permintaan data yang gagal', async ({
  page,
}) => {
  const masalah: string[] = [];
  page.on('console', (pesan) => {
    if (pesan.type() === 'error') {
      masalah.push(`konsol: ${pesan.text().slice(0, 160)}`);
    }
  });
  page.on('pageerror', (galat) => masalah.push(`skrip: ${galat.message.slice(0, 160)}`));
  page.on('response', (respons) => {
    if (
      respons.status() >= 500 ||
      (respons.url().includes('/internal-api/') && respons.status() >= 400)
    ) {
      masalah.push(`${respons.status()} ${respons.url()}`);
    }
  });

  await login(page, 'pemilik');
  for (const path of HALAMAN_PEMILIK) {
    const respons = await page.goto(path, { waitUntil: 'networkidle' });
    expect(respons?.status(), path).toBe(200);
  }

  expect(masalah).toEqual([]);
});
