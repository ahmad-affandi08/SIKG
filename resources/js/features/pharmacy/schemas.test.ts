import { describe, expect, it } from 'vitest';

import {
  adjustmentSchema,
  emptyRow,
  prescriptionRowSchema,
  expiryText,
  stockLevel,
  stockMovementSchema,
} from './schemas';

const row = {
  ...emptyRow(),
  medicine_id: 'M1',
  dose: '500 mg',
  frequency: '3 x sehari',
  duration: '5 hari',
  quantity: '15',
};

function messages(result: {
  success: boolean;
  error?: { issues: { message: string }[] };
}): string[] {
  return result.success ? [] : (result.error?.issues.map((i) => i.message) ?? []);
}

describe('OBT-01 baris resep', () => {
  it('menerima obat katalog atau teks bebas yang lengkap', () => {
    expect(prescriptionRowSchema.safeParse(row).success).toBe(true);
    expect(
      prescriptionRowSchema.safeParse({ ...row, medicine_id: '', free_text_name: 'Obat kumur' })
        .success,
    ).toBe(true);
  });

  it('salah satu: katalog atau teks bebas', () => {
    expect(messages(prescriptionRowSchema.safeParse({ ...row, medicine_id: '' }))).toContain(
      'Pilih obat dari katalog atau tulis nama obat, salah satu.',
    );
    expect(
      messages(prescriptionRowSchema.safeParse({ ...row, free_text_name: 'Obat X' })),
    ).toContain('Pilih obat dari katalog atau tulis nama obat, salah satu.');
  });

  it('dosis, frekuensi, durasi wajib dan jumlah 1 sampai 9999', () => {
    expect(messages(prescriptionRowSchema.safeParse({ ...row, dose: '' }))).toContain(
      'Dosis wajib diisi.',
    );
    expect(messages(prescriptionRowSchema.safeParse({ ...row, quantity: '0' }))).toContain(
      'Jumlah minimal 1.',
    );
    expect(messages(prescriptionRowSchema.safeParse({ ...row, quantity: '10000' }))).toContain(
      'Jumlah maksimal 9999.',
    );
  });

  it('hanya obat katalog yang dapat dijual di klinik', () => {
    expect(
      messages(
        prescriptionRowSchema.safeParse({
          ...row,
          medicine_id: '',
          free_text_name: 'Obat X',
          dispense_in_clinic: true,
        }),
      ),
    ).toContain('Hanya obat dari katalog yang dapat dijual di klinik.');
    expect(prescriptionRowSchema.safeParse({ ...row, dispense_in_clinic: true }).success).toBe(
      true,
    );
  });
});

describe('OBT-05 stok', () => {
  const movement = {
    medicine_id: 'M1',
    type: 'in' as const,
    quantity: '10',
    batch_no: '',
    expiry_date: '',
    reason: '',
  };

  it('stok keluar wajib beralasan, stok masuk tidak', () => {
    expect(stockMovementSchema.safeParse(movement).success).toBe(true);
    expect(messages(stockMovementSchema.safeParse({ ...movement, type: 'out' }))).toContain(
      'Alasan stok keluar wajib diisi.',
    );
    expect(
      stockMovementSchema.safeParse({ ...movement, type: 'out', reason: 'Dipakai' }).success,
    ).toBe(true);
  });

  it('jumlah dan tanggal kedaluwarsa divalidasi', () => {
    expect(messages(stockMovementSchema.safeParse({ ...movement, quantity: '0' }))).toContain(
      'Jumlah minimal 1.',
    );
    expect(
      messages(stockMovementSchema.safeParse({ ...movement, expiry_date: '31/12/2026' })),
    ).toContain('Tanggal kedaluwarsa harus berformat YYYY-MM-DD.');
  });

  it('penyesuaian wajib beralasan dan tidak negatif', () => {
    expect(
      adjustmentSchema.safeParse({ medicine_id: 'M1', counted_quantity: '5', reason: 'Opname' })
        .success,
    ).toBe(true);
    expect(
      messages(
        adjustmentSchema.safeParse({ medicine_id: 'M1', counted_quantity: '5', reason: '' }),
      ),
    ).toContain('Alasan penyesuaian wajib diisi.');
    expect(
      messages(
        adjustmentSchema.safeParse({ medicine_id: 'M1', counted_quantity: '-1', reason: 'x' }),
      ),
    ).toContain('Tidak boleh negatif.');
  });

  it('tingkat stok: kosong, rendah, cukup', () => {
    expect(stockLevel(0, 10)).toBe('empty');
    expect(stockLevel(9, 10)).toBe('low');
    expect(stockLevel(10, 10)).toBe('ok');
  });
});

describe('OBT-06 keterangan kedaluwarsa', () => {
  it('menyebut sisa hari, hari ini, dan yang sudah lewat', () => {
    expect(expiryText(12)).toBe('12 hari lagi');
    expect(expiryText(0)).toBe('kedaluwarsa hari ini');
    expect(expiryText(-3)).toBe('sudah lewat 3 hari');
  });
});
