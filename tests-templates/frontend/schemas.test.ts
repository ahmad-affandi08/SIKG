// TEMPLAT: salin ke resources/js/features/patient/schemas.test.ts
import { describe, expect, it } from 'vitest';
import { patientFormSchema } from './schemas';

const valid = {
  name: 'Pasien Uji',
  nik: '9999000000000001',
  birthDate: '2000-01-01',
  sex: 'F',
  phone: '081200000001',
  address: 'Jl. Uji 1',
};

describe('PAS-01 skema form pasien', () => {
  it('menerima data sah', () => {
    expect(patientFormSchema.safeParse(valid).success).toBe(true);
  });

  it('menolak NIK bukan 16 digit dengan pesan Indonesia', () => {
    const r = patientFormSchema.safeParse({ ...valid, nik: '123' });
    expect(r.success).toBe(false);
    if (!r.success) expect(r.error.issues[0]?.message).toMatch(/16 digit/i);
  });

  it('PAS-06 pasien di bawah 18 tahun wajib punya wali', () => {
    const anak = { ...valid, birthDate: '2015-01-01' };
    expect(patientFormSchema.safeParse(anak).success).toBe(false);
    expect(patientFormSchema.safeParse({ ...anak, guardian: { name: 'Wali Uji', relation: 'ibu', phone: '081200000002' } }).success).toBe(true);
  });

  it('KSR-01 uang harus bilangan bulat tidak negatif', () => {
    // contoh pada skema invoice: amount
    // expect(paymentFormSchema.safeParse({ amount: 1.5 }).success).toBe(false);
    // expect(paymentFormSchema.safeParse({ amount: -1 }).success).toBe(false);
  });
});
