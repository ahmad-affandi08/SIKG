import type { InputHTMLAttributes } from 'react';
import { useId } from 'react';

import { FieldLabel } from '@/components/ui/FieldLabel';
import { useRequired } from '@/lib/useRequired';

interface TextFieldProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'id' | 'required'> {
  label: string;
  error?: string | undefined;
  /** Nama isian di server; dipakai mencari tanda wajib dari prop `required` halaman. */
  field?: string;
  /** Menimpa tanda wajib dari server (untuk kewajiban bersyarat di layar). */
  required?: boolean;
}

/** Input berlabel dengan pesan galat yang dibacakan pembaca layar. Target sentuh minimal 44 px. */
export function TextField({
  label,
  error,
  field,
  required,
  className = '',
  ...rest
}: TextFieldProps) {
  const id = useId();
  const errorId = `${id}-error`;
  const fromServer = useRequired(field);
  const isRequired = required ?? fromServer;

  return (
    <div className="flex flex-col gap-1">
      <FieldLabel htmlFor={id} required={isRequired}>
        {label}
      </FieldLabel>
      <input
        id={id}
        aria-required={isRequired ? true : undefined}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? errorId : undefined}
        className={`field ${className}`}
        {...rest}
      />
      {error ? (
        <p id={errorId} role="alert" className="text-danger-600 text-sm">
          {error}
        </p>
      ) : null}
    </div>
  );
}
