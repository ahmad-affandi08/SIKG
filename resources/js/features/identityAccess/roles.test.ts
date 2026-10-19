import { describe, expect, it } from 'vitest';

import { roleLabel } from './roles';

describe('KLU-02 sebutan peran Dokter mengikuti profil layanan', () => {
  it('tanpa profil, Dokter tetap tampil sebagai Dokter gigi', () => {
    expect(roleLabel('dokter')).toBe('Dokter gigi');
  });

  it('klinik umum menyebutnya Dokter dan peran lain tidak berubah', () => {
    expect(roleLabel('dokter', 'Dokter')).toBe('Dokter');
    expect(roleLabel('perawat', 'Dokter')).toBe('Perawat');
    expect(roleLabel(null, 'Dokter')).toBe('-');
  });
});
