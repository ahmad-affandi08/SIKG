import { describe, expect, it } from 'vitest';

import { fileIssue, formatBytes } from './files';

describe('RM-08 berkas klinis', () => {
  it('berkas wajib dipilih', () => {
    expect(fileIssue(null)).toBe('Pilih berkas.');
  });

  it('hanya JPG, PNG, PDF hingga 10 MB', () => {
    expect(fileIssue({ type: 'image/png', size: 1000 })).toBeNull();
    expect(fileIssue({ type: 'application/pdf', size: 10 * 1024 * 1024 })).toBeNull();
    expect(fileIssue({ type: 'image/svg+xml', size: 1000 })).toBe(
      'Berkas harus berformat JPG, PNG, atau PDF.',
    );
    expect(fileIssue({ type: 'image/png', size: 10 * 1024 * 1024 + 1 })).toBe(
      'Ukuran berkas maksimal 10 MB.',
    );
    expect(fileIssue({ type: 'image/png', size: 0 })).toBe('Ukuran berkas maksimal 10 MB.');
  });

  it('ukuran ditampilkan ringkas', () => {
    expect(formatBytes(512)).toBe('512 B');
    expect(formatBytes(2048)).toBe('2 KB');
    expect(formatBytes(5 * 1024 * 1024)).toBe('5.0 MB');
  });
});
