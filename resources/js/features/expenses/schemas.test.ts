import { describe, expect, it } from 'vitest';

import { expenseSchema, voidSchema } from '@/features/expenses/schemas';

const valid = {
  expense_date: '2025-06-10',
  category_id: 'C1',
  amount: '1500000',
  payee: 'PLN',
  description: 'Listrik Juni',
  reference_no: '',
};

describe('expenseSchema', () => {
  const schema = expenseSchema('2025-06-16');

  it('P2-08 menerima biaya yang lengkap', () => {
    expect(schema.safeParse(valid).success).toBe(true);
  });

  it('P2-08 menolak tanggal masa depan, kategori kosong, dan keterangan kosong', () => {
    const result = schema.safeParse({
      ...valid,
      expense_date: '2025-06-17',
      category_id: '',
      description: ' ',
    });
    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues.map((i) => i.path[0])).toEqual([
        'expense_date',
        'category_id',
        'description',
      ]);
    }
  });

  it('P2-08 jumlah harus bilangan bulat rupiah lebih dari nol', () => {
    expect(schema.safeParse({ ...valid, amount: '0' }).success).toBe(false);
    expect(schema.safeParse({ ...valid, amount: '1.500.000' }).success).toBe(false);
    expect(schema.safeParse({ ...valid, amount: '12,5' }).success).toBe(false);
    expect(schema.safeParse({ ...valid, amount: '' }).success).toBe(false);
  });
});

describe('voidSchema', () => {
  it('P2-08 pembatalan butuh alasan', () => {
    expect(voidSchema.safeParse({ reason: '' }).success).toBe(false);
    expect(voidSchema.safeParse({ reason: 'Salah input' }).success).toBe(true);
  });
});
