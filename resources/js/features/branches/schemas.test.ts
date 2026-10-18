import { describe, expect, it } from 'vitest';

import { transferSchema } from './schemas';

const base = {
  medicine_id: '01HZ0000000000000000000000',
  from_branch_id: '',
  to_branch_id: '01HZ0000000000000000000001',
  quantity: '10',
  reason: '',
};

describe('P3-04 skema transfer stok', () => {
  it('menerima transfer dari gudang pusat ke cabang', () => {
    expect(transferSchema.safeParse(base).success).toBe(true);
  });

  it('menolak asal sama dengan tujuan, jumlah nol atau pecahan, dan obat kosong', () => {
    expect(transferSchema.safeParse({ ...base, to_branch_id: '' }).success).toBe(false);
    expect(transferSchema.safeParse({ ...base, quantity: '0' }).success).toBe(false);
    expect(transferSchema.safeParse({ ...base, quantity: '2.5' }).success).toBe(false);
    expect(transferSchema.safeParse({ ...base, medicine_id: '' }).success).toBe(false);
  });
});
