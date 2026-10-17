import { describe, expect, it } from 'vitest';

import {
  clinicProfileSchema,
  clinicSettingsSchema,
  holidaySchema,
  logoFileIssue,
} from '@/features/clinic/schemas';

const VALID = {
  timezone: 'Asia/Jakarta',
  session_timeout_minutes: 30,
  medical_record_pattern: 'RM-{seq:6}',
  invoice_pattern: 'INV-{yyyy}-{seq:5}',
  discount_limits: { pemilik: 100, admin: 100, kasir: 10 },
  operating_hours: { '1': { open: '08:00', close: '17:00' }, '7': null },
};

describe('SYS-04 skema pengaturan klinik', () => {
  it('menerima pengaturan sah', () => {
    expect(clinicSettingsSchema.safeParse(VALID).success).toBe(true);
  });

  it('KSR-02 batas diskon per peran harus bilangan bulat 0 sampai 100', () => {
    const limits = (kasir: number) => ({
      ...VALID,
      discount_limits: { pemilik: 100, admin: 100, kasir },
    });
    expect(clinicSettingsSchema.safeParse(limits(0)).success).toBe(true);
    expect(clinicSettingsSchema.safeParse(limits(101)).success).toBe(false);
    expect(clinicSettingsSchema.safeParse(limits(10.5)).success).toBe(false);
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

describe('MST-01 profil klinik', () => {
  const valid = {
    name: 'Klinik Senyum',
    address: '',
    license_no: '',
    receipt_header: '',
    prescription_header: '',
  };

  it('nama klinik wajib', () => {
    expect(clinicProfileSchema.safeParse(valid).success).toBe(true);
    expect(clinicProfileSchema.safeParse({ ...valid, name: '  ' }).success).toBe(false);
  });

  it('logo hanya PNG atau JPG hingga 1 MB', () => {
    expect(logoFileIssue({ type: 'image/png', size: 1000 })).toBeNull();
    expect(logoFileIssue({ type: 'image/svg+xml', size: 1000 })).toBe(
      'Logo harus berformat PNG atau JPG.',
    );
    expect(logoFileIssue({ type: 'image/jpeg', size: 2 * 1024 * 1024 })).toBe(
      'Ukuran logo maksimal 1 MB.',
    );
  });
});
