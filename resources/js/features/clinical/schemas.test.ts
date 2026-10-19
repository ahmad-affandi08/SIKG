import { describe, expect, it } from 'vitest';

import {
  addendumSchema,
  anamnesisSchema,
  diagnosisEntrySchema,
  procedureEntrySchema,
  temperatureToTenths,
  tenthsToTemperature,
  tenthsToWeight,
  weightToTenths,
} from './schemas';

const anamnesis = {
  chief_complaint: 'Nyeri',
  complaint_history: '',
  systolic: '120',
  diastolic: '80',
  pulse: '78',
  temperature: '36.8',
  respiratory_rate: '',
  oxygen_saturation: '',
  weight: '',
  height: '',
};

function messages(result: {
  success: boolean;
  error?: { issues: { message: string }[] };
}): string[] {
  return result.success ? [] : (result.error?.issues.map((i) => i.message) ?? []);
}

describe('RM-02 anamnesis', () => {
  it('menerima isian yang benar', () => {
    expect(anamnesisSchema.safeParse(anamnesis).success).toBe(true);
    expect(
      anamnesisSchema.safeParse({
        ...anamnesis,
        systolic: '',
        diastolic: '',
        pulse: '',
        temperature: '',
        respiratory_rate: '',
        oxygen_saturation: '',
        weight: '',
        height: '',
      }).success,
    ).toBe(true);
  });

  it('tekanan darah harus berpasangan dan diastolik lebih rendah', () => {
    expect(messages(anamnesisSchema.safeParse({ ...anamnesis, diastolic: '' }))).toContain(
      'Tekanan darah diisi sistolik dan diastolik sekaligus.',
    );
    expect(
      messages(anamnesisSchema.safeParse({ ...anamnesis, systolic: '80', diastolic: '120' })),
    ).toContain('Tekanan diastolik harus lebih rendah dari sistolik.');
  });

  it('menolak nilai di luar rentang atau bukan angka bulat', () => {
    expect(messages(anamnesisSchema.safeParse({ ...anamnesis, pulse: '500' })).join()).toContain(
      'Nadi di luar rentang',
    );
    expect(messages(anamnesisSchema.safeParse({ ...anamnesis, pulse: '7.5' }))).toContain(
      'Nadi harus berupa angka bulat.',
    );
    expect(
      messages(anamnesisSchema.safeParse({ ...anamnesis, temperature: '60' })).join(),
    ).toContain('Suhu tidak wajar');
  });

  it('suhu dikonversi ke persepuluh derajat tanpa bilangan pecahan', () => {
    expect(temperatureToTenths('36.8')).toBe(368);
    expect(temperatureToTenths('36,8')).toBe(368);
    expect(temperatureToTenths('37')).toBe(370);
    expect(temperatureToTenths('')).toBeNull();
    expect(Number.isNaN(temperatureToTenths('abc'))).toBe(true);
    expect(tenthsToTemperature(368)).toBe('36.8');
    expect(tenthsToTemperature(null)).toBe('');
  });
});

describe('KLU-04 tanda vital lengkap', () => {
  const lengkap = {
    ...anamnesis,
    respiratory_rate: '20',
    oxygen_saturation: '98',
    weight: '65.2',
    height: '170',
  };

  it('menerima laju napas, saturasi, berat, dan tinggi badan yang wajar', () => {
    expect(anamnesisSchema.safeParse(lengkap).success).toBe(true);
  });

  it('menolak nilai di luar rentang atau bukan angka', () => {
    expect(
      messages(anamnesisSchema.safeParse({ ...lengkap, oxygen_saturation: '101' })).join(),
    ).toContain('Saturasi oksigen di luar rentang');
    expect(
      messages(anamnesisSchema.safeParse({ ...lengkap, respiratory_rate: '2' })).join(),
    ).toContain('Laju napas di luar rentang');
    expect(messages(anamnesisSchema.safeParse({ ...lengkap, height: '300' })).join()).toContain(
      'Tinggi badan di luar rentang',
    );
    expect(messages(anamnesisSchema.safeParse({ ...lengkap, weight: 'abc' })).join()).toContain(
      'Berat badan tidak wajar',
    );
  });

  it('berat badan dikonversi ke persepuluh kilogram tanpa bilangan pecahan', () => {
    expect(weightToTenths('65.2')).toBe(652);
    expect(weightToTenths('65,2')).toBe(652);
    expect(weightToTenths('7')).toBe(70);
    expect(weightToTenths('')).toBeNull();
    expect(Number.isNaN(weightToTenths('65.25'))).toBe(true);
    expect(tenthsToWeight(652)).toBe('65.2');
    expect(tenthsToWeight(null)).toBe('');
  });
});

describe('RM-04 diagnosa', () => {
  it('diagnosa wajib dan gigi harus FDI yang sah', () => {
    expect(
      messages(diagnosisEntrySchema.safeParse({ diagnosis_id: '', tooth_fdi: '', notes: '' })),
    ).toContain('Diagnosa wajib dipilih.');
    expect(
      messages(diagnosisEntrySchema.safeParse({ diagnosis_id: 'D', tooth_fdi: '19', notes: '' })),
    ).toContain('Nomor gigi tidak valid menurut FDI.');
    expect(
      diagnosisEntrySchema.safeParse({ diagnosis_id: 'D', tooth_fdi: '46', notes: '' }).success,
    ).toBe(true);
    expect(
      diagnosisEntrySchema.safeParse({ diagnosis_id: 'D', tooth_fdi: '', notes: '' }).success,
    ).toBe(true);
  });
});

describe('RM-05 tindakan menurut cakupan', () => {
  const base = {
    procedure_id: 'P',
    tooth_fdi: '',
    surfaces: [] as string[],
    jaw: '',
    quantity: '1',
    notes: '',
  };

  it('per gigi butuh gigi, per permukaan butuh gigi dan permukaan, per rahang butuh rahang', () => {
    expect(procedureEntrySchema('tooth').safeParse(base).success).toBe(false);
    expect(procedureEntrySchema('tooth').safeParse({ ...base, tooth_fdi: '16' }).success).toBe(
      true,
    );
    expect(procedureEntrySchema('surface').safeParse({ ...base, tooth_fdi: '16' }).success).toBe(
      false,
    );
    expect(
      procedureEntrySchema('surface').safeParse({ ...base, tooth_fdi: '16', surfaces: ['O'] })
        .success,
    ).toBe(true);
    expect(procedureEntrySchema('jaw').safeParse(base).success).toBe(false);
    expect(procedureEntrySchema('jaw').safeParse({ ...base, jaw: 'upper' }).success).toBe(true);
    expect(procedureEntrySchema('visit').safeParse(base).success).toBe(true);
  });

  it('jumlah 1 sampai 99', () => {
    expect(procedureEntrySchema('visit').safeParse({ ...base, quantity: '0' }).success).toBe(false);
    expect(procedureEntrySchema('visit').safeParse({ ...base, quantity: '100' }).success).toBe(
      false,
    );
    expect(procedureEntrySchema('visit').safeParse({ ...base, quantity: '99' }).success).toBe(true);
  });
});

describe('RM-07 addendum', () => {
  it('isi dan alasan wajib', () => {
    expect(addendumSchema.safeParse({ body: '', reason: '' }).success).toBe(false);
    expect(addendumSchema.safeParse({ body: 'Koreksi', reason: 'Salah ketik' }).success).toBe(true);
  });
});
