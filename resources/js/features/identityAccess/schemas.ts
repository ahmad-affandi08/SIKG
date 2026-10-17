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
