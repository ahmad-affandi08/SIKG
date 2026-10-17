import { z } from 'zod';

/** Aturan formulir penggabungan pasien (PAS-07); server memeriksa ulang dengan aturan yang sama. */
export const mergeFormSchema = z.object({
  reason: z
    .string()
    .trim()
    .min(5, 'Alasan penggabungan minimal 5 karakter.')
    .max(255, 'Terlalu panjang.'),
  confirm: z.literal(true, {
    error: 'Centang konfirmasi bahwa penggabungan tidak dapat dibatalkan lewat aplikasi.',
  }),
});
export type MergeFormValues = { reason: string; confirm: boolean };

export interface MergeCount {
  label: string;
  count: number;
}

/** Ringkasan satu kalimat jumlah data yang akan berpindah, hanya yang jumlahnya lebih dari nol. */
export function summarizeCounts(counts: readonly MergeCount[]): string {
  const parts = counts
    .filter((c) => c.count > 0)
    .map((c) => `${c.label.toLowerCase()} ${String(c.count)}`);
  return parts.length === 0
    ? 'Pasien ganda belum memiliki data yang perlu dipindahkan.'
    : parts.join(', ');
}
