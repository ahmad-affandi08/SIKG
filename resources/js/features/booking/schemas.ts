import { z } from 'zod';

/** Umpan balik cepat di klien; server memvalidasi ulang dan berwenang (P3-01). */
export const bookingFormSchema = z.object({
  doctor_id: z.string().min(1, 'Pilih dokter.'),
  date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Pilih tanggal.'),
  time: z.string().regex(/^\d{2}:\d{2}$/, 'Pilih jam.'),
  name: z
    .string()
    .trim()
    .min(3, 'Nama lengkap minimal 3 karakter.')
    .max(120, 'Nama terlalu panjang.'),
  phone: z
    .string()
    .trim()
    .min(1, 'Nomor HP wajib diisi.')
    .regex(/^\+?[\d\s\-()]{8,20}$/, 'Nomor HP tidak valid.'),
  complaint: z.string().max(300, 'Keluhan maksimal 300 karakter.'),
  consent: z.boolean().refine((value) => value, {
    message: 'Setujui penggunaan nomor HP untuk konfirmasi janji.',
  }),
});
export type BookingFormValues = z.infer<typeof bookingFormSchema>;

export const verifyCodeSchema = z.object({
  code: z
    .string()
    .trim()
    .regex(/^\d{6}$/, 'Kode verifikasi terdiri dari 6 angka.'),
});

export const rejectSchema = z.object({
  reason: z
    .string()
    .trim()
    .min(1, 'Alasan penolakan wajib diisi.')
    .max(255, 'Alasan terlalu panjang.'),
});

export const bookingSettingsSchema = z.object({
  enabled: z.boolean(),
  slug: z
    .string()
    .trim()
    .refine((value) => value === '' || /^[a-z0-9][a-z0-9-]{1,58}[a-z0-9]$/.test(value), {
      message: 'Alamat berisi huruf kecil, angka, dan tanda hubung (3 sampai 60 karakter).',
    }),
  slot_minutes: z.string().regex(/^\d{2,3}$/, 'Durasi slot berupa menit bulat.'),
  horizon_days: z.string().regex(/^\d{1,2}$/, 'Batas hari berupa bilangan bulat.'),
  min_lead_hours: z.string().regex(/^\d{1,2}$/, 'Jeda minimum berupa jam bulat.'),
  hold_hours: z.string().regex(/^\d{1,3}$/, 'Masa tahan berupa jam bulat.'),
  max_active_per_phone: z.string().regex(/^\d{1,2}$/, 'Batas per nomor berupa bilangan bulat.'),
  max_requests_per_hour: z
    .string()
    .regex(/^\d{1,3}$/, 'Batas per jam berupa bilangan bulat.')
    .refine((value) => Number(value) >= 5 && Number(value) <= 500, {
      message: 'Batas per jam antara 5 dan 500.',
    }),
  otp_template: z.string().max(100, 'Nama templat terlalu panjang.'),
  welcome_text: z.string().max(500, 'Teks sambutan maksimal 500 karakter.'),
});
export type BookingSettingsValues = z.infer<typeof bookingSettingsSchema>;

export const BOOKING_TONE: Record<string, 'success' | 'danger' | 'warning' | 'neutral'> = {
  awaiting_code: 'warning',
  pending: 'warning',
  confirmed: 'success',
  rejected: 'danger',
  expired: 'neutral',
  cancelled: 'neutral',
};
