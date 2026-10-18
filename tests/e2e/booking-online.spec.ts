import { expect, test } from '@playwright/test';

import { login, todayLocal } from './helpers';

// P3-01: booking online dari halaman publik tanpa login, lalu dikonfirmasi petugas. Data sintetis (M12).
const SLUG = 'klinik-uji-e2e';
const PEMESAN = `Calon Pasien E2E ${String(Date.now()).slice(-6)}`;
let halamanStatus = '';
const NOMOR_HP = `0812${String(Date.now()).slice(-8)}`;

test.describe.serial('booking online pasien sampai konfirmasi petugas', () => {
  test('P3-01 admin mengaktifkan booking online dan menetapkan alamat halaman publik', async ({
    page,
  }) => {
    await login(page, 'admin');
    await page.goto('/settings/booking');
    await page.getByLabel(/Alamat halaman/).fill(SLUG);
    await page.getByRole('checkbox', { name: /Aktifkan/ }).check();
    await page.getByLabel(/Durasi slot/).fill('30');
    await page.getByLabel(/Dapat dipesan sampai/).fill('30');
    await page.getByLabel(/Jeda minimum/).fill('0');
    await page.getByLabel(/Masa tahan permintaan/).fill('24');
    await page.getByLabel(/Janji aktif maksimal per nomor HP/).fill('2');
    await page.getByLabel(/Permintaan booking baru maksimal per jam/).fill('30');
    await page.getByLabel(/Teks sambutan/).fill('Selamat datang di klinik uji.');
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByText('Pengaturan booking online disimpan.')).toBeVisible();
  });

  test('P3-01 calon pasien memesan jam lewat halaman publik tanpa login dan melihat statusnya', async ({
    page,
  }) => {
    await page.goto(`/book/${SLUG}`);
    await expect(page.getByText('Selamat datang di klinik uji.')).toBeVisible();
    // Isian jebakan bot tidak terlihat pengguna.
    const jebakan = await page.getByLabel('Situs web').boundingBox();
    expect(jebakan === null || jebakan.x + jebakan.width < 0).toBe(true);

    await page.getByLabel(/^Dokter/).selectOption({ index: 1 });
    await page.getByRole('textbox', { name: /Tanggal/ }).fill(todayLocal(1));
    const slot = page.getByRole('button', { name: /^\d{2}:\d{2}$/ });
    await expect(slot.first()).toBeVisible();
    await slot.nth(3).click();
    await page.getByLabel(/Nama lengkap/).fill(PEMESAN);
    await page.getByLabel(/Nomor HP/).fill(NOMOR_HP);
    await page.getByLabel(/Keluhan/).fill('Gigi berlubang dan ngilu.');
    await page.getByLabel(/Saya setuju/).check();
    await page.getByRole('button', { name: 'Kirim permintaan' }).click();

    await expect(page).toHaveURL(new RegExp(`/book/${SLUG}/r/[a-f0-9]{40}$`));
    halamanStatus = page.url();
    await expect(page.getByText('Menunggu konfirmasi klinik')).toBeVisible();
    // Halaman status memakai nama depan saja dan tidak menampilkan nomor HP.
    await expect(page.getByText(new RegExp(`Halo ${PEMESAN.split(' ')[0]}`))).toBeVisible();
    await expect(page.getByText(NOMOR_HP)).toHaveCount(0);
  });

  test('P3-01 petugas melihat permintaan dengan nomor HP tersamar', async ({ page }) => {
    await login(page, 'rina');
    await page.goto('/booking-requests');
    const permintaan = page.getByRole('article', { name: new RegExp(PEMESAN) });
    await expect(permintaan).toBeVisible();
    await expect(permintaan).toContainText(NOMOR_HP.slice(-4));
    await expect(permintaan).not.toContainText(NOMOR_HP);

    // Konfirmasi: nomor belum terverifikasi, jadi petugas wajib menyatakan sudah menghubungi pasien.
    await permintaan.getByLabel(/Cari pasien/).fill('Budi Santoso');
    await permintaan
      .getByRole('button', { name: /Budi Santoso/ })
      .first()
      .click();
    await permintaan.getByLabel(/sudah dihubungi/).check();
    await permintaan.getByRole('button', { name: 'Konfirmasi menjadi janji' }).click();
    await expect(page.getByText(/dikonfirmasi|Janji dibuat/i).first()).toBeVisible();
  });

  test('P3-01 calon pasien melihat permintaannya sudah dikonfirmasi tanpa login', async ({
    page,
  }) => {
    await page.goto(halamanStatus);
    await expect(page.getByText('Dikonfirmasi').first()).toBeVisible();
  });
});
