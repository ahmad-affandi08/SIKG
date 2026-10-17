import { describe, expect, it } from 'vitest';

import {
  changePasswordSchema,
  loginSchema,
  twoFactorChangeSchema,
  twoFactorCodeSchema,
  userFormSchema,
} from '@/features/identityAccess/schemas';

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

describe('AUTH-06 skema ganti kata sandi', () => {
  const valid = {
    current_password: 'kata-lama-123',
    password: 'KataBaruAman2024',
    password_confirmation: 'KataBaruAman2024',
  };
  const messages = (input: object) => {
    const result = changePasswordSchema.safeParse(input);
    return result.success ? [] : result.error.issues.map((issue) => issue.message);
  };

  it('menerima kata sandi yang memenuhi aturan', () => {
    expect(changePasswordSchema.safeParse(valid).success).toBe(true);
  });

  it.each([
    [
      'terlalu pendek',
      { password: 'Pendek1', password_confirmation: 'Pendek1' },
      'Kata sandi baru minimal 10 karakter.',
    ],
    [
      'tanpa angka',
      { password: 'HurufSajaPanjang', password_confirmation: 'HurufSajaPanjang' },
      'Kata sandi baru harus memuat angka.',
    ],
    [
      'tanpa huruf',
      { password: '12345678901234', password_confirmation: '12345678901234' },
      'Kata sandi baru harus memuat huruf.',
    ],
    [
      'konfirmasi beda',
      { password_confirmation: 'Lain12345678' },
      'Konfirmasi kata sandi tidak sama.',
    ],
    [
      'sama dengan lama',
      { password: 'kata-lama-123', password_confirmation: 'kata-lama-123' },
      'Kata sandi baru harus berbeda dari kata sandi saat ini.',
    ],
    ['kata sandi lama kosong', { current_password: '' }, 'Kata sandi saat ini wajib diisi.'],
  ])('menolak %s', (_label, override, message) => {
    expect(messages({ ...valid, ...override })).toContain(message);
  });
});

describe('AUTH-05 formulir verifikasi dua langkah', () => {
  it('kata sandi dan kode wajib diisi', () => {
    expect(twoFactorChangeSchema.safeParse({ password: '', code: '' }).success).toBe(false);
    expect(
      twoFactorChangeSchema.safeParse({ password: 'rahasia123', code: ' 123456 ' }).success,
    ).toBe(true);
  });

  it('kode pemulihan dan kode autentikator sama-sama diterima bentuknya', () => {
    expect(twoFactorCodeSchema.safeParse('k3m9p-x2tqa').success).toBe(true);
    expect(twoFactorCodeSchema.safeParse('x'.repeat(40)).success).toBe(false);
  });
});
