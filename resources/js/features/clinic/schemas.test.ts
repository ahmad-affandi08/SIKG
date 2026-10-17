import { describe, expect, it } from 'vitest';

import { clinicSettingsSchema, holidaySchema } from '@/features/clinic/schemas';

const VALID = {
  timezone: 'Asia/Jakarta',
  session_timeout_minutes: 30,
  medical_record_pattern: 'RM-{seq:6}',
  invoice_pattern: 'INV-{yyyy}-{seq:5}',
  operating_hours: { '1': { open: '08:00', close: '17:00' }, '7': null },
};

describe('SYS-04 skema pengaturan klinik', () => {
  it('menerima pengaturan sah', () => {
    expect(clinicSettingsSchema.safeParse(VALID).success).toBe(true);
  });

  it('menolak batas sesi di bawah 5 menit dengan pesan Indonesia', () => {
    const result = clinicSettingsSchema.safeParse({ ...VALID, session_timeout_minutes: 3 });

    expect(result.success).toBe(false);
    expect(!result.success && result.error.issues[0]?.message).toBe('Minimal 5 menit.');
  });

  it.each([
    ['tanpa {seq}', 'RM-001'],
    ['dua {seq}', '{seq}-{seq}'],
    ['karakter terlarang', 'RM {seq}'],
    ['token tak dikenal', '{xyz}-{seq}'],
  ])('menolak format nomor %s', (_label, pattern) => {
    expect(
      clinicSettingsSchema.safeParse({ ...VALID, medical_record_pattern: pattern }).success,
    ).toBe(false);
  });

  it('menolak jam tutup yang tidak lebih lambat dari jam buka', () => {
    const result = clinicSettingsSchema.safeParse({
      ...VALID,
      operating_hours: { '1': { open: '17:00', close: '08:00' } },
    });

    expect(result.success).toBe(false);
  });
});

describe('SYS-04 skema hari libur', () => {
  it('menerima tanggal dan nama', () => {
    expect(holidaySchema.safeParse({ date: '2030-08-17', name: 'Hari Kemerdekaan' }).success).toBe(
      true,
    );
  });

  it('menolak kosong dengan pesan Indonesia', () => {
    const result = holidaySchema.safeParse({ date: '', name: ' ' });

    expect(result.success).toBe(false);
    if (!result.success) {
      const messages = result.error.issues.map((issue) => issue.message);
      expect(messages).toContain('Tanggal wajib diisi.');
      expect(messages).toContain('Nama hari libur wajib diisi.');
    }
  });
});
