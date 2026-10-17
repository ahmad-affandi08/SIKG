import { describe, expect, it } from 'vitest';

import { mergeFormSchema, summarizeCounts } from '@/features/patient/merge';

describe('PAS-07 formulir penggabungan pasien ganda', () => {
  it('alasan minimal 5 karakter dan konfirmasi wajib dicentang', () => {
    expect(mergeFormSchema.safeParse({ reason: 'Ganda', confirm: true }).success).toBe(true);
    const pendek = mergeFormSchema.safeParse({ reason: 'dup', confirm: true });
    expect(pendek.success).toBe(false);
    const tanpaKonfirmasi = mergeFormSchema.safeParse({
      reason: 'Pasien daftar dua kali',
      confirm: false,
    });
    expect(tanpaKonfirmasi.success).toBe(false);
  });

  it('ringkasan hanya menyebut data yang jumlahnya lebih dari nol', () => {
    expect(
      summarizeCounts([
        { label: 'Janji temu', count: 2 },
        { label: 'Tagihan', count: 0 },
        { label: 'Kunjungan', count: 1 },
      ]),
    ).toBe('janji temu 2, kunjungan 1');
    expect(summarizeCounts([{ label: 'Resep', count: 0 }])).toBe(
      'Pasien ganda belum memiliki data yang perlu dipindahkan.',
    );
  });
});
