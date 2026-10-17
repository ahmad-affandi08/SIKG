import { useId, useState } from 'react';

import { FieldLabel } from '@/components/ui/FieldLabel';
import { useRequired } from '@/lib/useRequired';

import { usePatientSearch } from '@/features/patient/queries';
import type { PatientSummary } from '@/features/patient/api';
import { useDebouncedValue } from '@/lib/useDebouncedValue';

interface PatientPickerProps {
  /** Nama isian di server; dipakai mencari tanda wajib dari prop `required` halaman. */
  field?: string;
  selected: { id: string; name: string } | null;
  onSelect: (patient: PatientSummary | null) => void;
  error?: string | undefined;
}

/** Memilih pasien lewat pencarian (nama, nomor RM, NIK, atau HP). Kata kunci dikirim lewat POST, bukan URL (M11). */
export function PatientPicker({ selected, onSelect, error, field }: PatientPickerProps) {
  const [text, setText] = useState('');
  const term = useDebouncedValue(text.trim(), 300);
  const query = usePatientSearch(selected ? '' : term);
  const id = useId();
  const isRequired = useRequired(field);
  const results = query.data ?? [];

  if (selected) {
    return (
      <div className="flex flex-col gap-1">
        <span className="text-sm font-semibold">
          Pasien
          {isRequired ? (
            <span aria-hidden="true" className="text-signal-700 ml-0.5">
              *
            </span>
          ) : null}
        </span>
        <div className="flex items-center gap-3">
          <span className="text-sm">{selected.name}</span>
          <button
            type="button"
            className="btn btn-secondary"
            onClick={() => {
              onSelect(null);
              setText('');
            }}
          >
            Ganti pasien
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-1">
      <FieldLabel htmlFor={id} required={isRequired}>
        Cari pasien (nama, nomor RM, NIK, atau HP)
      </FieldLabel>
      <input
        id={id}
        type="search"
        autoComplete="off"
        value={text}
        onChange={(event) => {
          setText(event.target.value);
        }}
        aria-invalid={error ? true : undefined}
        className="field"
      />
      {error ? (
        <p role="alert" className="text-danger-600 text-sm">
          {error}
        </p>
      ) : null}
      {term !== '' && query.isSuccess && results.length === 0 ? (
        <p className="text-muted text-sm">Pasien tidak ditemukan.</p>
      ) : null}
      {results.length > 0 ? (
        <ul className="card">
          {results.map((patient) => (
            <li key={patient.id}>
              <button
                type="button"
                className="min-h-touch hover:bg-brand-50 w-full px-3 text-left text-sm"
                onClick={() => {
                  onSelect(patient);
                }}
              >
                <span className="font-medium">{patient.name}</span> ({patient.medical_record_no})
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
