import { describe, expect, it } from 'vitest';

import {
  loyaltySettingsSchema,
  packageUseSchema,
  pointsSchema,
  sellPackageSchema,
  voucherCodeSchema,
} from '@/features/loyalty/schemas';

describe('voucherCodeSchema', () => {
  it('P3-06 menerima kode yang sah dan menolak kosong atau berspasi', () => {
    expect(voucherCodeSchema.safeParse({ code: 'HEMAT-10' }).success).toBe(true);
    expect(voucherCodeSchema.safeParse({ code: '' }).success).toBe(false);
    expect(voucherCodeSchema.safeParse({ code: 'HEMAT 10' }).success).toBe(false);
  });
});

describe('pointsSchema', () => {
  const schema = pointsSchema(10, 50, 30);

  it('P3-06 menerima poin dalam batas minimum, maksimum tagihan, dan saldo', () => {
    expect(schema.safeParse({ points: '20' }).success).toBe(true);
  });

  it('P3-06 menolak di bawah minimum, di atas saldo, di atas nilai tagihan, dan bukan bilangan bulat', () => {
    const message = (points: string) => {
      const result = schema.safeParse({ points });
      return result.success ? '' : (result.error.issues[0]?.message ?? '');
    };
    expect(message('5')).toBe('Penukaran minimal 10 poin.');
    expect(message('40')).toBe('Saldo poin pasien tidak cukup.');
    expect(pointsSchema(10, 20, 100).safeParse({ points: '30' }).success).toBe(false);
    expect(message('2,5')).toBe('Jumlah poin berupa bilangan bulat.');
  });
});

describe('formulir paket dan pengaturan', () => {
  it('P3-06 pemakaian dan penjualan paket butuh pilihan lengkap', () => {
    expect(packageUseSchema.safeParse({ item_id: 'I', patient_package_id: '' }).success).toBe(
      false,
    );
    expect(sellPackageSchema.safeParse({ package_id: 'P', encounter_id: 'E' }).success).toBe(true);
    expect(sellPackageSchema.safeParse({ package_id: '', encounter_id: 'E' }).success).toBe(false);
  });

  it('P3-06 pengaturan poin berupa bilangan bulat positif', () => {
    const ok = {
      enabled: true,
      rupiah_per_point: '10000',
      point_value_amount: '1000',
      min_redeem_points: '10',
    };
    expect(loyaltySettingsSchema.safeParse(ok).success).toBe(true);
    expect(loyaltySettingsSchema.safeParse({ ...ok, rupiah_per_point: '10.000' }).success).toBe(
      false,
    );
  });
});
