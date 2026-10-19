import { z } from 'zod';

export const PROVIDER_VALUES = ['link', 'meta', 'fonnte', 'wablas', 'waha'] as const;
export type ProviderValue = (typeof PROVIDER_VALUES)[number];

/** Umpan balik cepat di klien; server memvalidasi ulang dan berwenang. */
export const whatsappSettingsSchema = z.object({
  provider: z.enum(PROVIDER_VALUES),
  phone_number_id: z.string().trim().regex(/^\d*$/, 'ID nomor telepon hanya angka.'),
  token: z.string().max(512, 'Terlalu panjang.'),
  base_url: z
    .string()
    .trim()
    .max(255, 'Terlalu panjang.')
    .refine((value) => value === '' || /^https:\/\/[^\s/]+/i.test(value), {
      message: 'Alamat server harus diawali https://.',
    }),
  session: z
    .string()
    .trim()
    .regex(/^[A-Za-z0-9_-]*$/, 'Nama sesi hanya huruf, angka, strip, dan garis bawah.'),
  language: z
    .string()
    .trim()
    .regex(/^([a-z]{2}(_[A-Z]{2})?)?$/, 'Kode bahasa berbentuk id atau en_US.'),
  staff_template: z
    .string()
    .trim()
    .max(100, 'Terlalu panjang.')
    .regex(/^[a-z0-9_]*$/, 'Nama templat hanya huruf kecil, angka, dan garis bawah.'),
});

export type WhatsAppSettingsValues = z.infer<typeof whatsappSettingsSchema>;

const REQUIRED_LABEL: Record<string, string> = {
  phone_number_id: 'ID nomor telepon wajib diisi.',
  token: 'Token wajib diisi.',
  base_url: 'Alamat server wajib diisi.',
};

/** Isian wajib penyedia terpilih yang masih kosong (token lama yang tersimpan dihitung terisi). */
export function missingRequired(
  values: WhatsAppSettingsValues,
  required: readonly string[],
  hasToken: boolean,
): Record<string, string> {
  const missing: Record<string, string> = {};
  for (const field of required) {
    const empty =
      field === 'token'
        ? values.token.trim() === '' && !hasToken
        : values[field as 'phone_number_id' | 'base_url'].trim() === '';
    if (empty) {
      missing[field] = REQUIRED_LABEL[field] ?? 'Wajib diisi.';
    }
  }
  return missing;
}
