import { describe, expect, it } from 'vitest';

import { isRequired } from '@/lib/useRequired';

describe('UI-05 daftar isian wajib dari server', () => {
  it('mencocokkan nama persis', () => {
    expect(isRequired(['name', 'birth_date'], 'name')).toBe(true);
    expect(isRequired(['name'], 'phone')).toBe(false);
  });

  it('pola bertingkat memakai * untuk satu ruas', () => {
    const list = ['schedule.*.start', 'items.*.dose'];
    expect(isRequired(list, 'schedule.0.start')).toBe(true);
    expect(isRequired(list, 'schedule.12.start')).toBe(true);
    expect(isRequired(list, 'schedule.0.end')).toBe(false);
    expect(isRequired(list, 'schedule.start')).toBe(false);
    expect(isRequired(list, 'items.1.dose')).toBe(true);
  });

  it('tanpa daftar atau tanpa nama isian tidak wajib', () => {
    expect(isRequired(undefined, 'name')).toBe(false);
    expect(isRequired(['name'], undefined)).toBe(false);
  });
});
