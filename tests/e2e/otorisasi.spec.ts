import { expect, test } from '@playwright/test';

import { login } from './helpers';

const ULID_FIKTIF = '01HZ0000000000000000000000';

// Alur E2E wajib nomor 6 (docs/rules/testing-dod.md): pengguna tanpa izin tidak melihat dan tidak dapat memanggil aksi terlarang.
// Penegakan ada di server (M9): halaman dibuka langsung lewat alamatnya, bukan hanya disembunyikan dari menu.

test('AUTH-02 tamu diarahkan ke halaman masuk untuk halaman yang dilindungi', async ({ page }) => {
  for (const path of ['/dashboard', '/patients', '/invoices', '/reports']) {
    await page.goto(path);
    await expect(page).toHaveURL(/\/login/);
  }
});

test('AUTH-03 kasir tidak dapat membuka rekam medis, laporan keuangan penuh, dan pengaturan lewat alamat langsung', async ({
  page,
}) => {
  await login(page, 'kasir');
  for (const path of [
    `/encounters/${ULID_FIKTIF}`,
    '/reports/revenue',
    '/reports/profit-loss',
    '/settings/online-payments',
    '/audit',
  ]) {
    const respons = await page.goto(path);
    expect([403, 404], `${path} harus ditolak`).toContain(respons?.status());
  }
  // Daftar laporan hanya menawarkan yang berlaku bagi kasir: ringkasan harian, piutang, dan tutup kas.
  await page.goto('/reports');
  await expect(page.getByRole('link', { name: /^Tutup kas/ })).toBeVisible();
  await expect(page.getByRole('link', { name: /^Pendapatan/ })).toHaveCount(0);
  await expect(page.getByRole('link', { name: /^Laba rugi/ })).toHaveCount(0);
});

test('AUTH-03 resepsionis tidak dapat mencatat pembayaran, menutup kas, atau membuka pengaturan', async ({
  page,
}) => {
  await login(page, 'rina');
  for (const path of [
    `/invoices/${ULID_FIKTIF}/payments/create`,
    '/cash-closings/create',
    '/settings/online-payments',
    '/audit',
  ]) {
    const respons = await page.goto(path);
    expect([403, 404], `${path} harus ditolak`).toContain(respons?.status());
  }
});

test('AUTH-03 dokter tidak dapat menutup kas atau membuka audit, tetapi dapat membuka antrean', async ({
  page,
}) => {
  await login(page, 'sari');
  for (const path of ['/cash-closings/create', '/audit', '/settings/online-payments']) {
    const respons = await page.goto(path);
    expect([403, 404], `${path} harus ditolak`).toContain(respons?.status());
  }
  const antrean = await page.goto('/queue');
  expect(antrean?.status()).toBe(200);
});

test('AUTH-03 pemilik melihat laporan dan pengaturan, tetapi tidak menutup kas (tugas kasir)', async ({
  page,
}) => {
  await login(page, 'pemilik');
  expect((await page.goto('/reports'))?.status()).toBe(200);
  expect((await page.goto('/settings/online-payments'))?.status()).toBe(200);
  expect((await page.goto('/cash-closings/create'))?.status()).toBe(403);
});

test('AUTH-01 kata sandi salah ditolak dengan pesan yang sama tanpa membuka sesi', async ({
  page,
}) => {
  await page.goto('/login');
  await page.getByLabel(/Email atau nama pengguna/).fill('kasir');
  await page.getByLabel(/Kata sandi/).fill('salah-salah-salah');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await expect(page).toHaveURL(/\/login/);
  await expect(
    page
      .getByRole('alert')
      .or(page.getByText(/tidak (sesuai|cocok|valid)|salah/i))
      .first(),
  ).toBeVisible();
  const dashboard = await page.goto('/dashboard');
  expect(dashboard?.url()).toMatch(/\/login/);
});
