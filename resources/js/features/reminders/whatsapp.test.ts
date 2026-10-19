import { describe, expect, it } from 'vitest';

import { missingRequired, whatsappSettingsSchema } from './whatsapp';
import type { WhatsAppSettingsValues } from './whatsapp';

const base: WhatsAppSettingsValues = {
  provider: 'fonnte',
  phone_number_id: '',
  token: '',
  base_url: '',
  session: '',
  language: 'id',
  staff_template: '',
};

describe('SYS-05 pengaturan saluran WhatsApp', () => {
  it('menerima isian sah dan menolak bentuk yang salah', () => {
    expect(whatsappSettingsSchema.safeParse(base).success).toBe(true);
    expect(whatsappSettingsSchema.safeParse({ ...base, phone_number_id: 'abc' }).success).toBe(
      false,
    );
    expect(
      whatsappSettingsSchema.safeParse({ ...base, base_url: 'http://wablas.test' }).success,
    ).toBe(false);
    expect(
      whatsappSettingsSchema.safeParse({ ...base, base_url: 'https://tegal.wablas.com' }).success,
    ).toBe(true);
    expect(whatsappSettingsSchema.safeParse({ ...base, staff_template: 'Staf Baru' }).success).toBe(
      false,
    );
    expect(whatsappSettingsSchema.safeParse({ ...base, session: 'ruang praktik' }).success).toBe(
      false,
    );
  });

  it('isian wajib kosong dilaporkan, token tersimpan dianggap terisi', () => {
    expect(missingRequired(base, ['token'], false)).toEqual({ token: 'Token wajib diisi.' });
    expect(missingRequired(base, ['token'], true)).toEqual({});
    expect(missingRequired({ ...base, provider: 'wablas' }, ['token', 'base_url'], false)).toEqual({
      token: 'Token wajib diisi.',
      base_url: 'Alamat server wajib diisi.',
    });
    expect(
      missingRequired(
        { ...base, provider: 'meta', token: 'x' },
        ['phone_number_id', 'token'],
        false,
      ),
    ).toEqual({
      phone_number_id: 'ID nomor telepon wajib diisi.',
    });
  });
});
