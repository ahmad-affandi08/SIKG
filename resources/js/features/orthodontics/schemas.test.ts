import { describe, expect, it } from 'vitest';

import { caseSchema, controlSchema, previewInstallments } from './schemas';

const base = {
  doctor_id: 'D1',
  appliance: 'metal_braces',
  title: 'Behel logam rahang atas dan bawah',
  plan_notes: '',
  total_fee_amount: '12000000',
  down_payment_amount: '',
  installment_count: '12',
  start_date: '2025-06-16',
  expected_months: '18',
};

describe('P2-04 paket ortodonti', () => {
  it('paket lengkap sah', () => {
    expect(caseSchema.safeParse(base).success).toBe(true);
  });

  it('biaya, jumlah cicilan, dan lama perawatan dibatasi', () => {
    expect(caseSchema.safeParse({ ...base, total_fee_amount: '0' }).success).toBe(false);
    expect(caseSchema.safeParse({ ...base, installment_count: '37' }).success).toBe(false);
    expect(caseSchema.safeParse({ ...base, expected_months: '61' }).success).toBe(false);
    expect(caseSchema.safeParse({ ...base, total_fee_amount: '1.5' }).success).toBe(false);
  });

  it('uang muka tidak melebihi biaya dan butuh sedikitnya dua cicilan bila ada sisa', () => {
    expect(caseSchema.safeParse({ ...base, down_payment_amount: '13000000' }).success).toBe(false);
    expect(
      caseSchema.safeParse({ ...base, down_payment_amount: '2000000', installment_count: '1' })
        .success,
    ).toBe(false);
    expect(
      caseSchema.safeParse({ ...base, down_payment_amount: '2000000', installment_count: '11' })
        .success,
    ).toBe(true);
    expect(
      caseSchema.safeParse({ ...base, down_payment_amount: '12000000', installment_count: '1' })
        .success,
    ).toBe(true);
  });
});

describe('P2-04 catatan kontrol', () => {
  const control = {
    control_date: '2025-07-16',
    encounter_id: '',
    wire_or_tray: '',
    adjustments: 'Ganti kawat',
    notes: '',
    next_control_date: '',
  };

  it('butuh sedikitnya satu isian klinis dan urutan tanggal benar', () => {
    expect(controlSchema.safeParse(control).success).toBe(true);
    expect(controlSchema.safeParse({ ...control, adjustments: '' }).success).toBe(false);
    expect(controlSchema.safeParse({ ...control, next_control_date: '2025-07-01' }).success).toBe(
      false,
    );
    expect(controlSchema.safeParse({ ...control, next_control_date: '2025-08-16' }).success).toBe(
      true,
    );
  });
});

describe('P2-04 pratinjau cicilan', () => {
  it('sisa pembagian jatuh pada cicilan terakhir dan jumlahnya sama dengan biaya', () => {
    const rows = previewInstallments(1000000, 3, 0);
    expect(rows).toEqual([333333, 333333, 333334]);
    expect(rows.reduce((a, b) => a + b, 0)).toBe(1000000);
  });

  it('uang muka menjadi cicilan pertama', () => {
    expect(previewInstallments(1000000, 3, 400000)).toEqual([400000, 300000, 300000]);
    expect(previewInstallments(0, 3, 0)).toEqual([]);
  });
});
