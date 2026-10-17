import { describe, expect, it } from 'vitest';

import { loginSchema, userFormSchema } from '@/features/identityAccess/schemas';

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

describe('AUTH-03 skema form pengguna', () => {
  const valid = {
    name: 'Sari',
    email: 'sari@contoh.test',
    username: 'sari.w',
    role: 'kasir',
    branch_id: '',
  };

  it('menerima data sah dan memangkas spasi', () => {
    const result = userFormSchema.safeParse({ ...valid, name: '  Sari  ' });

    expect(result.success && result.data.name).toBe('Sari');
  });

  it.each([
    ['nama kosong', { name: ' ' }, 'Nama wajib diisi.'],
    ['email buruk', { email: 'bukan-email' }, 'Format email tidak valid.'],
    ['email kosong', { email: '' }, 'Email wajib diisi.'],
    ['peran kosong', { role: '' }, 'Peran wajib dipilih.'],
  ])('menolak %s dengan pesan Indonesia', (_label, override, message) => {
    const result = userFormSchema.safeParse({ ...valid, ...override });

    expect(result.success).toBe(false);
    expect(!result.success && result.error.issues.map((issue) => issue.message)).toContain(message);
  });

  it('menolak nama pengguna dengan spasi', () => {
    expect(userFormSchema.safeParse({ ...valid, username: 'a b' }).success).toBe(false);
  });
});
