import type { InputHTMLAttributes } from 'react';
import { useId } from 'react';

interface TextFieldProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'id'> {
  label: string;
  error?: string | undefined;
}

/** Input berlabel dengan pesan galat yang dibacakan pembaca layar. Target sentuh minimal 44 px. */
export function TextField({ label, error, className = '', ...rest }: TextFieldProps) {
  const id = useId();
  const errorId = `${id}-error`;

  return (
    <div className="flex flex-col gap-1">
      <label htmlFor={id} className="text-sm font-medium text-slate-700">
        {label}
      </label>
      <input
        id={id}
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
