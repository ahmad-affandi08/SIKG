import { describe, expect, it } from 'vitest';

import { importFileIssue } from './importFile';

describe('PAS-08 berkas impor', () => {
  it('berkas wajib dipilih', () => {
    expect(importFileIssue(null)).toBe('Pilih berkas Excel.');
  });

  it('hanya .xlsx hingga 5 MB', () => {
    expect(importFileIssue({ name: 'pasien.xlsx', size: 1000 })).toBeNull();
    expect(importFileIssue({ name: 'PASIEN.XLSX', size: 1000 })).toBeNull();
    expect(importFileIssue({ name: 'pasien.csv', size: 1000 })).toBe(
      'Berkas harus berformat .xlsx (gunakan templat dari sistem).',
    );
    expect(importFileIssue({ name: 'pasien.xlsx', size: 6 * 1024 * 1024 })).toBe(
      'Ukuran berkas maksimal 5 MB.',
    );
  });
});
