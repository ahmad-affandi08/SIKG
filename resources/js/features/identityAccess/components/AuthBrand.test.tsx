import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { AuthBrand } from './AuthBrand';

describe('AuthBrand', () => {
  it('AUTH-01 menampilkan logo dan nama klinik bila tersedia', () => {
    render(
      <AuthBrand
        brand={{
          name: 'Klinik Gigi Senyum',
          logoUrl: '/brand/logo?v=abc',
        }}
      />,
    );

    expect(screen.getByRole('img', { name: 'Logo Klinik Gigi Senyum' })).toHaveAttribute(
      'src',
      '/brand/logo?v=abc',
    );
    expect(screen.getByText('Klinik Gigi Senyum')).toBeInTheDocument();
  });

  it('AUTH-01 klinik tanpa logo menampilkan nama dengan ikon aplikasi', () => {
    render(<AuthBrand brand={{ name: 'Klinik Gigi Senyum', logoUrl: null }} />);

    expect(screen.getByRole('img', { name: 'Logo SIKLIN' })).toHaveAttribute(
      'src',
      '/icons/icon-192.png',
    );
    expect(screen.getByText('Klinik Gigi Senyum')).toBeInTheDocument();
  });

  it('AUTH-01 tanpa data klinik hanya ikon aplikasi', () => {
    render(<AuthBrand brand={null} />);

    expect(screen.getByRole('img', { name: 'Logo SIKLIN' })).toBeInTheDocument();
    expect(screen.queryByText('Klinik Gigi Senyum')).not.toBeInTheDocument();
  });
});
