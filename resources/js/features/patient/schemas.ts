import { z } from 'zod';

const NIK = /^\d{16}$/;
const PHONE = /^(\+?62|0)8\d{7,12}$/;
const ADULT_AGE = 18;

/** Umur penuh pada tanggal `today`, dari tanggal lahir YYYY-MM-DD. */
export function ageOn(birthDate: string, today: Date): number {
  const [y, m, d] = birthDate.split('-').map(Number);
  if (y === undefined || m === undefined || d === undefined) {
    return Number.NaN;
  }
  let age = today.getFullYear() - y;
  const beforeBirthday =
    today.getMonth() + 1 < m || (today.getMonth() + 1 === m && today.getDate() < d);
  if (beforeBirthday) {
    age -= 1;
  }
  return age;
}

function phoneIssue(value: string): string | null {
  const text = value.replace(/[\s\-().]/g, '');
  return text === '' || PHONE.test(text) ? null : 'Nomor HP tidak valid (contoh 08123456789).';
}

/**
 * Umpan balik cepat di klien; server memvalidasi ulang dan berwenang (aturan NIK, wali, duplikat).
 * `requireConsent` hanya untuk pendaftaran baru.
 */
export function buildPatientSchema(options: { requireConsent: boolean; today?: Date }) {
  return z
    .object({
      name: z.string().trim().min(1, 'Nama pasien wajib diisi.').max(255, 'Terlalu panjang.'),
      nik: z.string(),
      nik_missing_reason: z.string().trim().max(255, 'Terlalu panjang.'),
      birth_date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Tanggal lahir wajib diisi.'),
      sex: z.string().min(1, 'Jenis kelamin wajib dipilih.'),
      address: z.string().trim().max(1000, 'Terlalu panjang.'),
      phone: z.string(),
      occupation: z.string().trim().max(100, 'Terlalu panjang.'),
      emergency_name: z.string().trim().max(255, 'Terlalu panjang.'),
      emergency_relation: z.string().trim().max(50, 'Terlalu panjang.'),
      emergency_phone: z.string(),
      guardian_name: z.string().trim().max(255, 'Terlalu panjang.'),
      guardian_relation: z.string().trim().max(50, 'Terlalu panjang.'),
      guardian_phone: z.string(),
      consent: z.boolean(),
    })
    .superRefine((v, ctx) => {
      const nik = v.nik.replace(/\s+/g, '');
      if (nik === '' && v.nik_missing_reason.trim() === '') {
        ctx.addIssue({
          code: 'custom',
          path: ['nik'],
          message: 'NIK wajib diisi. Bila belum ada (bayi atau WNA), tulis alasannya.',
        });
      } else if (nik !== '' && !NIK.test(nik)) {
        ctx.addIssue({ code: 'custom', path: ['nik'], message: 'NIK harus 16 digit angka.' });
      }

      for (const key of ['phone', 'emergency_phone', 'guardian_phone'] as const) {
        const issue = phoneIssue(v[key]);
        if (issue) {
          ctx.addIssue({ code: 'custom', path: [key], message: issue });
        }
      }

      if (v.emergency_name.trim() !== '' && v.emergency_phone.trim() === '') {
        ctx.addIssue({
          code: 'custom',
          path: ['emergency_phone'],
          message: 'Nomor HP kontak darurat wajib diisi.',
        });
      }

      const age = ageOn(v.birth_date, options.today ?? new Date());
      if (age < ADULT_AGE && v.guardian_name.trim() === '') {
        ctx.addIssue({
          code: 'custom',
          path: ['guardian_name'],
          message: 'Pasien di bawah 18 tahun wajib memiliki data wali.',
        });
      }
      if (v.guardian_name.trim() !== '' && v.guardian_relation.trim() === '') {
        ctx.addIssue({
          code: 'custom',
          path: ['guardian_relation'],
          message: 'Hubungan wali dengan pasien wajib diisi.',
        });
      }

      if (options.requireConsent && !v.consent) {
        ctx.addIssue({
          code: 'custom',
          path: ['consent'],
          message: 'Persetujuan pemrosesan data pasien wajib dicatat.',
        });
      }
    });
}

export type PatientFormValues = z.infer<ReturnType<typeof buildPatientSchema>>;

export const historyFormSchema = z.object({
  type: z.string().min(1, 'Jenis riwayat wajib dipilih.'),
  name: z.string().trim().min(1, 'Nama riwayat wajib diisi.').max(255, 'Terlalu panjang.'),
  severity: z.string(),
  notes: z.string().trim().max(1000, 'Terlalu panjang.'),
});

export type HistoryFormValues = z.infer<typeof historyFormSchema>;

/**
 * JDW-07: kontak pengingat. Persetujuan pengingat butuh sedikitnya satu saluran; Telegram yang sudah tertaut dihitung dari
 * server (`telegramLinked`), sedangkan email diperiksa di sini.
 */
export function buildContactSchema(options: { telegramLinked: boolean }) {
  return z
    .object({
      email: z.string().trim().max(255, 'Terlalu panjang.'),
      reminders_opt_in: z.boolean(),
    })
    .superRefine((v, ctx) => {
      if (v.email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.email)) {
        ctx.addIssue({ code: 'custom', path: ['email'], message: 'Alamat email tidak valid.' });
      }
      if (v.reminders_opt_in && v.email === '' && !options.telegramLinked) {
        ctx.addIssue({
          code: 'custom',
          path: ['reminders_opt_in'],
          message: 'Isi email atau tautkan Telegram dahulu agar pengingat dapat dikirim.',
        });
      }
    });
}

export type ContactFormValues = z.infer<ReturnType<typeof buildContactSchema>>;
