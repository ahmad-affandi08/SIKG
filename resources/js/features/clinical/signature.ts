import { z } from 'zod';

/** Umpan balik cepat di klien; server memvalidasi ulang gambar tanda tangan dan berwenang (P2-07). */
export const signSchema = z
  .object({
    signer_name: z
      .string()
      .trim()
      .min(1, 'Nama penanda tangan wajib diisi.')
      .max(150, 'Terlalu panjang.'),
    signer_role: z.enum(['patient', 'guardian'], { message: 'Pilih pasien atau wali pasien.' }),
    signature: z.string().min(1, 'Tanda tangan wajib diisi.'),
    witness_name: z.string().trim().max(150, 'Terlalu panjang.'),
    witness_signature: z.string(),
  })
  .superRefine((value, ctx) => {
    const hasName = value.witness_name.trim() !== '';
    const hasImage = value.witness_signature !== '';
    if (hasName && !hasImage) {
      ctx.addIssue({
        code: 'custom',
        path: ['witness_signature'],
        message: 'Tanda tangan saksi wajib diisi bila nama saksi diisi.',
      });
    }
    if (!hasName && hasImage) {
      ctx.addIssue({
        code: 'custom',
        path: ['witness_name'],
        message: 'Nama saksi wajib diisi bila saksi menandatangani.',
      });
    }
  });
export type SignValues = z.infer<typeof signSchema>;

/** Jarak minimum (piksel kanvas) antartitik agar goresan halus tanpa menyimpan titik berlebihan. */
export const MIN_POINT_DISTANCE = 1.5;

/** Menilai apakah goresan layak disebut tanda tangan: minimal beberapa titik yang membentang. */
export function looksLikeSignature(points: readonly { x: number; y: number }[]): boolean {
  if (points.length < 8) {
    return false;
  }
  const xs = points.map((p) => p.x);
  const ys = points.map((p) => p.y);
  const width = Math.max(...xs) - Math.min(...xs);
  const height = Math.max(...ys) - Math.min(...ys);
  return width >= 20 || height >= 20;
}
