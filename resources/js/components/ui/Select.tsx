import type { SelectHTMLAttributes } from 'react';
import { useId } from 'react';

export interface SelectOption {
  value: string;
  label: string;
}

interface SelectProps extends Omit<SelectHTMLAttributes<HTMLSelectElement>, 'id'> {
  label: string;
  options: readonly SelectOption[];
  /** Teks pilihan kosong; tanpa ini tidak ada pilihan kosong. */
  placeholder?: string;
  error?: string | undefined;
}

export function Select({
  label,
  options,
  placeholder,
  error,
  className = '',
  ...rest
}: SelectProps) {
  const id = useId();
  const errorId = `${id}-error`;

  return (
    <div className="flex flex-col gap-1">
      <label htmlFor={id} className="text-sm font-semibold">
        {label}
      </label>
      <select
        id={id}
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
