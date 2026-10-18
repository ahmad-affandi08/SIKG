import type { ComponentPropsWithRef } from 'react';

type Variant = 'primary' | 'secondary' | 'danger';

const VARIANTS: Record<Variant, string> = {
  primary: 'btn-primary',
  secondary: 'btn-secondary',
  danger: 'btn-danger',
};

interface ButtonProps extends ComponentPropsWithRef<'button'> {
  variant?: Variant;
  loading?: boolean;
}

/** Target sentuh minimal 44 px (docs/rules/frontend.md bagian 6). */
export function Button({
  variant = 'primary',
  loading = false,
  disabled,
  className = '',
  children,
  type = 'button',
  ...rest
}: ButtonProps) {
  return (
    <button
      type={type}
      disabled={disabled === true || loading}
      aria-busy={loading}
      className={`btn disabled:cursor-not-allowed disabled:opacity-60 ${VARIANTS[variant]} ${className}`}
      {...rest}
    >
      {loading ? 'Memproses...' : children}
    </button>
  );
}
