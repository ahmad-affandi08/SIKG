import { expect, test } from '@playwright/test';

import { login } from './helpers';

test.describe.serial('SYS-05 saluran WhatsApp di Pengaturan', () => {
  test('SYS-05 admin memilih penyedia tidak resmi, melihat peringatan, mengisi token, dan pengaturan tersimpan tanpa menampilkan token', async ({
    page,
  }) => {
    await login(page, 'admin');
    await page.goto('/settings/whatsapp');
    await expect(
      page.getByRole('radio', { name: 'WhatsApp Resmi (Meta Cloud API)' }),
    ).toBeVisible();
    await expect(page.getByRole('radio', { name: 'Wablas (tidak resmi)' })).toBeVisible();
    await expect(
      page.getByRole('radio', { name: 'WAHA (tidak resmi, server sendiri)' }),
    ).toBeVisible();

    await page.getByRole('radio', { name: 'Fonnte (tidak resmi)' }).check();
    await expect(page.getByText(/melanggar ketentuan layanan WhatsApp/)).toBeVisible();

    await page.getByRole('button', { name: 'Simpan pengaturan' }).click();
    await expect(page.getByText('Token wajib diisi.')).toBeVisible();

    await page.getByLabel(/Token akun Fonnte/).fill('TOKEN-FONNTE-UJI-E2E');
    await page.getByRole('button', { name: 'Simpan pengaturan' }).click();
    await expect(page.getByText('Pengaturan WhatsApp disimpan.')).toBeVisible();

    await page.reload();
    await expect(page.getByRole('radio', { name: 'Fonnte (tidak resmi)' })).toBeChecked();
    await expect(page.getByLabel(/kosongkan bila tidak diganti/)).toHaveValue('');
    expect(await page.content()).not.toContain('TOKEN-FONNTE-UJI-E2E');
  });

  test('SYS-05 pesan uji hanya ke nomor staf, dan admin mengisi nomor staf di Pengaturan klinik', async ({
    page,
  }) => {
    await login(page, 'admin');
    await page.goto('/settings/whatsapp');
    await page.getByLabel(/Nomor WhatsApp tujuan/).fill('081200009999');
    await page.getByRole('button', { name: 'Kirim pesan uji' }).click();
    await expect(page.getByText(/hanya dapat dikirim ke nomor WhatsApp staf/)).toBeVisible();

    await page.goto('/settings/clinic');
    await page.getByLabel(/Nomor WhatsApp staf/).fill('0812-0000-9999\n+62 813 0000 8888');
    await page.getByRole('button', { name: 'Simpan pengaturan' }).click();
    await expect(page.getByText('Pengaturan klinik disimpan.')).toBeVisible();
    await page.reload();
    await expect(page.getByLabel(/Nomor WhatsApp staf/)).toHaveValue(
      '6281200009999\n6281300008888',
    );
  });

  test('SYS-05 nomor staf asing ditolak sebelum dikirim dan pengaturan dikembalikan ke tautan manual', async ({
    page,
  }) => {
    await login(page, 'admin');
    await page.goto('/settings/clinic');
    await page.getByLabel(/Nomor WhatsApp staf/).fill('+1 202 555 0100');
    await page.getByRole('button', { name: 'Simpan pengaturan' }).click();
    await expect(page.getByText(/harus nomor Indonesia/)).toBeVisible();

    await page.goto('/settings/whatsapp');
    await page.getByRole('radio', { name: 'Tautan WhatsApp (manual, gratis)' }).check();
    await page.getByRole('button', { name: 'Simpan pengaturan' }).click();
    await expect(page.getByText('Pengaturan WhatsApp disimpan.')).toBeVisible();
  });

  test('SYS-05 resepsionis tidak dapat membuka pengaturan WhatsApp', async ({ page }) => {
    await login(page, 'rina');
    const respons = await page.goto('/settings/whatsapp');
    expect(respons?.status()).toBe(403);
  });
});
