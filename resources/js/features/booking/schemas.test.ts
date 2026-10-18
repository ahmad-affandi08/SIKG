import { describe, expect, it } from 'vitest';

import {
  bookingFormSchema,
  bookingSettingsSchema,
  rejectSchema,
  verifyCodeSchema,
} from './schemas';

const valid = {
  doctor_id: '01HZ0000000000000000000000',
  date: '2025-06-20',
  time: '09:30',
  name: 'Budi Santoso',
  phone: '0812 3456 7890',
  complaint: '',
  consent: true,
};

describe('P3-01 skema booking online', () => {
  it('menerima isian yang lengkap', () => {
    expect(bookingFormSchema.safeParse(valid).success).toBe(true);
  });

  it('menolak nama pendek, nomor tidak sah, jam kosong, dan tanpa persetujuan', () => {
    expect(bookingFormSchema.safeParse({ ...valid, name: 'Bu' }).success).toBe(false);
    expect(bookingFormSchema.safeParse({ ...valid, phone: 'abc' }).success).toBe(false);
    expect(bookingFormSchema.safeParse({ ...valid, time: '' }).success).toBe(false);
    expect(bookingFormSchema.safeParse({ ...valid, consent: false }).success).toBe(false);
  });

  it('kode verifikasi harus 6 angka', () => {
    expect(verifyCodeSchema.safeParse({ code: '123456' }).success).toBe(true);
    expect(verifyCodeSchema.safeParse({ code: '12345' }).success).toBe(false);
    expect(verifyCodeSchema.safeParse({ code: 'abcdef' }).success).toBe(false);
  });

  it('alasan penolakan wajib', () => {
    expect(rejectSchema.safeParse({ reason: '  ' }).success).toBe(false);
    expect(rejectSchema.safeParse({ reason: 'Dokter berhalangan' }).success).toBe(true);
  });

  it('alamat halaman hanya huruf kecil, angka, dan tanda hubung', () => {
    const base = {
      enabled: true,
      slug: 'klinik-sehat',
      slot_minutes: '30',
      horizon_days: '30',
      min_lead_hours: '2',
      hold_hours: '24',
      max_active_per_phone: '2',
      max_requests_per_hour: '30',
      otp_template: '',
      welcome_text: '',
    };
    expect(bookingSettingsSchema.safeParse(base).success).toBe(true);
    expect(bookingSettingsSchema.safeParse({ ...base, slug: 'Klinik Sehat' }).success).toBe(false);
    expect(bookingSettingsSchema.safeParse({ ...base, slug: '' }).success).toBe(true);
  });

  it('P3-01 batas permintaan per jam antara 5 dan 500', () => {
    const base = {
      enabled: true,
      slug: 'klinik-sehat',
      slot_minutes: '30',
      horizon_days: '30',
      min_lead_hours: '2',
      hold_hours: '24',
      max_active_per_phone: '2',
      max_requests_per_hour: '30',
      otp_template: '',
      welcome_text: '',
    };
    expect(bookingSettingsSchema.safeParse({ ...base, max_requests_per_hour: '4' }).success).toBe(
      false,
    );
    expect(bookingSettingsSchema.safeParse({ ...base, max_requests_per_hour: '501' }).success).toBe(
      false,
    );
    expect(bookingSettingsSchema.safeParse({ ...base, max_requests_per_hour: '60' }).success).toBe(
      true,
    );
  });
});
