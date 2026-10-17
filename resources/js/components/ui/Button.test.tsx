import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { Button } from '@/components/ui/Button';

describe('NFR-08 komponen Button', () => {
  it('dapat diklik dan dapat diakses dengan nama', async () => {
    const onClick = vi.fn();
    render(<Button onClick={onClick}>Simpan</Button>);

    await userEvent.click(screen.getByRole('button', { name: 'Simpan' }));

    expect(onClick).toHaveBeenCalledOnce();
  });

  it('tidak dapat diklik saat memuat dan memberi tahu pembaca layar', async () => {
    const onClick = vi.fn();
    render(
      <Button loading onClick={onClick}>
        Simpan
      </Button>,
    );

    const button = screen.getByRole('button', { name: 'Memproses...' });
    await userEvent.click(button);

    expect(button).toBeDisabled();
    expect(button).toHaveAttribute('aria-busy', 'true');
    expect(onClick).not.toHaveBeenCalled();
  });
});
