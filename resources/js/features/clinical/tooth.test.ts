import { describe, expect, it } from 'vitest';

import { allTeeth, dentitionOf, isAnterior, isValidFdi, toothRows } from './tooth';

describe('ODO-01 penomoran FDI', () => {
  it('menerima gigi dewasa dan sulung yang sah', () => {
    for (const fdi of [11, 18, 21, 28, 31, 38, 41, 48, 51, 55, 61, 65, 71, 75, 81, 85]) {
      expect(isValidFdi(fdi)).toBe(true);
    }
  });

  it('menolak nomor yang tidak ada', () => {
    for (const fdi of [0, 10, 19, 29, 49, 56, 66, 76, 86, 90, 111, 1.5]) {
      expect(isValidFdi(fdi)).toBe(false);
    }
  });

  it('gigi dewasa 32 dan sulung 20', () => {
    expect(allTeeth('permanent')).toHaveLength(32);
    expect(allTeeth('primary')).toHaveLength(20);
    expect(allTeeth('mixed')).toHaveLength(52);
    expect(new Set(allTeeth('mixed')).size).toBe(52);
    expect(allTeeth('mixed').every(isValidFdi)).toBe(true);
  });

  it('baris odontogram mengikuti urutan tampilan (kanan pasien di kiri layar)', () => {
    const rows = toothRows('permanent');
    expect(rows.upper.slice(0, 3)).toEqual([18, 17, 16]);
    expect(rows.upper.slice(7, 10)).toEqual([11, 21, 22]);
    expect(rows.lower.slice(0, 2)).toEqual([48, 47]);
    expect(rows.lower.slice(7, 9)).toEqual([41, 31]);
  });

  it('jenis gigi dan gigi depan', () => {
    expect(dentitionOf(16)).toBe('permanent');
    expect(dentitionOf(54)).toBe('primary');
    expect(isAnterior(11)).toBe(true);
    expect(isAnterior(14)).toBe(false);
  });
});
