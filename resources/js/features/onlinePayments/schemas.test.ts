import { describe, expect, it } from 'vitest';

import { onlineSettingsSchema, startQrSchema } from './schemas';

describe('P3-05 skema pembayaran online', () => {
  it('jumlah QR kosong berarti seluruh sisa, selain itu bulat dan tidak melebihi sisa', () => {
    const schema = startQrSchema(500000);
    expect(schema.safeParse({ amount: '' }).success).toBe(true);
    expect(schema.safeParse({ amount: '250000' }).success).toBe(true);
    expect(schema.safeParse({ amount: '500001' }).success).toBe(false);
    expect(schema.safeParse({ amount: '0' }).success).toBe(false);
    expect(schema.safeParse({ amount: '1000.5' }).success).toBe(false);
  });

  it('pengaturan menolak lingkungan dan masa berlaku di luar rentang', () => {
    const base = {
      enabled: true,
      environment: 'sandbox',
      client_id: '',
      secret_key: '',
      payment_method_id: '',
      expiry_minutes: '15',
    };
    expect(onlineSettingsSchema.safeParse(base).success).toBe(true);
    expect(onlineSettingsSchema.safeParse({ ...base, environment: 'demo' }).success).toBe(false);
    expect(onlineSettingsSchema.safeParse({ ...base, expiry_minutes: '3' }).success).toBe(false);
    expect(onlineSettingsSchema.safeParse({ ...base, expiry_minutes: '2000' }).success).toBe(false);
  });
});
