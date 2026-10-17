import { z } from 'zod';

export const PLACEHOLDERS = ['nama', 'tanggal', 'jam', 'dokter', 'klinik', 'bulan'] as const;

/** Penanda {…} pada templat yang tidak dikenal; kosong berarti templat sah. */
export function unknownPlaceholders(template: string): string[] {
  const found = [...template.matchAll(/\{([^{}]*)\}/g)].map((m) => m[1] ?? '');
  return [...new Set(found.filter((name) => !(PLACEHOLDERS as readonly string[]).includes(name)))];
}

const kindSchema = z.object({
  enabled: z.boolean(),
  template: z
    .string()
    .max(500, 'Templat maksimal 500 karakter.')
    .superRefine((value, ctx) => {
      const unknown = unknownPlaceholders(value);
      if (unknown.length > 0) {
        ctx.addIssue({
          code: 'custom',
          message: `Penanda tidak dikenal: ${unknown.map((n) => `{${n}}`).join(', ')}.`,
        });
      }
    }),
  api_template: z
    .string()
    .trim()
    .max(100, 'Terlalu panjang.')
    .regex(/^[a-z0-9_]*$/, 'Nama templat hanya huruf kecil, angka, dan garis bawah.'),
});

/** Umpan balik cepat di klien; server memvalidasi ulang dan berwenang (P2-03). */
export const reminderSettingsSchema = z
  .object({
    enabled: z.boolean(),
    mode: z.enum(['link', 'api']),
    recall_months: z
      .string()
      .trim()
      .regex(/^\d{1,2}$/, 'Jarak kontrol berupa bilangan bulat bulan.')
      .refine((v) => Number(v) >= 1 && Number(v) <= 24, 'Jarak kontrol 1 sampai 24 bulan.'),
    api_phone_number_id: z.string().trim().regex(/^\d*$/, 'ID nomor telepon hanya angka.'),
    api_token: z.string().max(512, 'Terlalu panjang.'),
    api_language: z
      .string()
      .trim()
      .regex(/^([a-z]{2}(_[A-Z]{2})?)?$/, 'Kode bahasa berbentuk id atau en_US.'),
    kinds: z.object({ h1: kindSchema, h0: kindSchema, recall: kindSchema }),
  })
  .superRefine((v, ctx) => {
    if (v.enabled && v.mode === 'api' && v.api_phone_number_id === '') {
      ctx.addIssue({
        code: 'custom',
        path: ['api_phone_number_id'],
        message: 'Mode API butuh ID nomor telepon.',
      });
    }
  });

export type ReminderSettingsValues = z.infer<typeof reminderSettingsSchema>;
