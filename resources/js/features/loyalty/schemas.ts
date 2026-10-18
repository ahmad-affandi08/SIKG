import { z } from 'zod';

const count = (label: string) =>
  z
    .string()
    .trim()
    .min(1, `${label} wajib diisi.`)
    .regex(/^\d{1,7}$/, `${label} berupa bilangan bulat.`);

/** Umpan balik cepat di klien; server memvalidasi ulang dan berwenang (P3-06). */
export const voucherCodeSchema = z.object({
  code: z
    .string()
    .trim()
    .min(1, 'Kode promo wajib diisi.')
    .max(30, 'Kode promo terlalu panjang.')
    .regex(/^[A-Za-z0-9_-]+$/, 'Kode promo hanya huruf, angka, garis bawah, dan strip.'),
});

export function pointsSchema(min: number, max: number, balance: number) {
  return z.object({
    points: count('Jumlah poin').superRefine((value, ctx) => {
      const points = Number(value);
      if (points < min) {
        ctx.addIssue({ code: 'custom', message: `Penukaran minimal ${String(min)} poin.` });
      } else if (points > balance) {
        ctx.addIssue({ code: 'custom', message: 'Saldo poin pasien tidak cukup.' });
      } else if (points > max) {
        ctx.addIssue({
          code: 'custom',
          message: `Melebihi nilai tagihan; maksimal ${String(max)} poin.`,
        });
      }
    }),
  });
}

export const packageUseSchema = z.object({
  item_id: z.string().min(1, 'Pilih item tagihan.'),
  patient_package_id: z.string().min(1, 'Pilih paket pasien.'),
});

export const sellPackageSchema = z.object({
  package_id: z.string().min(1, 'Pilih paket.'),
  encounter_id: z.string().min(1, 'Pilih kunjungan yang sedang berjalan.'),
});

export const loyaltySettingsSchema = z.object({
  enabled: z.boolean(),
  rupiah_per_point: count('Belanja per poin'),
  point_value_amount: count('Nilai satu poin'),
  min_redeem_points: count('Minimum penukaran'),
});
export type LoyaltySettingsValues = z.infer<typeof loyaltySettingsSchema>;

export const LEDGER_LABEL: Record<string, string> = {
  earn: 'Dapat dari tagihan lunas',
  redeem: 'Ditukar',
  release: 'Penukaran dikembalikan',
  reverse: 'Dibalik (tagihan dibatalkan)',
  adjust: 'Penyesuaian',
};
