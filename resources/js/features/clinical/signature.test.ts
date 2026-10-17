import { describe, expect, it } from 'vitest';

import { looksLikeSignature, signSchema } from '@/features/clinical/signature';

const valid = {
  signer_name: 'Budi Santoso',
  signer_role: 'patient' as const,
  signature: 'data:image/png;base64,AAAA',
  witness_name: '',
  witness_signature: '',
};

describe('signSchema', () => {
  it('P2-07 menerima tanda tangan pasien tanpa saksi', () => {
    expect(signSchema.safeParse(valid).success).toBe(true);
  });

  it('P2-07 menolak nama kosong dan tanda tangan kosong', () => {
    const result = signSchema.safeParse({ ...valid, signer_name: ' ', signature: '' });
    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues.map((i) => i.path[0])).toEqual(['signer_name', 'signature']);
    }
  });

  it('P2-07 saksi harus lengkap: nama dan tanda tangan sekaligus', () => {
    const onlyName = signSchema.safeParse({ ...valid, witness_name: 'Sari' });
    const onlyImage = signSchema.safeParse({
      ...valid,
      witness_signature: 'data:image/png;base64,BB',
    });
    const both = signSchema.safeParse({
      ...valid,
      witness_name: 'Sari',
      witness_signature: 'data:image/png;base64,BB',
    });
    expect([onlyName.success, onlyImage.success, both.success]).toEqual([false, false, true]);
  });

  it('P2-07 menolak peran selain pasien atau wali', () => {
    expect(signSchema.safeParse({ ...valid, signer_role: 'dokter' }).success).toBe(false);
  });
});

describe('looksLikeSignature', () => {
  it('P2-07 sentuhan singkat atau titik tunggal bukan tanda tangan', () => {
    expect(looksLikeSignature([])).toBe(false);
    expect(looksLikeSignature(Array.from({ length: 3 }, (_, i) => ({ x: i, y: 0 })))).toBe(false);
    expect(looksLikeSignature(Array.from({ length: 20 }, () => ({ x: 5, y: 5 })))).toBe(false);
  });

  it('P2-07 goresan yang membentang dianggap tanda tangan', () => {
    expect(looksLikeSignature(Array.from({ length: 20 }, (_, i) => ({ x: i * 4, y: i })))).toBe(
      true,
    );
  });
});
