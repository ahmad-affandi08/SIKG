import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { LoginForm } from '@/features/identityAccess/components/LoginForm';

describe('AUTH-01 formulir login', () => {
  it('semua input berlabel dan dapat diakses dengan nama', () => {
    render(<LoginForm onSubmit={vi.fn()} />);

    expect(screen.getByLabelText('Email atau nama pengguna')).toBeInTheDocument();
    expect(screen.getByLabelText('Kata sandi')).toHaveAttribute('type', 'password');
    expect(screen.getByRole('button', { name: 'Masuk' })).toBeEnabled();
  });

  it('menampilkan galat Zod berbahasa Indonesia dan tidak mengirim bila kosong', async () => {
    const onSubmit = vi.fn();
    render(<LoginForm onSubmit={onSubmit} />);

    await userEvent.click(screen.getByRole('button', { name: 'Masuk' }));

    const alerts = screen.getAllByRole('alert').map((node) => node.textContent);
    expect(alerts).toContain('Email atau nama pengguna wajib diisi.');
    expect(alerts).toContain('Kata sandi wajib diisi.');
    expect(screen.getByLabelText('Kata sandi')).toHaveAttribute('aria-invalid', 'true');
    expect(onSubmit).not.toHaveBeenCalled();
  });

  it('mengirim nilai yang sudah dipangkas bila sah', async () => {
    const onSubmit = vi.fn();
    render(<LoginForm onSubmit={onSubmit} />);

    await userEvent.type(screen.getByLabelText('Email atau nama pengguna'), '  budi  ');
    await userEvent.type(screen.getByLabelText('Kata sandi'), 'rahasia-123');
    await userEvent.click(screen.getByRole('button', { name: 'Masuk' }));

    expect(onSubmit).toHaveBeenCalledExactlyOnceWith({
      identifier: 'budi',
      password: 'rahasia-123',
    });
  });

  it('menampilkan galat dari server (kata sandi salah atau terlalu banyak percobaan)', () => {
    render(
      <LoginForm
        onSubmit={vi.fn()}
        serverErrors={{ identifier: 'Nama pengguna atau kata sandi salah.' }}
      />,
    );

    expect(screen.getByRole('alert')).toHaveTextContent('Nama pengguna atau kata sandi salah.');
  });

  it('tombol tidak aktif saat memproses agar tidak terkirim dua kali', () => {
    render(<LoginForm onSubmit={vi.fn()} processing />);

    expect(screen.getByRole('button', { name: 'Memproses...' })).toBeDisabled();
  });
});
