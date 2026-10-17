import { useId, useState } from 'react';

import { usePatientSearch } from '@/features/patient/queries';
import type { PatientSummary } from '@/features/patient/api';
import { useDebouncedValue } from '@/lib/useDebouncedValue';

interface PatientPickerProps {
  selected: { id: string; name: string } | null;
  onSelect: (patient: PatientSummary | null) => void;
  error?: string | undefined;
}

/** Memilih pasien lewat pencarian (nama, nomor RM, NIK, atau HP). Kata kunci dikirim lewat POST, bukan URL (M11). */
export function PatientPicker({ selected, onSelect, error }: PatientPickerProps) {
  const [text, setText] = useState('');
  const term = useDebouncedValue(text.trim(), 300);
  const query = usePatientSearch(selected ? '' : term);
  const id = useId();
  const results = query.data ?? [];

  if (selected) {
    return (
      <div className="flex flex-col gap-1">
        <span className="text-sm font-medium text-slate-900">Pasien</span>
        <div className="flex items-center gap-3">
          <span className="text-sm">{selected.name}</span>
          <button
            type="button"
            className="min-h-touch rounded-control px-3 text-sm ring-1 ring-slate-300"
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
      <label htmlFor={id} className="text-sm font-medium text-slate-900">
        Cari pasien (nama, nomor RM, NIK, atau HP)
      </label>
      <input
        id={id}
        type="search"
        autoComplete="off"
        value={text}
        onChange={(event) => {
          setText(event.target.value);
        }}
        aria-invalid={error ? true : undefined}
        className="min-h-touch rounded-control px-3 ring-1 ring-slate-300"
      />
      {error ? (
        <p role="alert" className="text-danger-600 text-sm">
          {error}
        </p>
      ) : null}
      {term !== '' && query.isSuccess && results.length === 0 ? (
        <p className="text-sm text-slate-600">Pasien tidak ditemukan.</p>
      ) : null}
      {results.length > 0 ? (
        <ul className="rounded-control ring-1 ring-slate-200">
          {results.map((patient) => (
            <li key={patient.id}>
              <button
                type="button"
                className="min-h-touch w-full px-3 text-left text-sm hover:bg-slate-100"
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
