import { z } from 'zod';

/** Uang selalu bilangan bulat rupiah (M4): teks digit saja, tanpa desimal atau pemisah. */
const rupiah = (label: string, { min = 1 }: { min?: number } = {}) =>
  z
    .string()
    .trim()
    .min(1, `${label} wajib diisi.`)
    .regex(/^\d{1,12}$/, `${label} berupa bilangan bulat rupiah, tanpa titik atau koma.`)
    .refine((value) => Number(value) >= min, `${label} minimal ${String(min)}.`);

/** Umpan balik cepat di klien; server memvalidasi ulang dan berwenang (P2-02). */
export const policySchema = z
  .object({
    insurer_id: z.string().min(1, 'Penjamin wajib dipilih.'),
    policy_no: z
      .string()
      .trim()
      .min(1, 'Nomor polis atau kartu anggota wajib diisi.')
      .max(60, 'Terlalu panjang.'),
    ceiling_amount: z
      .string()
      .trim()
      .regex(/^(\d{1,12})?$/, 'Plafon berupa bilangan bulat rupiah, atau kosong bila tanpa batas.'),
    valid_from: z.string(),
    valid_to: z.string(),
    notes: z.string().trim().max(500, 'Terlalu panjang.'),
  })
  .refine((v) => v.valid_from === '' || v.valid_to === '' || v.valid_from <= v.valid_to, {
    path: ['valid_to'],
    message: 'Masa berlaku akhir tidak boleh sebelum awal.',
  });
export type PolicyValues = z.infer<typeof policySchema>;

export function claimSchema(maxAmount: number) {
  return z.object({
    insurer_id: z.string().min(1, 'Penjamin wajib dipilih.'),
    policy_id: z.string(),
    claimed_amount: rupiah('Jumlah klaim').refine(
      (v) => Number(v) <= maxAmount,
      `Jumlah klaim melebihi sisa tagihan yang dapat diklaim.`,
    ),
    notes: z.string().trim().max(500, 'Terlalu panjang.'),
  });
}
export type ClaimValues = z.infer<ReturnType<typeof claimSchema>>;

export function approveSchema(claimed: number) {
  return z.object({
    approved_amount: rupiah('Jumlah disetujui').refine(
      (v) => Number(v) <= claimed,
      'Jumlah disetujui tidak boleh melebihi jumlah diklaim.',
    ),
    note: z.string().trim().max(255, 'Terlalu panjang.'),
  });
}
export type ApproveValues = z.infer<ReturnType<typeof approveSchema>>;

export const rejectSchema = z.object({
  reason: z.string().trim().min(1, 'Alasan penolakan wajib diisi.').max(255, 'Terlalu panjang.'),
});

export function receiveSchema(remaining: number) {
  return z.object({
    payment_method_id: z.string().min(1, 'Pilih metode pembayaran.'),
    amount: rupiah('Jumlah pembayaran').refine(
      (v) => Number(v) <= remaining,
      'Pembayaran melebihi sisa yang disetujui penjamin.',
    ),
    reference_no: z.string().trim().max(100, 'Terlalu panjang.'),
  });
}
export type ReceiveValues = z.infer<ReturnType<typeof receiveSchema>>;

export const CLAIM_TONE: Record<string, 'neutral' | 'success' | 'danger' | 'warning'> = {
  draft: 'neutral',
  submitted: 'warning',
  approved: 'warning',
  rejected: 'danger',
  paid: 'success',
  cancelled: 'neutral',
};
