import { z } from 'zod';

/** Umpan balik cepat di klien. Server memvalidasi ulang dan berwenang. */
export const loginSchema = z.object({
  identifier: z
    .string()
    .trim()
    .min(1, 'Email atau nama pengguna wajib diisi.')
    .max(255, 'Terlalu panjang.'),
  password: z.string().min(1, 'Kata sandi wajib diisi.').max(255, 'Terlalu panjang.'),
});

export type LoginValues = z.infer<typeof loginSchema>;

export const userFormSchema = z.object({
  name: z.string().trim().min(1, 'Nama wajib diisi.').max(255, 'Terlalu panjang.'),
  email: z.string().trim().min(1, 'Email wajib diisi.').pipe(z.email('Format email tidak valid.')),
  username: z
    .string()
    .trim()
    .regex(
      /^[A-Za-z0-9._-]{3,50}$/,
      'Nama pengguna 3-50 karakter: huruf, angka, titik, garis bawah, atau tanda hubung.',
    ),
  role: z.string().min(1, 'Peran wajib dipilih.'),
  branch_id: z.string(),
});

export type UserFormValues = z.infer<typeof userFormSchema>;

export const PASSWORD_MIN_LENGTH = 10;

/** Aturan kata sandi baru (AUTH-06); server memvalidasi ulang dan berwenang. */
export const changePasswordSchema = z
  .object({
    current_password: z
      .string()
      .min(1, 'Kata sandi saat ini wajib diisi.')
      .max(255, 'Terlalu panjang.'),
    password: z
      .string()
      .min(1, 'Kata sandi baru wajib diisi.')
      .min(PASSWORD_MIN_LENGTH, `Kata sandi baru minimal ${String(PASSWORD_MIN_LENGTH)} karakter.`)
      .max(255, 'Terlalu panjang.')
      .regex(/\p{L}/u, 'Kata sandi baru harus memuat huruf.')
      .regex(/\d/, 'Kata sandi baru harus memuat angka.'),
    password_confirmation: z.string(),
  })
  .refine((v) => v.password === v.password_confirmation, {
    path: ['password_confirmation'],
    message: 'Konfirmasi kata sandi tidak sama.',
  })
  .refine((v) => v.password !== v.current_password, {
    path: ['password'],
    message: 'Kata sandi baru harus berbeda dari kata sandi saat ini.',
  });

export type ChangePasswordValues = z.infer<typeof changePasswordSchema>;

/** Kode faktor kedua: 6 digit dari autentikator atau kode pemulihan; server memeriksa keabsahannya (AUTH-05). */
export const twoFactorCodeSchema = z
  .string()
  .trim()
  .min(1, 'Kode verifikasi wajib diisi.')
  .max(32, 'Kode terlalu panjang.');

export const twoFactorChangeSchema = z.object({
  password: z.string().min(1, 'Kata sandi wajib diisi.').max(255, 'Terlalu panjang.'),
  code: twoFactorCodeSchema,
});

export type TwoFactorChangeValues = z.infer<typeof twoFactorChangeSchema>;
