import { z } from 'zod';

/** Uang selalu bilangan bulat rupiah (M4): teks digit saja, tanpa desimal atau pemisah. */
const rupiah = (label: string) =>
  z
    .string()
    .trim()
    .min(1, `${label} wajib diisi (isi 0 bila nol).`)
    .regex(/^\d{1,12}$/, `${label} berupa bilangan bulat rupiah, tanpa titik atau koma.`);

/** Umpan balik cepat di klien; server memvalidasi ulang dan berwenang (P2-06). */
export const labOrderSchema = z.object({
  doctor_id: z.string().min(1, 'Dokter wajib dipilih.'),
  laboratory_id: z.string().min(1, 'Laboratorium wajib dipilih.'),
  work_type: z.string().min(1, 'Jenis pekerjaan wajib dipilih.'),
  tooth_notes: z.string().trim().max(100, 'Terlalu panjang.'),
  description: z
    .string()
    .trim()
    .min(1, 'Uraian pekerjaan wajib diisi.')
    .max(500, 'Terlalu panjang.'),
  shade: z.string().trim().max(30, 'Terlalu panjang.'),
  due_date: z.string(),
  lab_cost_amount: rupiah('Biaya lab'),
  patient_charge_amount: rupiah('Harga ke pasien'),
});
export type LabOrderValues = z.infer<typeof labOrderSchema>;

export const sendSchema = z.object({ date: z.string().min(1, 'Tanggal kirim wajib diisi.') });

export const receiveSchema = z.object({
  date: z.string().min(1, 'Tanggal terima wajib diisi.'),
  lab_cost_amount: rupiah('Biaya lab aktual'),
});

export const cancelSchema = z.object({
  reason: z.string().trim().min(1, 'Alasan pembatalan wajib diisi.').max(255, 'Terlalu panjang.'),
});

export const LAB_TONE: Record<string, 'neutral' | 'success' | 'danger' | 'warning'> = {
  ordered: 'warning',
  sent: 'warning',
  received: 'success',
  cancelled: 'neutral',
};
