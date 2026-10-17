import { z } from 'zod';

export const prescriptionRowSchema = z
  .object({
    medicine_id: z.string(),
    free_text_name: z.string().trim().max(255, 'Terlalu panjang.'),
    dose: z.string().trim().min(1, 'Dosis wajib diisi.').max(100, 'Terlalu panjang.'),
    frequency: z.string().trim().min(1, 'Frekuensi wajib diisi.').max(100, 'Terlalu panjang.'),
    duration: z.string().trim().min(1, 'Durasi wajib diisi.').max(100, 'Terlalu panjang.'),
    instructions: z.string().trim().max(500, 'Terlalu panjang.'),
    quantity: z.coerce
      .number()
      .int('Jumlah harus bilangan bulat.')
      .min(1, 'Jumlah minimal 1.')
      .max(9999, 'Jumlah maksimal 9999.'),
    dispense_in_clinic: z.boolean(),
  })
  .superRefine((row, ctx) => {
    const hasCatalog = row.medicine_id !== '';
    const hasText = row.free_text_name.trim() !== '';
    if (hasCatalog === hasText) {
      ctx.addIssue({
        code: 'custom',
        path: ['medicine_id'],
        message: 'Pilih obat dari katalog atau tulis nama obat, salah satu.',
      });
    }
    if (row.dispense_in_clinic && !hasCatalog) {
      ctx.addIssue({
        code: 'custom',
        path: ['dispense_in_clinic'],
        message: 'Hanya obat dari katalog yang dapat dijual di klinik.',
      });
    }
  });

/** Input formulir: jumlah ditampung sebagai teks lalu dipaksa ke angka oleh skema. */
export type PrescriptionRow = Omit<z.input<typeof prescriptionRowSchema>, 'quantity'> & {
  quantity: string;
};

export const emptyRow = (): PrescriptionRow => ({
  medicine_id: '',
  free_text_name: '',
  dose: '',
  frequency: '',
  duration: '',
  instructions: '',
  quantity: '1',
  dispense_in_clinic: false,
});

export const stockMovementSchema = z
  .object({
    medicine_id: z.string().min(1, 'Obat wajib dipilih.'),
    type: z.enum(['in', 'out']),
    quantity: z.coerce
      .number()
      .int('Jumlah harus bilangan bulat.')
      .min(1, 'Jumlah minimal 1.')
      .max(1_000_000, 'Terlalu besar.'),
    batch_no: z.string().trim().max(50, 'Terlalu panjang.'),
    expiry_date: z
      .string()
      .refine(
        (v) => v === '' || /^\d{4}-\d{2}-\d{2}$/.test(v),
        'Tanggal kedaluwarsa harus berformat YYYY-MM-DD.',
      ),
    reason: z.string().trim().max(255, 'Terlalu panjang.'),
  })
  .superRefine((v, ctx) => {
    if (v.type === 'out' && v.reason === '') {
      ctx.addIssue({
        code: 'custom',
        path: ['reason'],
        message: 'Alasan stok keluar wajib diisi.',
      });
    }
  });

export const adjustmentSchema = z.object({
  medicine_id: z.string().min(1, 'Obat wajib dipilih.'),
  counted_quantity: z.coerce
    .number()
    .int('Jumlah harus bilangan bulat.')
    .min(0, 'Tidak boleh negatif.')
    .max(1_000_000, 'Terlalu besar.'),
  reason: z.string().trim().min(1, 'Alasan penyesuaian wajib diisi.').max(255, 'Terlalu panjang.'),
});

/** Status stok untuk daftar: menurun di bawah minimum diberi tanda teks, bukan warna saja. */
export function stockLevel(quantity: number, minStock: number): 'empty' | 'low' | 'ok' {
  if (quantity === 0) {
    return 'empty';
  }
  return quantity < minStock ? 'low' : 'ok';
}

/** Keterangan sisa hari kedaluwarsa untuk peringatan stok (OBT-06). */
export function expiryText(daysLeft: number): string {
  if (daysLeft < 0) {
    return `sudah lewat ${String(-daysLeft)} hari`;
  }
  return daysLeft === 0 ? 'kedaluwarsa hari ini' : `${String(daysLeft)} hari lagi`;
}
