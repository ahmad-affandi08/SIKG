import { describe, expect, it } from 'vitest';

import { reminderSettingsSchema, unknownPlaceholders } from './schemas';

const kind = { enabled: true, template: '', api_template: '' };
const base = {
  enabled: true,
  recall_months: '6',
  kinds: { h1: kind, h0: kind, recall: kind },
};

describe('P2-03 pengaturan pengingat', () => {
  it('pengaturan isi pengingat sah tanpa data saluran (saluran diatur di Pengaturan WhatsApp)', () => {
    expect(reminderSettingsSchema.safeParse(base).success).toBe(true);
  });

  it('penanda templat yang tidak dikenal ditolak', () => {
    expect(unknownPlaceholders('Halo {nama}, {tanggal} {salah} {lagi}')).toEqual(['salah', 'lagi']);
    const result = reminderSettingsSchema.safeParse({
      ...base,
      kinds: { ...base.kinds, h1: { ...kind, template: 'Halo {namma}' } },
    });
    expect(result.success).toBe(false);
  });

  it('nama templat WhatsApp Resmi harus huruf kecil, angka, dan garis bawah', () => {
    expect(
      reminderSettingsSchema.safeParse({
        ...base,
        kinds: { ...base.kinds, h0: { ...kind, api_template: 'pengingat_h0' } },
      }).success,
    ).toBe(true);
    expect(
      reminderSettingsSchema.safeParse({
        ...base,
        kinds: { ...base.kinds, h0: { ...kind, api_template: 'Pengingat H0' } },
      }).success,
    ).toBe(false);
  });

  it('jarak kontrol berkala 1 sampai 24 bulan', () => {
    expect(reminderSettingsSchema.safeParse({ ...base, recall_months: '0' }).success).toBe(false);
    expect(reminderSettingsSchema.safeParse({ ...base, recall_months: '25' }).success).toBe(false);
    expect(reminderSettingsSchema.safeParse({ ...base, recall_months: '12' }).success).toBe(true);
  });
});
