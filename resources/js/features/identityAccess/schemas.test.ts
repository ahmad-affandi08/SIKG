import { describe, expect, it } from 'vitest';

import { loginSchema } from '@/features/identityAccess/schemas';

describe('AUTH-01 skema form login', () => {
  it('menerima email atau nama pengguna dengan kata sandi', () => {
    expect(
      loginSchema.safeParse({ identifier: 'budi@contoh.test', password: 'rahasia' }).success,
    ).toBe(true);
    expect(loginSchema.safeParse({ identifier: 'budi', password: 'rahasia' }).success).toBe(true);
  });

  it('memangkas spasi di pengenal', () => {
    const result = loginSchema.safeParse({ identifier: '  budi  ', password: 'x' });

    expect(result.success && result.data.identifier).toBe('budi');
  });

  it('menolak kosong dengan pesan Indonesia', () => {
    const result = loginSchema.safeParse({ identifier: '   ', password: '' });

    expect(result.success).toBe(false);
    if (!result.success) {
      const messages = result.error.issues.map((issue) => issue.message);
      expect(messages).toContain('Email atau nama pengguna wajib diisi.');
      expect(messages).toContain('Kata sandi wajib diisi.');
    }
  });
});
