import { z } from 'zod';

const SAFE_ID = /^[A-Za-z0-9-]*$/;

/** Umpan balik cepat di klien; server memvalidasi ulang dan berwenang (P2-01). */
export const satusehatSettingsSchema = z
  .object({
    enabled: z.boolean(),
    environment: z.enum(['staging', 'production']),
    organization_id: z
      .string()
      .trim()
      .max(64, 'Terlalu panjang.')
      .regex(SAFE_ID, 'Hanya huruf, angka, dan tanda hubung.'),
    location_id: z
      .string()
      .trim()
      .max(64, 'Terlalu panjang.')
      .regex(SAFE_ID, 'Hanya huruf, angka, dan tanda hubung.'),
    location_name: z.string().trim().max(150, 'Terlalu panjang.'),
    client_id: z.string().trim().max(128, 'Terlalu panjang.'),
    client_secret: z.string().max(255, 'Terlalu panjang.'),
  })
  .superRefine((v, ctx) => {
    if (!v.enabled) {
      return;
    }
    const need: [keyof typeof v, string][] = [
      ['organization_id', 'ID organisasi wajib diisi untuk mengaktifkan.'],
      ['location_id', 'ID lokasi wajib diisi untuk mengaktifkan.'],
      ['client_id', 'Client ID wajib diisi untuk mengaktifkan.'],
    ];
    for (const [key, message] of need) {
      if (String(v[key]).trim() === '') {
        ctx.addIssue({ code: 'custom', path: [key], message });
      }
    }
  });

export type SatusehatSettingsValues = z.infer<typeof satusehatSettingsSchema>;

/** NIK dokter boleh kosong (menghapus); bila diisi harus 16 digit. */
export function nikIssue(value: string): string | null {
  const digits = value.replace(/\s+/g, '');
  return digits === '' || /^\d{16}$/.test(digits) ? null : 'NIK harus 16 digit angka.';
}
