import { describe, expect, it } from 'vitest';

import type { OdontogramData } from './api';
import { applyCondition, conditionAt, mesialOnRight, toothLabel } from './model';

const conditions = [
  { code: 'SEH', name: 'Sehat', color: '#FFFFFF', scope: 'both' as const, active: true },
  { code: 'KAR', name: 'Karies', color: '#DC2626', scope: 'surface' as const, active: true },
  { code: 'HLG', name: 'Hilang', color: '#475569', scope: 'tooth' as const, active: true },
];

const entry = (code: string) => ({
  condition_code: code,
  set_at: '2025-06-16T00:00:00Z',
  encounter_id: 'E1',
  in_this_encounter: false,
});

const data: OdontogramData = {
  conditions,
  teeth: {
    '16': { whole: null, surfaces: { O: entry('KAR'), M: entry('KAR') } },
    '26': { whole: entry('HLG'), surfaces: {} },
  },
};

describe('ODO-02 model odontogram', () => {
  it('kondisi efektif: seluruh gigi menutupi permukaan', () => {
    expect(conditionAt(data, 16, 'O')?.condition_code).toBe('KAR');
    expect(conditionAt(data, 16, 'D')).toBeNull();
    expect(conditionAt(data, 26, 'O')?.condition_code).toBe('HLG');
    expect(conditionAt(data, 26, 'W')?.condition_code).toBe('HLG');
    expect(conditionAt(data, 36, 'O')).toBeNull();
  });

  it('label aksesibel menyebut nomor gigi, kondisi, dan permukaan', () => {
    expect(toothLabel(data, 16)).toBe('Gigi 16, karies mesial, karies oklusal');
    expect(toothLabel(data, 26)).toBe('Gigi 26, hilang seluruh gigi');
    expect(toothLabel(data, 36)).toBe('Gigi 36, belum dicatat');
  });

  it('mesial menghadap garis tengah', () => {
    expect(mesialOnRight(16)).toBe(true);
    expect(mesialOnRight(46)).toBe(true);
    expect(mesialOnRight(26)).toBe(false);
    expect(mesialOnRight(36)).toBe(false);
  });

  it('penerapan optimistis tidak mengubah data asal dan menimpa permukaan yang sama', () => {
    const next = applyCondition(data, 16, 'O', 'SEH', '2025-06-17T00:00:00Z', 'E2');

    expect(next.teeth['16']?.surfaces.O?.condition_code).toBe('SEH');
    expect(next.teeth['16']?.surfaces.O?.in_this_encounter).toBe(true);
    expect(next.teeth['16']?.surfaces.M?.condition_code).toBe('KAR');
    expect(data.teeth['16']?.surfaces.O?.condition_code).toBe('KAR');
  });

  it('kondisi seluruh gigi menghapus tampilan permukaannya', () => {
    const next = applyCondition(data, 16, 'W', 'HLG', '2025-06-17T00:00:00Z', 'E2');

    expect(next.teeth['16']?.whole?.condition_code).toBe('HLG');
    expect(next.teeth['16']?.surfaces).toEqual({});
  });
});
