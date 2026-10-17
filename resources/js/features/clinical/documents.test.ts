import { describe, expect, it } from 'vitest';

import {
  documentFormSchema,
  renderTemplate,
  requiredExtras,
  restSentence,
  toDisplayDate,
  unresolvedPlaceholders,
} from '@/features/clinical/documents';

describe('RM-09 dan RM-10 templat dokumen klinis', () => {
  it('mengisi penanda dari isian dan membiarkan yang kosong terlihat', () => {
    const text = renderTemplate('Pasien {{pasien}} (RM {{no_rm}}) dirujuk ke {{tujuan}}.', {
      pasien: 'Budi Santoso',
      no_rm: 'RM-000001',
      tujuan: '  ',
    });

    expect(text).toBe('Pasien Budi Santoso (RM RM-000001) dirujuk ke {{tujuan}}.');
    expect(unresolvedPlaceholders(text)).toEqual(['{{tujuan}}']);
  });

  it('penanda boleh memakai spasi di dalam kurung dan diisi ulang di tiap kemunculan', () => {
    expect(renderTemplate('{{ pasien }} dan {{pasien}}', { pasien: 'Ani' })).toBe('Ani dan Ani');
  });

  it('kalimat istirahat dan format tanggal', () => {
    expect(restSentence('3')).toBe('disarankan beristirahat selama 3 hari');
    expect(restSentence('')).toBe('tidak memerlukan istirahat khusus');
    expect(restSentence('0')).toBe('tidak memerlukan istirahat khusus');
    expect(restSentence('abc')).toBe('tidak memerlukan istirahat khusus');
    expect(toDisplayDate('2025-06-16')).toBe('16/06/2025');
  });

  it('formulir menolak teks yang masih berpenanda dan teks terlalu pendek', () => {
    const ok = { doctor_id: 'D', body: 'Dengan ini pasien dirujuk untuk pemeriksaan lanjutan.' };
    expect(documentFormSchema.safeParse(ok).success).toBe(true);
    expect(documentFormSchema.safeParse({ ...ok, body: 'Pendek' }).success).toBe(false);
    expect(
      documentFormSchema.safeParse({
        ...ok,
        body: 'Pasien dirujuk ke {{tujuan}} untuk pemeriksaan.',
      }).success,
    ).toBe(false);
    expect(documentFormSchema.safeParse({ ...ok, doctor_id: '' }).success).toBe(false);
  });

  it('isian wajib menurut jenis dokumen', () => {
    expect(requiredExtras('consent')).toEqual(['tindakan']);
    expect(requiredExtras('certificate')).toEqual(['keperluan']);
    expect(requiredExtras('referral')).toEqual(['tujuan', 'alasan']);
  });
});
