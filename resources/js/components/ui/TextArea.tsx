import type { TextareaHTMLAttributes } from 'react';
import { useId } from 'react';

import { FieldLabel } from '@/components/ui/FieldLabel';
import { useRequired } from '@/lib/useRequired';

interface TextAreaProps extends Omit<
  TextareaHTMLAttributes<HTMLTextAreaElement>,
  'id' | 'required'
> {
  label: string;
  error?: string | undefined;
  /** Nama isian di server; dipakai mencari tanda wajib dari prop `required` halaman. */
  field?: string;
  /** Menimpa tanda wajib dari server. */
  required?: boolean;
}

/** Isian teks panjang berlabel dengan pesan galat yang dibacakan pembaca layar. */
export function TextArea({
  label,
  error,
  field,
  required,
  className = '',
  rows = 8,
  ...rest
}: TextAreaProps) {
  const id = useId();
  const errorId = `${id}-error`;
  const fromServer = useRequired(field);
  const isRequired = required ?? fromServer;

  return (
    <div className="flex flex-col gap-1">
      <FieldLabel htmlFor={id} required={isRequired}>
        {label}
      </FieldLabel>
      <textarea
        id={id}
        rows={rows}
        aria-required={isRequired ? true : undefined}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? errorId : undefined}
        className={`field h-auto py-1 ${className}`}
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
