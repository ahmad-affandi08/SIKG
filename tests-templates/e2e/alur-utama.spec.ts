// TEMPLAT: salin ke tests/e2e/alur-utama.spec.ts. Data sintetis dari seeder uji; NIK fiktif berawalan 9999.
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

async function login(page, username: string) {
  await page.goto('/login');
  await page.getByLabel('Email atau username').fill(username);
  await page.getByLabel('Kata sandi').fill(process.env.E2E_PASSWORD ?? 'sandi-uji-lokal');
  await page.getByRole('button', { name: 'Masuk' }).click();
}

test('PAS-01 sampai KSR-08 alur utama dari daftar sampai tutup kas', async ({ page }) => {
  await login(page, 'resepsionis.uji');
  await page.getByRole('link', { name: 'Pasien' }).click();
  await page.getByRole('link', { name: 'Pasien baru' }).click();
  await page.getByLabel('Nama').fill('Pasien Uji E2E');
  await page.getByLabel('NIK').fill('9999000000009999');
  await page.getByLabel('Tanggal lahir').fill('01/01/1990');
  await page.getByRole('button', { name: 'Simpan' }).click();
  await expect(page.getByText('RM-')).toBeVisible();

  // ...janji, check-in, pemeriksaan (SOAP, odontogram, tindakan, resep), tutup kunjungan oleh dokter,
  // pembayaran sebagian lalu lunas oleh kasir, kwitansi, laporan harian, tutup kas oleh pemilik.
  // Lengkapi per peran dengan login(page, 'dokter.uji') dst.
});

test('AUTH-02 kasir tidak melihat menu rekam medis dan server menolak akses langsung', async ({ page }) => {
  await login(page, 'kasir.uji');
  await expect(page.getByRole('link', { name: 'Pemeriksaan' })).toHaveCount(0);
  const res = await page.goto('/encounters/tidak-ada');
  expect([403, 404]).toContain(res?.status());
});

test('NFR-08 beranda tanpa pelanggaran aksesibilitas serius', async ({ page }) => {
  await login(page, 'pemilik.uji');
  const hasil = await new AxeBuilder({ page }).analyze();
  expect(hasil.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')).toEqual([]);
});
