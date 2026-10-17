import { describe, expect, it } from 'vitest';

import {
  hasReceivedLine,
  materialSchema,
  purchaseOrderSchema,
  receiveLinesError,
  scheduleSchema,
} from '@/features/pharmacy/purchasing';

const line = { medicine_id: 'M1', quantity: '10', unit_cost_amount: '2500' };

describe('purchaseOrderSchema', () => {
  it('P2-05 menerima pesanan lengkap', () => {
    const result = purchaseOrderSchema.safeParse({
      supplier_id: 'S1',
      expected_date: '',
      note: '',
      lines: [line],
    });
    expect(result.success).toBe(true);
  });

  it('P2-05 menolak pemasok kosong, tanpa baris, dan harga desimal', () => {
    const noSupplier = purchaseOrderSchema.safeParse({
      supplier_id: '',
      expected_date: '',
      note: '',
      lines: [line],
    });
    const noLines = purchaseOrderSchema.safeParse({
      supplier_id: 'S1',
      expected_date: '',
      note: '',
      lines: [],
    });
    const decimal = purchaseOrderSchema.safeParse({
      supplier_id: 'S1',
      expected_date: '',
      note: '',
      lines: [{ ...line, unit_cost_amount: '12,5' }],
    });
    expect([noSupplier.success, noLines.success, decimal.success]).toEqual([false, false, false]);
  });

  it('P2-05 menolak obat ganda dalam satu pesanan', () => {
    const result = purchaseOrderSchema.safeParse({
      supplier_id: 'S1',
      expected_date: '',
      note: '',
      lines: [line, line],
    });
    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues[0]?.path).toEqual(['lines', 1, 'medicine_id']);
    }
  });
});

describe('receiveLinesError', () => {
  const outstanding = { I1: 5, I2: 3 };

  it('P2-05 melewati baris kosong atau nol dan menolak yang melebihi sisa', () => {
    const errors = receiveLinesError(
      [
        { item_id: 'I1', quantity: '6', batch_no: '', expiry_date: '' },
        { item_id: 'I2', quantity: '0', batch_no: '', expiry_date: '' },
      ],
      outstanding,
    );
    expect(errors).toEqual({ 0: 'Melebihi sisa pesanan (5).' });
  });

  it('P2-05 hasReceivedLine mengenali minimal satu baris terisi', () => {
    const empty = [{ item_id: 'I1', quantity: '', batch_no: '', expiry_date: '' }];
    expect(hasReceivedLine(empty)).toBe(false);
    expect(hasReceivedLine([{ ...empty[0]!, quantity: '2' }])).toBe(true);
  });
});

describe('scheduleSchema dan materialSchema', () => {
  it('P2-05 interval opname antara 7 dan 365 hari', () => {
    expect(scheduleSchema.safeParse({ enabled: true, interval_days: '30' }).success).toBe(true);
    expect(scheduleSchema.safeParse({ enabled: true, interval_days: '3' }).success).toBe(false);
    expect(scheduleSchema.safeParse({ enabled: true, interval_days: '400' }).success).toBe(false);
  });

  it('P2-05 bahan tindakan butuh tindakan, obat, dan jumlah 1 sampai 1000', () => {
    const ok = { procedure_id: 'P', medicine_id: 'M', quantity: '2' };
    expect(materialSchema.safeParse(ok).success).toBe(true);
    expect(materialSchema.safeParse({ ...ok, quantity: '0' }).success).toBe(false);
    expect(materialSchema.safeParse({ ...ok, quantity: '1001' }).success).toBe(false);
    expect(materialSchema.safeParse({ ...ok, procedure_id: '' }).success).toBe(false);
  });
});
