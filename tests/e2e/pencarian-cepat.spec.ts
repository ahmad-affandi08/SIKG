import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

import { login } from './helpers';

// Data sintetis (M12): NIK fiktif berawalan 9999; nama unik per jalankan.
const SUFIKS = String(Date.now()).slice(-6);
const PASIEN = `Pasien Cari Cepat ${SUFIKS}`;
const NIK = `9999${String(Date.now()).slice(-12)}`;
let nomorRm = '';
let urlPasien = '';

async function kotakCari(page: Page) {
  await page.goto('/dashboard');
  const kotak = page.getByRole('combobox', { name: 'Pencarian pasien cepat' });
  await expect(kotak).toBeVisible();
  return kotak;
}

test.describe.serial('PAS-02 pencarian pasien cepat di bar jejak halaman', () => {
  test('PAS-02 menyiapkan pasien uji', async ({ page }) => {
    await login(page, 'rina');
    await page.goto('/patients/create');
    await page.getByLabel(/Nama lengkap/).fill(PASIEN);
    await page.getByLabel('NIK', { exact: true }).fill(NIK);
    await page.getByRole('textbox', { name: /Tanggal lahir/ }).fill('02/02/1992');
    await page.getByLabel(/Jenis kelamin/).selectOption({ index: 1 });
    await page.getByLabel(/Pasien telah membaca/).check();
    await page.getByRole('button', { name: 'Daftarkan pasien' }).click();
    await expect(page).toHaveURL(/\/patients\/[0-9A-Z]{26}/);
    urlPasien = page.url();
    nomorRm =
      (
        await page
          .getByText(/[A-Z]+-\d{5,6}/)
          .first()
          .textContent()
      )?.match(/[A-Z]+-\d{5,6}/)?.[0] ?? '';
    expect(nomorRm).not.toBe('');
  });

  test('PAS-02 mengetik nama menampilkan daftar dengan NIK penuh lalu membuka pasien', async ({
    page,
  }) => {
    await login(page, 'rina');
    const kotak = await kotakCari(page);
    await kotak.fill(`cari cepat ${SUFIKS}`);

    const hasil = page.getByRole('option').filter({ hasText: PASIEN });
    await expect(hasil).toBeVisible();
    await expect(hasil).toContainText(`NIK ${NIK}`);
    await expect(hasil).toContainText('02/02/1992');
    await hasil.getByRole('button').click();

    await expect(page).toHaveURL(urlPasien);
  });

  test('PAS-02 NIK langsung membuka pasien dan tidak masuk ke alamat halaman', async ({ page }) => {
    await login(page, 'rina');
    const kotak = await kotakCari(page);
    await kotak.fill(NIK);
    await kotak.press('Enter');

    await expect(page).toHaveURL(urlPasien);
    expect(page.url()).not.toContain(NIK);
  });

  test('PAS-02 nomor RM langsung membuka pasien', async ({ page }) => {
    await login(page, 'rina');
    const kotak = await kotakCari(page);
    await kotak.fill(nomorRm);
    await kotak.press('Enter');

    await expect(page).toHaveURL(urlPasien);
  });

  test('PAS-02 NIK yang tidak terdaftar menampilkan pesan dan tetap di halaman', async ({
    page,
  }) => {
    await login(page, 'rina');
    const kotak = await kotakCari(page);
    await kotak.fill('9999000000000000');
    await kotak.press('Enter');

    await expect(page.getByText('Pasien tidak ditemukan.')).toBeVisible();
    await expect(page).toHaveURL(/\/dashboard/);
  });

  test('PAS-02 tombol / memfokuskan kotak cari', async ({ page }) => {
    await login(page, 'rina');
    const kotak = await kotakCari(page);
    await page.locator('body').click({ position: { x: 5, y: 300 } });
    await page.keyboard.press('/');

    await expect(kotak).toBeFocused();
  });
});
