import { z } from 'zod';

/** Umpan balik cepat di klien; server memvalidasi ulang dan berwenang (P2-08). Uang bilangan bulat rupiah (M4). */
export function expenseSchema(today: string) {
  return z.object({
    expense_date: z
      .string()
      .min(1, 'Tanggal biaya wajib diisi.')
      .refine((value) => value <= today, 'Tanggal biaya tidak boleh di masa depan.'),
    category_id: z.string().min(1, 'Kategori biaya wajib dipilih.'),
    amount: z
      .string()
      .trim()
      .min(1, 'Jumlah biaya wajib diisi.')
      .regex(/^\d{1,12}$/, 'Jumlah biaya berupa bilangan bulat rupiah, tanpa titik atau koma.')
      .refine((value) => Number(value) >= 1, 'Jumlah biaya harus lebih dari nol.'),
    payee: z.string().trim().max(150, 'Terlalu panjang.'),
    description: z
      .string()
      .trim()
      .min(1, 'Keterangan biaya wajib diisi.')
      .max(255, 'Terlalu panjang.'),
    reference_no: z.string().trim().max(60, 'Terlalu panjang.'),
  });
}
export type ExpenseValues = z.infer<ReturnType<typeof expenseSchema>>;

export const voidSchema = z.object({
  reason: z.string().trim().min(1, 'Alasan pembatalan wajib diisi.').max(255, 'Terlalu panjang.'),
});
