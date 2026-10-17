import { z } from 'zod';

/** Uang selalu bilangan bulat rupiah (M4): teks digit saja, tanpa desimal atau pemisah. */
const rupiahText = (label: string) =>
  z
    .string()
    .trim()
    .min(1, `${label} wajib diisi.`)
    .regex(/^\d{1,10}$/, `${label} berupa bilangan bulat rupiah, tanpa titik atau koma.`);

const countText = (label: string, min: number) =>
  z
    .string()
    .trim()
    .min(1, `${label} wajib diisi.`)
    .regex(/^\d{1,7}$/, `${label} berupa bilangan bulat.`)
    .refine((value) => Number(value) >= min, `${label} minimal ${String(min)}.`);

/** Umpan balik cepat di klien; server memvalidasi ulang dan berwenang (P2-05). */
export const purchaseLineSchema = z.object({
  medicine_id: z.string().min(1, 'Obat wajib dipilih.'),
  quantity: countText('Jumlah', 1),
  unit_cost_amount: rupiahText('Harga beli'),
});
export type PurchaseLineValues = z.infer<typeof purchaseLineSchema>;

export const purchaseOrderSchema = z
  .object({
    supplier_id: z.string().min(1, 'Pemasok wajib dipilih.'),
    expected_date: z.string(),
    note: z.string().trim().max(255, 'Terlalu panjang.'),
    lines: z.array(purchaseLineSchema).min(1, 'Tambahkan minimal satu obat.'),
  })
  .superRefine((value, ctx) => {
    const seen = new Set<string>();
    value.lines.forEach((line, index) => {
      if (line.medicine_id !== '' && seen.has(line.medicine_id)) {
        ctx.addIssue({
          code: 'custom',
          path: ['lines', index, 'medicine_id'],
          message: 'Obat ini sudah ada di pesanan.',
        });
      }
      seen.add(line.medicine_id);
    });
  });
export type PurchaseOrderValues = z.infer<typeof purchaseOrderSchema>;

export interface ReceiveLineValues {
  item_id: string;
  quantity: string;
  batch_no: string;
  expiry_date: string;
}

/** Penerimaan: jumlah 0 atau kosong berarti baris dilewati; tidak boleh melebihi sisa pesanan. */
export function receiveLinesError(
  lines: readonly ReceiveLineValues[],
  outstanding: Readonly<Record<string, number>>,
): Record<number, string> {
  const errors: Record<number, string> = {};
  lines.forEach((line, index) => {
    const text = line.quantity.trim();
    if (text === '' || text === '0') {
      return;
    }
    if (!/^\d{1,7}$/.test(text)) {
      errors[index] = 'Jumlah berupa bilangan bulat.';
    } else if (Number(text) > (outstanding[line.item_id] ?? 0)) {
      errors[index] = `Melebihi sisa pesanan (${String(outstanding[line.item_id] ?? 0)}).`;
    }
  });
  return errors;
}

export function hasReceivedLine(lines: readonly ReceiveLineValues[]): boolean {
  return lines.some((line) => line.quantity.trim() !== '' && line.quantity.trim() !== '0');
}

export const scheduleSchema = z.object({
  enabled: z.boolean(),
  interval_days: countText('Interval', 7).refine(
    (value) => Number(value) <= 365,
    'Interval maksimal 365 hari.',
  ),
});

export const materialSchema = z.object({
  procedure_id: z.string().min(1, 'Tindakan wajib dipilih.'),
  medicine_id: z.string().min(1, 'Obat atau bahan wajib dipilih.'),
  quantity: countText('Jumlah', 1).refine((value) => Number(value) <= 1000, 'Maksimal 1000.'),
});

export const PURCHASE_TONE: Record<string, 'neutral' | 'success' | 'danger' | 'warning'> = {
  draft: 'neutral',
  ordered: 'warning',
  partial: 'warning',
  received: 'success',
  cancelled: 'neutral',
};
