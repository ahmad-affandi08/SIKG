import { describe, expect, it } from 'vitest';

import { ageOn, buildPatientSchema, historyFormSchema } from './schemas';
import type { PatientFormValues } from './schemas';

const TODAY = new Date(2025, 5, 15); // 15 Juni 2025

const valid: PatientFormValues = {
  name: 'Budi Santoso',
  nik: '9999010101900001',
  nik_missing_reason: '',
  birth_date: '1990-01-01',
  sex: 'male',
  address: '',
  phone: '08123456789',
  occupation: '',
  emergency_name: '',
  emergency_relation: '',
  emergency_phone: '',
  guardian_name: '',
  guardian_relation: '',
  guardian_phone: '',
  consent: true,
};

function issues(values: Partial<PatientFormValues>, requireConsent = true): string[] {
  const schema = buildPatientSchema({ requireConsent, today: TODAY });
  const result = schema.safeParse({ ...valid, ...values });
  return result.success ? [] : result.error.issues.map((i) => i.message);
}

describe('PAS-01 formulir pasien', () => {
  it('menerima isian yang benar', () => {
    expect(issues({})).toEqual([]);
  });

  it('NIK wajib atau alasan wajib', () => {
    expect(issues({ nik: '' })).toContain(
      'NIK wajib diisi. Bila belum ada (bayi atau WNA), tulis alasannya.',
    );
    expect(issues({ nik: '', nik_missing_reason: 'WNA, paspor menyusul' })).toEqual([]);
  });

  it('NIK harus 16 digit', () => {
    expect(issues({ nik: '123' })).toContain('NIK harus 16 digit angka.');
    expect(issues({ nik: '9999 0101 0190 0001' })).toEqual([]);
  });

  it('nomor HP divalidasi bila diisi', () => {
    expect(issues({ phone: 'abc' })).toContain('Nomor HP tidak valid (contoh 08123456789).');
    expect(issues({ phone: '+62 812-3456-789' })).toEqual([]);
    expect(issues({ phone: '' })).toEqual([]);
  });

  it('kontak darurat butuh nomor HP bila nama diisi', () => {
    expect(issues({ emergency_name: 'Ani' })).toContain('Nomor HP kontak darurat wajib diisi.');
  });

  it('nama wajib', () => {
    expect(issues({ name: ' ' })).toContain('Nama pasien wajib diisi.');
  });
});

describe('PAS-06 wali untuk pasien di bawah 18 tahun', () => {
  it('menghitung umur penuh dengan benar', () => {
    expect(ageOn('2007-06-15', TODAY)).toBe(18);
    expect(ageOn('2007-06-16', TODAY)).toBe(17);
    expect(ageOn('2025-06-15', TODAY)).toBe(0);
  });

  it('wali wajib untuk anak, tidak untuk dewasa', () => {
    expect(issues({ birth_date: '2015-03-03' })).toContain(
      'Pasien di bawah 18 tahun wajib memiliki data wali.',
    );
    expect(
      issues({ birth_date: '2015-03-03', guardian_name: 'Rina', guardian_relation: 'Ibu' }),
    ).toEqual([]);
    expect(issues({ birth_date: '2007-06-15' })).toEqual([]);
  });

  it('hubungan wali wajib bila nama wali diisi', () => {
    expect(issues({ guardian_name: 'Rina' })).toContain('Hubungan wali dengan pasien wajib diisi.');
  });
});

describe('PAS-05 persetujuan', () => {
  it('wajib pada pendaftaran baru, tidak pada ubah data', () => {
    expect(issues({ consent: false })).toContain(
      'Persetujuan pemrosesan data pasien wajib dicatat.',
    );
    expect(issues({ consent: false }, false)).toEqual([]);
  });
});

describe('PAS-04 formulir riwayat medis', () => {
  it('jenis dan nama wajib', () => {
    const result = historyFormSchema.safeParse({ type: '', name: ' ', severity: '', notes: '' });
    expect(result.success).toBe(false);
    expect(
      historyFormSchema.safeParse({ type: 'allergy', name: 'Penisilin', severity: '', notes: '' })
        .success,
    ).toBe(true);
  });
});
