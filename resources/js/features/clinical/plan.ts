import { z } from 'zod';

/** RM-06: aturan formulir rencana perawatan; server memeriksa ulang dengan aturan yang sama. */
export const planFormSchema = z.object({
  title: z.string().trim().min(1, 'Judul rencana wajib diisi.').max(150, 'Terlalu panjang.'),
  notes: z.string().trim().max(1000, 'Terlalu panjang.'),
});
export type PlanFormValues = z.infer<typeof planFormSchema>;

export const planItemFormSchema = z.object({
  procedure_id: z.string().min(1, 'Tindakan wajib dipilih.'),
  tooth_fdi: z
    .string()
    .trim()
    .regex(/^(\d{2})?$/, 'Nomor gigi FDI terdiri dari dua angka.'),
  quantity: z
    .string()
    .trim()
    .regex(/^\d{1,2}$/, 'Jumlah berupa bilangan bulat 1 sampai 99.')
    .refine((v) => Number(v) >= 1, 'Jumlah minimal 1.'),
  notes: z.string().trim().max(500, 'Terlalu panjang.'),
});
export type PlanItemFormValues = z.infer<typeof planItemFormSchema>;

export interface PlanItem {
  id: string;
  sequence: number;
  code: string;
  name: string;
  tooth_fdi: number | null;
  quantity: number;
  unit_price_amount: number;
  estimate_amount: number;
  status: 'planned' | 'in_progress' | 'done';
  status_label: string;
  notes: string | null;
  encounter_id: string | null;
  completed_at: string | null;
}

export interface Plan {
  id: string;
  title: string;
  notes: string | null;
  status: 'planned' | 'in_progress' | 'done' | 'cancelled';
  status_label: string;
  created_at: string;
  estimate_amount: number;
  remaining_amount: number;
  items: PlanItem[];
}

/** Ringkasan kemajuan "2 dari 5 langkah selesai". */
export function progressText(items: readonly PlanItem[]): string {
  if (items.length === 0) {
    return 'Belum ada langkah';
  }
  const done = items.filter((i) => i.status === 'done').length;
  return `${String(done)} dari ${String(items.length)} langkah selesai`;
}

/** Status berikutnya yang wajar untuk tombol cepat; langkah selesai tidak punya status berikutnya. */
export function nextStatus(status: PlanItem['status']): PlanItem['status'] | null {
  if (status === 'planned') return 'in_progress';
  if (status === 'in_progress') return 'done';
  return null;
}
