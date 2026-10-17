import type { SelectHTMLAttributes } from 'react';
import { useId } from 'react';

import { FieldLabel } from '@/components/ui/FieldLabel';
import { useRequired } from '@/lib/useRequired';

export interface SelectOption {
  value: string;
  label: string;
}

interface SelectProps extends Omit<SelectHTMLAttributes<HTMLSelectElement>, 'id' | 'required'> {
  label: string;
  options: readonly SelectOption[];
  /** Teks pilihan kosong; tanpa ini tidak ada pilihan kosong. */
  placeholder?: string;
  error?: string | undefined;
  /** Nama isian di server; dipakai mencari tanda wajib dari prop `required` halaman. */
  field?: string;
  /** Menimpa tanda wajib dari server (untuk kewajiban bersyarat di layar). */
  required?: boolean;
}

export function Select({
  label,
  options,
  placeholder,
  error,
  field,
  required,
  className = '',
  ...rest
}: SelectProps) {
  const id = useId();
  const errorId = `${id}-error`;
  const fromServer = useRequired(field);
  const isRequired = required ?? fromServer;

  return (
    <div className="flex flex-col gap-1">
      <FieldLabel htmlFor={id} required={isRequired}>
        {label}
      </FieldLabel>
      <select
        id={id}
        aria-required={isRequired ? true : undefined}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? errorId : undefined}
        className={`field ${className}`}
        {...rest}
      >
        {placeholder !== undefined ? <option value="">{placeholder}</option> : null}
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      {error ? (
        <p id={errorId} role="alert" className="text-danger-600 text-sm">
          {error}
        </p>
      ) : null}
    </div>
  );
}
