import type { ReactNode } from 'react';

interface FieldLabelProps {
  htmlFor: string;
  children: ReactNode;
  /** Isian wajib: tanda bintang merah di belakang label. */
  required?: boolean;
}

/** Label isian seragam. Tanda * hanya hiasan; kewajiban disampaikan ke pembaca layar lewat aria-required pada isiannya. */
export function FieldLabel({ htmlFor, children, required = false }: FieldLabelProps) {
  return (
    <label htmlFor={htmlFor} className="text-sm font-semibold">
      {children}
      {required ? (
        <span aria-hidden="true" className="text-signal-700 ml-0.5">
          *
        </span>
      ) : null}
    </label>
  );
}
