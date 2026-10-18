import { expect, test } from '@playwright/test';

import { login } from './helpers';

// MST-11: impor stok awal obat dari berkas CSV dengan pratinjau; konfirmasi menambah stok lewat penerimaan biasa (data sintetis).
test('MST-11 admin mengimpor stok awal: pratinjau lalu konfirmasi menambah stok', async ({
  page,
}) => {
  await login(page, 'admin');
  await page.goto('/stock');
  await page.getByRole('link', { name: /Impor stok awal/ }).click();
  await expect(page).toHaveURL(/\/settings\/master\/stock-opening\/import/);
  // Peringatan bahwa impor ini MENAMBAH stok setiap kali dikonfirmasi.
  await expect(page.getByText(/MENAMBAH stok/).first()).toBeVisible();

  const csv = [
    'Nama obat atau bahan;Jumlah;Nomor batch;Tanggal kedaluwarsa',
    'Parasetamol 500 mg;40;E2E-001;31/12/2030',
    'Obat Tidak Ada Di Katalog;5;;',
    'Amoksisilin 500 mg;0;;',
  ].join('\n');
  await page
    .locator('input[type=file]')
    .setInputFiles({
      name: 'stok-awal.csv',
      mimeType: 'text/csv',
      buffer: Buffer.from(csv, 'utf-8'),
    });
  await page.getByRole('button', { name: /Periksa|Unggah|Pratinjau/ }).click();

  // Pratinjau: satu baris sah, dua baris perlu diperbaiki (obat tidak ada di katalog, jumlah nol).
  await expect(page.getByText(/Baris yang perlu diperbaiki/)).toBeVisible();
  await expect(page.getByText(/tidak ada di katalog/i).first()).toBeVisible();

  await page
    .getByRole('button', { name: /Impor 1 baris|Konfirmasi|Impor/ })
    .last()
    .click();
  await page.getByRole('alertdialog').getByRole('button').last().click();
  await expect(page).toHaveURL(/\/stock/);
  await expect(page.getByText(/1 baris diimpor/)).toBeVisible();
  // Stok Parasetamol bertambah 40 dari saldo contoh 200 (basis data uji dibuat ulang tiap jalan).
  await expect(
    page.getByRole('row').filter({ hasText: 'Parasetamol 500 mg' }).first(),
  ).toContainText('240');
});
