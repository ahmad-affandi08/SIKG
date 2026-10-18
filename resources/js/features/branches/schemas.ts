import { z } from 'zod';

/** Umpan balik cepat di klien; server memvalidasi ulang dan berwenang (P3-04). */
export const transferSchema = z
  .object({
    medicine_id: z.string().min(1, 'Pilih obat atau bahan.'),
    from_branch_id: z.string(),
    to_branch_id: z.string(),
    quantity: z
      .string()
      .trim()
      .regex(/^\d{1,7}$/, 'Jumlah berupa bilangan bulat.')
      .refine((value) => Number(value) >= 1, { message: 'Jumlah harus lebih dari nol.' }),
    reason: z.string().max(255, 'Alasan maksimal 255 karakter.'),
  })
  .refine((value) => value.from_branch_id !== value.to_branch_id, {
    path: ['to_branch_id'],
    message: 'Cabang asal dan tujuan harus berbeda.',
  });
export type TransferValues = z.infer<typeof transferSchema>;

/** Nilai pilihan "gudang pusat" (stok tanpa cabang) pada formulir. */
export const CENTRAL = '';
