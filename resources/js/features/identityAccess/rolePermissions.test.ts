import { describe, expect, it } from 'vitest';

import { diffFromDefaults, isDirty, togglePermission } from './rolePermissions';

describe('AUTH-07 pilihan izin peran', () => {
  it('menampilkan izin yang ditambah dan dicabut dari bawaan', () => {
    expect(diffFromDefaults(['a', 'c'], ['a', 'b'])).toEqual({ added: ['c'], removed: ['b'] });
  });

  it('mencentang menambah izin sekali saja', () => {
    expect(togglePermission(['a'], 'b', true, [])).toEqual(['a', 'b']);
    expect(togglePermission(['a'], 'a', true, [])).toEqual(['a']);
  });

  it('mencabut menghapus izin, kecuali izin terkunci', () => {
    expect(togglePermission(['a', 'b'], 'b', false, [])).toEqual(['a']);
    expect(togglePermission(['a', 'b'], 'b', false, ['b'])).toEqual(['a', 'b']);
  });

  it('deteksi perubahan tidak peduli urutan', () => {
    expect(isDirty(['b', 'a'], ['a', 'b'])).toBe(false);
    expect(isDirty(['a'], ['a', 'b'])).toBe(true);
    expect(isDirty(['a', 'c'], ['a', 'b'])).toBe(true);
  });
});
