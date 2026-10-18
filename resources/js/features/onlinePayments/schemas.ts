import { z } from 'zod';

/** Umpan balik cepat di klien; server memvalidasi ulang dan berwenang (P3-05). */
export function startQrSchema(balance: number) {
  return z.object({
    amount: z
      .string()
      .trim()
      .refine((value) => value === '' || /^\d{1,12}$/.test(value), {
        message: 'Jumlah berupa rupiah bulat.',
      })
      .refine((value) => value === '' || (Number(value) >= 1 && Number(value) <= balance), {
        message: 'Jumlah harus lebih dari nol dan tidak melebihi sisa tagihan.',
      }),
  });
}

export const onlineSettingsSchema = z.object({
  enabled: z.boolean(),
  environment: z.enum(['sandbox', 'production'], { message: 'Pilih lingkungan.' }),
  client_id: z.string().max(100, 'Client ID terlalu panjang.'),
  secret_key: z.string().max(200, 'Secret Key terlalu panjang.'),
  payment_method_id: z.string(),
  expiry_minutes: z
    .string()
    .regex(/^\d{1,4}$/, 'Masa berlaku berupa menit bulat.')
    .refine((value) => Number(value) >= 5 && Number(value) <= 1440, {
      message: 'Masa berlaku 5 sampai 1440 menit.',
    }),
});
export type OnlineSettingsValues = z.infer<typeof onlineSettingsSchema>;

export const intentStatusSchema = z.object({
  status: z.string(),
  status_label: z.string(),
  paid: z.boolean(),
});

export const INTENT_TONE: Record<string, 'success' | 'danger' | 'warning' | 'neutral'> = {
  pending: 'warning',
  paid: 'success',
  expired: 'neutral',
  cancelled: 'neutral',
  failed: 'danger',
  mismatch: 'danger',
};
