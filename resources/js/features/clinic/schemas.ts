import { z } from 'zod';

const hour = z.string().regex(/^([01]\d|2[0-3]):[0-5]\d$/, 'Jam harus berformat HH:MM.');

const dayHours = z
  .object({ open: hour, close: hour })
  .refine((day) => day.open < day.close, { message: 'Jam buka harus lebih awal dari jam tutup.' })
  .nullable();

const numberPattern = z
  .string()
  .min(1, 'Format wajib diisi.')
  .max(40, 'Maksimal 40 karakter.')
  .regex(
    /^(?:[A-Za-z0-9/_.-]|\{(?:seq(?::\d{1,2})?|yyyy|yy|mm|dd)\})*$/,
    'Hanya huruf, angka, / _ . - dan token {seq}, {yyyy}, {yy}, {mm}, {dd}.',
  )
  .refine((value) => (value.match(/\{seq(?::\d{1,2})?\}/g) ?? []).length === 1, {
    message: 'Harus memuat tepat satu token {seq}.',
  });

/** Umpan balik cepat di klien. Server memvalidasi ulang dan berwenang. */
export const clinicSettingsSchema = z.object({
  timezone: z.string().min(1, 'Zona waktu wajib dipilih.'),
  session_timeout_minutes: z.number().int('Harus bilangan bulat.').min(5, 'Minimal 5 menit.'),
  medical_record_pattern: numberPattern,
  invoice_pattern: numberPattern,
  tax_percent: z
    .number()
    .int('Harus bilangan bulat.')
    .min(0, 'Minimal 0.')
    .max(100, 'Maksimal 100.'),
  discount_limits: z.object({
    pemilik: z.number().int('Harus bilangan bulat.').min(0, 'Minimal 0.').max(100, 'Maksimal 100.'),
    admin: z.number().int('Harus bilangan bulat.').min(0, 'Minimal 0.').max(100, 'Maksimal 100.'),
    kasir: z.number().int('Harus bilangan bulat.').min(0, 'Minimal 0.').max(100, 'Maksimal 100.'),
  }),
  operating_hours: z.record(z.string(), dayHours),
});

export type ClinicSettingsValues = z.infer<typeof clinicSettingsSchema>;

export const holidaySchema = z.object({
  date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Tanggal wajib diisi.'),
  name: z.string().trim().min(1, 'Nama hari libur wajib diisi.').max(150, 'Terlalu panjang.'),
});

export type HolidayValues = z.infer<typeof holidaySchema>;

export const WEEKDAYS: readonly { day: string; label: string }[] = [
  { day: '1', label: 'Senin' },
  { day: '2', label: 'Selasa' },
  { day: '3', label: 'Rabu' },
  { day: '4', label: 'Kamis' },
  { day: '5', label: 'Jumat' },
  { day: '6', label: 'Sabtu' },
  { day: '7', label: 'Minggu' },
];

export const clinicProfileSchema = z.object({
  name: z.string().trim().min(1, 'Nama klinik wajib diisi.').max(255, 'Terlalu panjang.'),
  address: z.string().trim().max(1000, 'Terlalu panjang.'),
  city: z.string().trim().max(100, 'Terlalu panjang.'),
  license_no: z.string().trim().max(100, 'Terlalu panjang.'),
  receipt_header: z.string().trim().max(500, 'Terlalu panjang.'),
  prescription_header: z.string().trim().max(500, 'Terlalu panjang.'),
});

export type ClinicProfileValues = z.infer<typeof clinicProfileSchema>;

export const LOGO_MAX_BYTES = 1024 * 1024;
export const LOGO_TYPES: readonly string[] = ['image/png', 'image/jpeg'];

/** Umpan balik cepat untuk berkas logo; server memeriksa isi berkas dan berwenang. */
export function logoFileIssue(file: { type: string; size: number }): string | null {
  if (!LOGO_TYPES.includes(file.type)) {
    return 'Logo harus berformat PNG atau JPG.';
  }
  if (file.size > LOGO_MAX_BYTES) {
    return 'Ukuran logo maksimal 1 MB.';
  }
  return null;
}
