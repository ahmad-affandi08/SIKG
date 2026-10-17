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
