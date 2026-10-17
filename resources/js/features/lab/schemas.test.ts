import { describe, expect, it } from 'vitest';

import { cancelSchema, labOrderSchema, receiveSchema } from '@/features/lab/schemas';

const valid = {
  doctor_id: 'D1',
  laboratory_id: 'L1',
  work_type: 'crown',
  tooth_notes: '46',
  description: 'Mahkota zirkonia',
  shade: 'A2',
  due_date: '',
  lab_cost_amount: '450000',
  patient_charge_amount: '1200000',
};

describe('labOrderSchema', () => {
  it('P2-06 menerima pesanan lengkap', () => {
    expect(labOrderSchema.safeParse(valid).success).toBe(true);
  });

  it('P2-06 menolak dokter, lab, dan uraian kosong', () => {
    const result = labOrderSchema.safeParse({
      ...valid,
      doctor_id: '',
      laboratory_id: '',
      description: ' ',
    });
    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues.map((i) => i.path[0])).toEqual([
        'doctor_id',
        'laboratory_id',
        'description',
      ]);
    }
  });

  it('P2-06 uang berupa bilangan bulat rupiah, nol diperbolehkan', () => {
    expect(labOrderSchema.safeParse({ ...valid, lab_cost_amount: '0' }).success).toBe(true);
    expect(labOrderSchema.safeParse({ ...valid, lab_cost_amount: '450.000' }).success).toBe(false);
    expect(labOrderSchema.safeParse({ ...valid, patient_charge_amount: '' }).success).toBe(false);
  });
});

describe('receiveSchema dan cancelSchema', () => {
  it('P2-06 penerimaan butuh tanggal dan biaya aktual', () => {
    expect(receiveSchema.safeParse({ date: '2025-06-20', lab_cost_amount: '500000' }).success).toBe(
      true,
    );
    expect(receiveSchema.safeParse({ date: '', lab_cost_amount: '500000' }).success).toBe(false);
  });

  it('P2-06 pembatalan butuh alasan', () => {
    expect(cancelSchema.safeParse({ reason: '' }).success).toBe(false);
    expect(cancelSchema.safeParse({ reason: 'Pasien batal' }).success).toBe(true);
  });
});
