import { describe, expect, it } from 'vitest';

import { dicomUploadSchema, formatBytes, importSchema } from './schemas';

describe('P3-03 skema rontgen digital', () => {
  it('impor dari folder masuk butuh berkas dan kunjungan; nomor gigi opsional dua angka', () => {
    expect(
      importSchema.safeParse({ name: 'a.dcm', encounter_id: 'X', tooth_fdi: '' }).success,
    ).toBe(true);
    expect(
      importSchema.safeParse({ name: 'a.dcm', encounter_id: 'X', tooth_fdi: '46' }).success,
    ).toBe(true);
    expect(importSchema.safeParse({ name: '', encounter_id: 'X', tooth_fdi: '' }).success).toBe(
      false,
    );
    expect(importSchema.safeParse({ name: 'a.dcm', encounter_id: '', tooth_fdi: '' }).success).toBe(
      false,
    );
    expect(
      importSchema.safeParse({ name: 'a.dcm', encounter_id: 'X', tooth_fdi: '4' }).success,
    ).toBe(false);
  });

  it('unggah DICOM membatasi keterangan', () => {
    expect(
      dicomUploadSchema.safeParse({ encounter_id: 'X', tooth_fdi: '', caption: 'ok' }).success,
    ).toBe(true);
    expect(
      dicomUploadSchema.safeParse({ encounter_id: 'X', tooth_fdi: '', caption: 'a'.repeat(256) })
        .success,
    ).toBe(false);
  });

  it('ukuran berkas ditulis ringkas', () => {
    expect(formatBytes(500)).toBe('1 KB');
    expect(formatBytes(2048)).toBe('2 KB');
    expect(formatBytes(3 * 1024 * 1024 + 512 * 1024)).toBe('3,5 MB');
  });
});
