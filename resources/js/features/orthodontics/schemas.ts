import { z } from 'zod';

const rupiah = (label: string, { min = 0 }: { min?: number } = {}) =>
  z
    .string()
    .trim()
    .regex(/^\d{1,12}$/, `${label} berupa bilangan bulat rupiah, tanpa titik atau koma.`)
    .refine((value) => Number(value) >= min, `${label} minimal ${String(min)}.`);

/** Umpan balik cepat di klien; server memvalidasi ulang dan berwenang (P2-04). */
export const caseSchema = z
  .object({
    doctor_id: z.string().min(1, 'Dokter wajib dipilih.'),
    appliance: z.string().min(1, 'Jenis alat wajib dipilih.'),
    title: z.string().trim().min(1, 'Nama paket wajib diisi.').max(150, 'Terlalu panjang.'),
    plan_notes: z.string().trim().max(1000, 'Terlalu panjang.'),
    total_fee_amount: rupiah('Biaya paket', { min: 1 }),
    down_payment_amount: z
      .string()
      .trim()
      .regex(/^(\d{1,12})?$/, 'Uang muka berupa bilangan bulat rupiah.'),
    installment_count: z
      .string()
      .trim()
      .regex(/^\d{1,2}$/, 'Jumlah cicilan berupa bilangan bulat 1 sampai 36.')
      .refine((v) => Number(v) >= 1 && Number(v) <= 36, 'Jumlah cicilan 1 sampai 36.'),
    start_date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Tanggal mulai wajib diisi.'),
    expected_months: z
      .string()
      .trim()
      .regex(/^\d{1,2}$/, 'Perkiraan lama berupa bilangan bulat bulan.')
      .refine((v) => Number(v) >= 1 && Number(v) <= 60, 'Perkiraan lama 1 sampai 60 bulan.'),
  })
  .superRefine((v, ctx) => {
    const total = Number(v.total_fee_amount);
    const down = v.down_payment_amount === '' ? 0 : Number(v.down_payment_amount);
    const count = Number(v.installment_count);
    if (down > total) {
      ctx.addIssue({
        code: 'custom',
        path: ['down_payment_amount'],
        message: 'Uang muka melebihi biaya paket.',
      });
    } else if (down > 0 && down < total && count < 2) {
      ctx.addIssue({
        code: 'custom',
        path: ['installment_count'],
        message: 'Dengan uang muka, cicilan minimal 2 (uang muka dan sisanya).',
      });
    }
  });
export type CaseValues = z.infer<typeof caseSchema>;

export const controlSchema = z
  .object({
    control_date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Tanggal kontrol wajib diisi.'),
    encounter_id: z.string(),
    wire_or_tray: z.string().trim().max(100, 'Terlalu panjang.'),
    adjustments: z.string().trim().max(1000, 'Terlalu panjang.'),
    notes: z.string().trim().max(1000, 'Terlalu panjang.'),
    next_control_date: z.string(),
  })
  .superRefine((v, ctx) => {
    if (v.wire_or_tray === '' && v.adjustments === '' && v.notes === '') {
      ctx.addIssue({
        code: 'custom',
        path: ['adjustments'],
        message: 'Isi sedikitnya satu dari kawat atau tray, penyesuaian, atau catatan.',
      });
    }
    if (v.next_control_date !== '' && v.next_control_date < v.control_date) {
      ctx.addIssue({
        code: 'custom',
        path: ['next_control_date'],
        message: 'Kontrol berikutnya tidak boleh sebelum tanggal kontrol ini.',
      });
    }
  });
export type ControlValues = z.infer<typeof controlSchema>;

/** Perkiraan cicilan untuk pratinjau; angka akhir dihitung server dengan aturan yang sama. */
export function previewInstallments(total: number, count: number, down: number): number[] {
  if (total <= 0 || count < 1) {
    return [];
  }
  const amounts: number[] = [];
  let rest = total;
  let parts = count;
  if (down > 0) {
    amounts.push(down);
    rest = total - down;
    parts = count - 1;
  }
  if (parts > 0) {
    const each = Math.floor(rest / parts);
    for (let i = 1; i <= parts; i++) {
      amounts.push(i === parts ? rest - each * (parts - 1) : each);
    }
  }
  return amounts;
}
