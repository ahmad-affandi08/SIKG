import { z } from 'zod';

/** Uang selalu bilangan bulat rupiah (M4): teks digit saja, tanpa desimal atau pemisah. */
const rupiah = (label: string, { min = 1 }: { min?: number } = {}) =>
  z
    .string()
    .trim()
    .min(1, `${label} wajib diisi.`)
    .regex(/^\d{1,12}$/, `${label} berupa bilangan bulat rupiah, tanpa titik atau koma.`)
    .refine((value) => Number(value) >= min, `${label} minimal ${String(min)}.`);

export const invoiceItemSchema = z.object({
  description: z.string().trim().min(1, 'Uraian item wajib diisi.').max(255, 'Terlalu panjang.'),
  quantity: z
    .string()
    .trim()
    .regex(/^\d{1,4}$/, 'Jumlah berupa bilangan bulat 1 sampai 9999.')
    .refine((v) => Number(v) >= 1, 'Jumlah minimal 1.'),
  unit_price_amount: rupiah('Harga satuan', { min: 0 }),
});
export type InvoiceItemValues = z.infer<typeof invoiceItemSchema>;

export const quantitySchema = invoiceItemSchema.pick({ quantity: true });

/** Persen berformat 10 atau 12,5 (maksimal dua desimal); nominal bilangan bulat rupiah. */
export const discountSchema = z
  .object({
    kind: z.enum(['percent', 'amount', 'none']),
    value: z.string().trim(),
    reason: z.string().trim().max(255, 'Terlalu panjang.'),
  })
  .superRefine((d, ctx) => {
    if (d.kind === 'none') {
      return;
    }
    if (d.kind === 'percent' && !/^(\d{1,3})([.,]\d{1,2})?$/.test(d.value)) {
      ctx.addIssue({
        code: 'custom',
        path: ['value'],
        message: 'Isi persen, contoh 10 atau 12,5.',
      });
    }
    if (d.kind === 'amount' && !/^\d{1,12}$/.test(d.value)) {
      ctx.addIssue({
        code: 'custom',
        path: ['value'],
        message: 'Diskon nominal berupa bilangan bulat rupiah.',
      });
    }
    if (d.reason === '') {
      ctx.addIssue({ code: 'custom', path: ['reason'], message: 'Alasan diskon wajib diisi.' });
    }
  });
export type DiscountValues = z.infer<typeof discountSchema>;

export const paymentSchema = z.object({
  payment_method_id: z.string().min(1, 'Pilih metode pembayaran.'),
  type: z.enum(['pay', 'dp']),
  amount: rupiah('Jumlah'),
  reference_no: z.string().trim().max(100, 'Terlalu panjang.'),
});
export type PaymentValues = z.infer<typeof paymentSchema>;

export const refundSchema = z.object({
  payment_method_id: z.string().min(1, 'Pilih metode pengembalian.'),
  amount: rupiah('Jumlah'),
  reason: z.string().trim().min(1, 'Alasan pengembalian wajib diisi.').max(255, 'Terlalu panjang.'),
});
export type RefundValues = z.infer<typeof refundSchema>;

export const voidSchema = z.object({
  reason: z.string().trim().min(1, 'Alasan pembatalan wajib diisi.').max(255, 'Terlalu panjang.'),
});

export const cashClosingSchema = z.object({
  counted_amount: rupiah('Uang fisik', { min: 0 }),
  note: z.string().trim().max(255, 'Terlalu panjang.'),
});
export type CashClosingValues = z.infer<typeof cashClosingSchema>;

export interface InvoiceHead {
  id: string;
  number: string | null;
  lifecycle?: string;
  status?: string;
  patient_name: string | null;
  medical_record_no: string | null;
  subtotal_amount?: number;
  total_amount: number;
  paid_amount?: number;
  balance_amount?: number;
}

export interface InvoiceItemRow {
  id: string;
  source_type: string;
  description: string;
  quantity: number;
  unit_price_amount: number;
  gross_amount: number;
  discount_amount: number;
  discount_type: 'percent' | 'amount' | null;
  discount_value: number | null;
  discount_reason: string | null;
  total_amount: number;
  manual: boolean;
}

export interface PaymentRow {
  id: string;
  type: 'pay' | 'dp' | 'refund';
  type_label: string;
  method_id: string;
  method_name: string | null;
  amount: number;
  paid_at: string;
  received_by_name: string | null;
  reference_no: string | null;
  receipt_no: string | null;
  reason: string | null;
}

export const STATUS_TONE = {
  draft: 'neutral',
  unpaid: 'warning',
  partial: 'warning',
  paid: 'success',
  void: 'danger',
} as const;

export type InvoiceStatus = keyof typeof STATUS_TONE;

/** Basis poin (1% = 100) sebagai teks persen, contoh 1250 menjadi "12,5%". */
export function percentText(basisPoints: number): string {
  const whole = Math.floor(basisPoints / 100);
  const frac = basisPoints % 100;
  if (frac === 0) {
    return `${String(whole)}%`;
  }
  return `${String(whole)},${String(frac).padStart(2, '0').replace(/0$/, '')}%`;
}
