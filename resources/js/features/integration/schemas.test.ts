import { describe, expect, it } from 'vitest';

import { nikIssue, satusehatSettingsSchema } from './schemas';

const base = {
  enabled: false,
  environment: 'staging' as const,
  organization_id: '',
  location_id: '',
  location_name: '',
  client_id: '',
  client_secret: '',
};

describe('P2-01 pengaturan SATUSEHAT', () => {
  it('boleh disimpan kosong selama belum diaktifkan', () => {
    expect(satusehatSettingsSchema.safeParse(base).success).toBe(true);
  });

  it('mengaktifkan butuh ID organisasi, ID lokasi, dan client ID', () => {
    const result = satusehatSettingsSchema.safeParse({ ...base, enabled: true });
    expect(result.success).toBe(false);
    expect(result.error?.issues.map((i) => i.path[0])).toEqual([
      'organization_id',
      'location_id',
      'client_id',
    ]);
    expect(
      satusehatSettingsSchema.safeParse({
        ...base,
        enabled: true,
        organization_id: 'abc-123',
        location_id: 'loc-1',
        client_id: 'client',
      }).success,
    ).toBe(true);
  });

  it('ID hanya huruf, angka, dan tanda hubung', () => {
    expect(satusehatSettingsSchema.safeParse({ ...base, organization_id: 'abc 123' }).success).toBe(
      false,
    );
  });

  it('NIK dokter kosong atau 16 digit', () => {
    expect(nikIssue('')).toBeNull();
    expect(nikIssue('9999 0101 0190 0001')).toBeNull();
    expect(nikIssue('123')).toBe('NIK harus 16 digit angka.');
  });
});
