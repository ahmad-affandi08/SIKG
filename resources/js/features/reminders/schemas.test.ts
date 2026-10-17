import { describe, expect, it } from 'vitest';

import { reminderSettingsSchema, unknownPlaceholders } from './schemas';

const kind = { enabled: true, template: '', api_template: '' };
const base = {
  enabled: true,
  mode: 'link' as const,
  recall_months: '6',
  api_phone_number_id: '',
  api_token: '',
  api_language: 'id',
  kinds: { h1: kind, h0: kind, recall: kind },
};

describe('P2-03 pengaturan pengingat', () => {
  it('mode tautan sah tanpa data API', () => {
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

  it('mode API butuh ID nomor telepon dan nama templat berhuruf kecil', () => {
    expect(reminderSettingsSchema.safeParse({ ...base, mode: 'api' }).success).toBe(false);
    expect(
      reminderSettingsSchema.safeParse({ ...base, mode: 'api', api_phone_number_id: '123456' })
        .success,
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
