import { describe, expect, it } from 'vitest';

import { basisPointsToPercent, doctorFormSchema, feeValueToText } from './doctor';
import type { DoctorFormValues } from './doctor';

const valid: DoctorFormValues = {
  name: 'drg. Sari',
  sip_no: '',
  specialty: '',
  branch_id: '',
  user_id: '',
  fee_mode: 'percentage',
  fee_value: '30',
  fee_overrides: [],
  schedule: [{ weekday: 1, start: '09:00', end: '12:00' }],
};

function issues(values: DoctorFormValues): string[] {
  const result = doctorFormSchema.safeParse(values);
  return result.success ? [] : result.error.issues.map((issue) => issue.message);
}

describe('MST-02 formulir dokter', () => {
  it('menerima isian yang benar', () => {
    expect(doctorFormSchema.safeParse(valid).success).toBe(true);
  });

  it('menolak nama kosong', () => {
    expect(issues({ ...valid, name: ' ' })).toContain('Nama dokter wajib diisi.');
  });

  it('menolak persentase di atas 100 dan nominal desimal', () => {
    expect(issues({ ...valid, fee_value: '101' })).toContain(
      'Persentase harus 0 sampai 100 (maksimal dua desimal).',
    );
    expect(issues({ ...valid, fee_mode: 'fixed', fee_value: '1000.5' })).toContain(
      'Nominal harus rupiah bulat tidak negatif.',
    );
  });

  it('menolak kode pengecualian ganda tanpa membedakan huruf besar', () => {
    const rows = [
      { code: 'cabut', mode: 'fixed' as const, value: '1000' },
      { code: 'CABUT', mode: 'fixed' as const, value: '2000' },
    ];
    expect(issues({ ...valid, fee_overrides: rows })).toContain('Kode tindakan tidak boleh ganda.');
  });

  it('menolak jam selesai sebelum mulai dan jadwal tumpang tindih', () => {
    expect(
      issues({ ...valid, schedule: [{ weekday: 1, start: '12:00', end: '09:00' }] }),
    ).toContain('Jam selesai harus setelah jam mulai.');
    expect(
      issues({
        ...valid,
        schedule: [
          { weekday: 2, start: '09:00', end: '12:00' },
          { weekday: 2, start: '11:00', end: '13:00' },
        ],
      }),
    ).toContain('Jam praktik pada hari yang sama tidak boleh tumpang tindih.');
  });

  it('mengubah basis poin menjadi persen tanpa pecahan floating point', () => {
    expect(basisPointsToPercent(3000)).toBe('30');
    expect(basisPointsToPercent(3250)).toBe('32.5');
    expect(basisPointsToPercent(3205)).toBe('32.05');
    expect(basisPointsToPercent(0)).toBe('0');
    expect(feeValueToText('fixed', 75000)).toBe('75000');
  });
});
