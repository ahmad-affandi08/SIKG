import { describe, expect, it } from 'vitest';

import type { OdontogramData } from '@/features/clinical/odontogram/api';
import {
  changedTeeth,
  conditionName,
  describeChange,
  hasPrimaryTeeth,
} from '@/features/clinical/odontogram/compare';

const data: OdontogramData = {
  teeth: {},
  conditions: [
    { code: 'KAR', name: 'Karies', color: '#DC2626', scope: 'surface', active: true },
    { code: 'TAM', name: 'Tambalan', color: '#2563EB', scope: 'surface', active: true },
  ],
};

describe('ODO-07 perbandingan odontogram', () => {
  it('menyorot gigi yang berubah tanpa menggandakan gigi yang sama', () => {
    const set = changedTeeth([
      { fdi: 16, surface: 'O', from: 'KAR', to: 'TAM' },
      { fdi: 16, surface: 'M', from: null, to: 'KAR' },
      { fdi: 26, surface: 'W', from: null, to: 'IMP' },
    ]);
    expect([...set].sort()).toEqual([16, 26]);
  });

  it('menamai kondisi dan menyebut bagian yang belum dicatat', () => {
    expect(conditionName(data, 'KAR')).toBe('Karies');
    expect(conditionName(data, 'ZZZ')).toBe('ZZZ');
    expect(conditionName(data, null)).toBe('Belum dicatat');
  });

  it('menulis perubahan dalam satu kalimat', () => {
    expect(describeChange({ fdi: 16, surface: 'O', from: 'KAR', to: 'TAM' }, data)).toBe(
      'Gigi 16, oklusal atau insisal: Karies menjadi Tambalan',
    );
    expect(describeChange({ fdi: 26, surface: 'W', from: null, to: 'KAR' }, data)).toBe(
      'Gigi 26, seluruh gigi: Belum dicatat menjadi Karies',
    );
  });

  it('menggambar gigi sulung hanya bila tercatat', () => {
    const sulung: OdontogramData = { ...data, teeth: { '55': { whole: null, surfaces: {} } } };
    expect(hasPrimaryTeeth(data, null)).toBe(false);
    expect(hasPrimaryTeeth(data, sulung)).toBe(true);
  });
});
