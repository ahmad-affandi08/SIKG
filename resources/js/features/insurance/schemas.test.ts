import { describe, expect, it } from 'vitest';

import { approveSchema, claimSchema, policySchema, receiveSchema, rejectSchema } from './schemas';

describe('P2-02 polis penjamin', () => {
  const base = {
    insurer_id: 'I1',
    policy_no: 'POL-1',
    ceiling_amount: '',
    valid_from: '',
    valid_to: '',
    notes: '',
  };

  it('penjamin dan nomor polis wajib; plafon boleh kosong', () => {
    expect(policySchema.safeParse(base).success).toBe(true);
    expect(policySchema.safeParse({ ...base, insurer_id: '', policy_no: ' ' }).success).toBe(false);
  });

  it('plafon bilangan bulat dan masa berlaku berurutan', () => {
    expect(policySchema.safeParse({ ...base, ceiling_amount: '1.500' }).success).toBe(false);
    expect(policySchema.safeParse({ ...base, ceiling_amount: '5000000' }).success).toBe(true);
    expect(
      policySchema.safeParse({ ...base, valid_from: '2025-06-10', valid_to: '2025-06-01' }).success,
    ).toBe(false);
  });
});

describe('P2-02 klaim', () => {
  it('jumlah klaim tidak melebihi sisa tagihan', () => {
    const schema = claimSchema(300000);
    const ok = { insurer_id: 'I1', policy_id: '', claimed_amount: '300000', notes: '' };
    expect(schema.safeParse(ok).success).toBe(true);
    expect(schema.safeParse({ ...ok, claimed_amount: '300001' }).success).toBe(false);
    expect(schema.safeParse({ ...ok, claimed_amount: '0' }).success).toBe(false);
    expect(schema.safeParse({ ...ok, claimed_amount: '1,5' }).success).toBe(false);
  });

  it('jumlah disetujui tidak melebihi jumlah diklaim dan alasan tolak wajib', () => {
    expect(approveSchema(100000).safeParse({ approved_amount: '80000', note: '' }).success).toBe(
      true,
    );
    expect(approveSchema(100000).safeParse({ approved_amount: '100001', note: '' }).success).toBe(
      false,
    );
    expect(rejectSchema.safeParse({ reason: ' ' }).success).toBe(false);
  });

  it('pembayaran tidak melebihi sisa yang disetujui', () => {
    const schema = receiveSchema(50000);
    expect(
      schema.safeParse({ payment_method_id: 'M1', amount: '50000', reference_no: '' }).success,
    ).toBe(true);
    expect(
      schema.safeParse({ payment_method_id: 'M1', amount: '50001', reference_no: '' }).success,
    ).toBe(false);
    expect(
      schema.safeParse({ payment_method_id: '', amount: '1000', reference_no: '' }).success,
    ).toBe(false);
  });
});
