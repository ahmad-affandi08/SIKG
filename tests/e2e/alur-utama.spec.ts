import { expect, test } from '@playwright/test';

import { login, todayLocal } from './helpers';

// Data sintetis (M12): NIK fiktif berawalan 9999. Nama dibuat unik per jalankan supaya pencarian tidak bertabrakan.
const PASIEN = `Pasien Uji E2E ${String(Date.now()).slice(-6)}`;
let kunjungan = '';
let tagihan = '';
const NIK = `9999${String(Date.now()).slice(-12)}`;

test.describe.serial('alur utama klinik dari pendaftaran sampai tutup kas', () => {
  test('PAS-01 resepsionis mendaftarkan pasien baru dan nomor RM terbentuk', async ({ page }) => {
    await login(page, 'rina');
    await page.goto('/patients/create');
    await page.getByLabel(/Nama lengkap/).fill(PASIEN);
    await page.getByLabel('NIK', { exact: true }).fill(NIK);
    await page.getByRole('textbox', { name: /Tanggal lahir/ }).fill('01/01/1990');
    await page.getByLabel(/Jenis kelamin/).selectOption({ index: 1 });
    await page.getByLabel(/Pasien telah membaca/).check();
    await page.getByRole('button', { name: 'Daftarkan pasien' }).click();

    await expect(page).toHaveURL(/\/patients\/[0-9A-Z]{26}/);
    await expect(page.getByText(/RM-\d{6}/).first()).toBeVisible();
    await expect(page.getByText(PASIEN).first()).toBeVisible();
  });

  test('JDW-01 resepsionis membuat janji besok untuk pasien itu', async ({ page }) => {
    await login(page, 'rina');
    await page.goto('/appointments/create');
    await page.getByLabel(/Cari pasien/).fill(PASIEN);
    await page
      .getByRole('button', { name: new RegExp(PASIEN) })
      .first()
      .click();
    await page.getByLabel(/Dokter/).selectOption({ index: 1 });
    await page.getByRole('textbox', { name: /Tanggal/ }).fill(todayLocal(1));
    await page.getByLabel(/Keluhan/).fill('Nyeri gigi geraham kanan bawah');
    await page.getByLabel(/Durasi/).selectOption('30');
    const jam = page.getByRole('button', { name: /^\d{2}:\d{2}$/ });
    await expect(jam.first()).toBeVisible();
    await jam.first().click();
    await page.getByRole('button', { name: 'Simpan janji' }).click();
    await expect(page.getByText('Janji dibuat.')).toBeVisible();
    await expect(page).toHaveURL(/\/schedule/);
    await expect(page.getByText(PASIEN)).toBeVisible();
  });

  test('JDW-05 pasien datang tanpa menunggu jam janji: resepsionis memasukkannya ke antrean hari ini', async ({
    page,
  }) => {
    await login(page, 'rina');
    await page.goto('/queue/walk-in');
    await page.getByLabel(/Cari pasien/).fill(PASIEN);
    await page
      .getByRole('button', { name: new RegExp(PASIEN) })
      .first()
      .click();
    await page.getByLabel(/Dokter/).selectOption({ index: 1 });
    await page.getByLabel(/Keluhan/).fill('Nyeri gigi geraham kanan bawah');
    await page.getByRole('button', { name: 'Masukkan ke antrean' }).click();

    await expect(page).toHaveURL(/\/queue/);
    await expect(page.getByText(PASIEN)).toBeVisible();
  });

  test('RM-01 dokter melihat antrean, memanggil pasien, dan memulai kunjungan', async ({
    page,
  }) => {
    await login(page, 'sari');
    await page.goto('/queue');
    await expect(page.getByText(PASIEN)).toBeVisible();
    await page
      .getByRole('row')
      .filter({ hasText: PASIEN })
      .getByRole('button', { name: 'Buka pemeriksaan' })
      .click();
    await expect(page).toHaveURL(/\/encounters\/[0-9A-Z]{26}/);
    kunjungan = page.url();

    await page.getByRole('button', { name: 'Mulai pemeriksaan' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Ya, mulai' }).click();
    await expect(page.getByText('Pemeriksaan dimulai.')).toBeVisible();
  });

  test('RM-02 dokter mengisi anamnesis dan catatan SOAP', async ({ page }) => {
    await login(page, 'sari');
    await page.goto(kunjungan);
    await page.getByLabel('Keluhan utama').fill('Nyeri gigi geraham kanan bawah sejak dua hari');
    await page.getByRole('button', { name: 'Simpan anamnesis' }).click();
    await expect(page.getByText(/Anamnesis disimpan/)).toBeVisible();

    await page.getByRole('tab', { name: 'Catatan SOAP' }).click();
    await page.getByLabel('Subjektif (S)').fill('Nyeri berdenyut, memberat saat malam.');
    await page.getByLabel('Asesmen (A)').fill('Pulpitis ireversibel gigi 46.');
    await page.getByLabel('Rencana (P)').fill('Perawatan saluran akar bertahap.');
    await page.getByRole('button', { name: 'Simpan catatan' }).click();
    await expect(page.getByText(/Catatan.*disimpan/)).toBeVisible();
  });

  test('RM-03 dokter menambah diagnosa dan tindakan kunjungan', async ({ page }) => {
    await login(page, 'sari');
    await page.goto(`${kunjungan}/diagnoses/create`);
    await page.getByLabel(/Cari diagnosa/).fill('pulpitis');
    await page.getByRole('button', { name: 'K04.0 Pulpitis' }).first().click();
    await page.getByLabel(/Nomor gigi/).fill('46');
    await page.getByRole('button', { name: 'Simpan diagnosa' }).click();
    await expect(page.getByText(/Diagnosa.*(ditambahkan|disimpan)/)).toBeVisible();

    await page.goto(`${kunjungan}/procedures/create`);
    await page.getByLabel(/Cari tindakan/).fill('Tambalan');
    await page
      .getByRole('button', { name: /TMB.*Tambalan komposit/ })
      .first()
      .click();
    await page.getByLabel(/Nomor gigi/).fill('46');
    await page.getByRole('button', { name: 'Simpan tindakan' }).click();
    await expect(page.getByText(/Tindakan.*(ditambahkan|dicatat|disimpan)/)).toBeVisible();
  });

  test('RM-04 dokter membuat resep dan membuka odontogram', async ({ page }) => {
    await login(page, 'sari');
    await page.goto(`${kunjungan}/prescriptions/create`);
    await page.getByLabel('Cari obat di katalog').first().fill('Amoksisilin');
    await page
      .getByRole('button', { name: /Amoksisilin 500 mg/ })
      .first()
      .click();
    await page.getByLabel(/Dosis/).first().fill('500 mg');
    await page
      .getByLabel(/Frekuensi/)
      .first()
      .fill('3 x sehari');
    await page
      .getByLabel(/Durasi/)
      .first()
      .fill('5 hari');
    await page
      .getByLabel(/Jumlah/)
      .first()
      .fill('15');
    await page.getByRole('button', { name: 'Simpan resep' }).click();
    await expect(page.getByText(/Resep.*(dibuat|disimpan|ditambahkan)/)).toBeVisible();

    await page.goto(kunjungan);
    await page.getByRole('tab', { name: 'Odontogram' }).click();
    await page.waitForTimeout(2000);
    await page.getByRole('button', { name: /^Gigi 46/ }).click();
    const panel = page.getByRole('tabpanel');
    await panel.getByLabel('Permukaan').selectOption({ label: 'oklusal atau insisal' });
    await panel.getByLabel('Kondisi baru').selectOption({ label: 'KAR - Karies' });
    await panel.getByRole('button', { name: 'Simpan kondisi' }).click();
    await expect(page.getByRole('button', { name: /^Gigi 46, .*(karies|Karies)/i })).toBeVisible();
  });

  test('RM-05 dokter menutup kunjungan; perubahan sesudahnya dibatasi', async ({ page }) => {
    await login(page, 'sari');
    await page.goto(kunjungan);
    await page.getByRole('button', { name: 'Tutup kunjungan' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Ya, tutup' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Kunjungan ditutup.' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Tutup kunjungan' })).toHaveCount(0);
    await expect(page.getByText(/Kunjungan ditutup \d{2}\/\d{2}\/\d{4}.*Perubahan/)).toBeVisible();
  });

  test('KSR-01 kasir melihat tagihan otomatis dari kunjungan yang ditutup', async ({ page }) => {
    await login(page, 'kasir');
    await page.goto('/invoices');
    await expect(page.getByText(PASIEN)).toBeVisible();
    await page.getByRole('row').filter({ hasText: PASIEN }).getByRole('link').first().click();
    await expect(page).toHaveURL(/\/invoices\/[0-9A-Z]{26}/);
    tagihan = page.url();
    await expect(page.getByText('Rp 350.000').first()).toBeVisible();
  });

  test('KSR-02 kasir menerima pembayaran sebagian lalu melunasi', async ({ page }) => {
    await login(page, 'kasir');
    await page.goto(tagihan);
    await page.getByRole('link', { name: 'Catat pembayaran' }).click();
    await expect(page).toHaveURL(/payments\/create/);
    await page.getByLabel(/Metode pembayaran/).selectOption({ label: 'Tunai' });
    await page.getByLabel(/Jumlah/).fill('100000');
    await page.getByRole('button', { name: 'Simpan pembayaran' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Ya, catat' }).click();
    await expect(page.getByText('Dibayar sebagian')).toBeVisible();
    await expect(
      page.getByText('Sisa tagihan').locator('..').getByText('Rp 250.000'),
    ).toBeVisible();

    await page.getByRole('link', { name: 'Catat pembayaran' }).click();
    await page.getByLabel(/Metode pembayaran/).selectOption({ label: 'Tunai' });
    await page.getByLabel(/Jumlah/).fill('250000');
    await page.getByRole('button', { name: 'Simpan pembayaran' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Ya, catat' }).click();
    await expect(page.getByText('Lunas', { exact: true }).first()).toBeVisible();
    await expect(page.getByRole('row').filter({ hasText: /KW-\d{4}-\d{5}/ })).toHaveCount(2);
  });

  test('KSR-04 kasir mencetak kwitansi sebagai PDF', async ({ page }) => {
    await login(page, 'kasir');
    await page.goto(tagihan);
    const href = await page
      .getByRole('link', { name: /Cetak kwitansi KW-.* A4/ })
      .first()
      .getAttribute('href');
    expect(href).toBeTruthy();
    const respons = await page.request.get(href as string);
    expect(respons.status()).toBe(200);
    expect(respons.headers()['content-type']).toContain('application/pdf');
    expect((await respons.body()).subarray(0, 4).toString()).toBe('%PDF');
  });

  test('LAP-01 pemilik melihat laporan pendapatan dan penerimaan harian', async ({ page }) => {
    await login(page, 'pemilik');
    await page.goto('/reports');
    await page
      .getByRole('link', { name: /^Pendapatan/ })
      .first()
      .click();
    await expect(page).toHaveURL(/\/reports\//);
    // Basis data uji dibuat ulang tiap jalan: hanya satu tagihan Rp 350.000 yang lunas tunai hari ini.
    await expect(
      page.getByText('Penerimaan bersih').locator('..').getByText('Rp 350.000'),
    ).toBeVisible();
    await expect(
      page.getByRole('row').filter({ hasText: 'Tunai' }).getByText('Rp 350.000').first(),
    ).toBeVisible();
  });

  test('KSR-08 kasir menutup kas hari ini', async ({ page }) => {
    await login(page, 'kasir');
    await page.goto('/cash-closings/create');
    await expect(
      page.getByText('Catatan sistem (tunai bersih)').locator('..').getByText('Rp 350.000'),
    ).toBeVisible();
    await page.getByLabel(/Uang tunai fisik/).fill('350000');
    await page.getByRole('button', { name: 'Tutup kas', exact: true }).click();
    const dialog = page.getByRole('alertdialog');
    if (await dialog.isVisible()) {
      await dialog.getByRole('button').last().click();
    }
    await expect(page.getByRole('row').filter({ hasText: 'Kiki Kasir' })).toContainText(
      'Tidak ada selisih',
    );
    await expect(page.getByRole('row').filter({ hasText: 'Kiki Kasir' })).toContainText(
      'Rp 350.000',
    );
  });
});
