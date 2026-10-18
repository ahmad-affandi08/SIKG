import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';

export const PASSWORD = process.env.E2E_PASSWORD ?? 'sandi-uji-lokal';

/** Masuk lewat halaman login sungguhan; pengguna contoh berasal dari DemoSeeder (data sintetis). */
export async function login(page: Page, username: string): Promise<void> {
  await page.goto('/login');
  await page.getByLabel(/Email atau nama pengguna/).fill(username);
  await page.getByLabel(/Kata sandi/).fill(PASSWORD);
  await page.getByRole('button', { name: 'Masuk' }).click();
  await expect(page).toHaveURL(/\/dashboard/);
}

export async function logout(page: Page): Promise<void> {
  await page.getByRole('button', { name: 'Keluar' }).click();
  await expect(page).toHaveURL(/\/login/);
}

/** Tanggal hari ini menurut zona waktu klinik (Asia/Jakarta) dalam format DD/MM/YYYY. */
export function todayLocal(offsetDays = 0): string {
  const d = new Date(Date.now() + offsetDays * 86_400_000);
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Asia/Jakarta',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).formatToParts(d);
  const get = (type: string) => parts.find((p) => p.type === type)?.value ?? '';
  return `${get('day')}/${get('month')}/${get('year')}`;
}
