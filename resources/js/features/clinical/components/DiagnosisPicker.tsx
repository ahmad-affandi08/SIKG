import { useId, useState } from 'react';

import { useDiagnosisSearch } from '@/features/clinical/queries';
import type { Diagnosis } from '@/features/clinical/api';
import { useDebouncedValue } from '@/lib/useDebouncedValue';

interface DiagnosisPickerProps {
  onSelect: (diagnosis: Diagnosis) => void;
  label?: string;
}

/** Pencarian diagnosa ICD-10 dengan autocomplete: ketik kode (K02) atau nama (pulpitis), pilih satu. */
export function DiagnosisPicker({
  onSelect,
  label = 'Cari diagnosa (kode atau nama)',
}: DiagnosisPickerProps) {
  const [text, setText] = useState('');
  const term = useDebouncedValue(text.trim());
  const query = useDiagnosisSearch(term);
  const inputId = useId();
  const listId = `${inputId}-list`;
  const results = query.data ?? [];
  const showList = term !== '' && results.length > 0;

  return (
    <div className="flex flex-col gap-1">
      <label htmlFor={inputId} className="text-sm font-medium text-slate-900">
        {label}
      </label>
      <input
        id={inputId}
        role="combobox"
        aria-expanded={showList}
        aria-controls={listId}
        aria-autocomplete="list"
        autoComplete="off"
        value={text}
        onChange={(event) => {
          setText(event.target.value);
        }}
        className="min-h-touch rounded-control px-3 ring-1 ring-slate-300"
      />
      {query.isError ? (
        <p role="alert" className="text-danger-600 text-sm">
          Pencarian diagnosa gagal. Coba lagi.
        </p>
      ) : null}
      {term !== '' && query.isSuccess && results.length === 0 ? (
        <p className="text-sm text-slate-600">Diagnosa tidak ditemukan.</p>
      ) : null}
      <ul
        id={listId}
        role="listbox"
        hidden={!showList}
        className="rounded-control ring-1 ring-slate-200"
      >
        {results.map((diagnosis) => (
          <li key={diagnosis.id} role="option" aria-selected={false}>
            <button
              type="button"
              className="min-h-touch w-full px-3 text-left text-sm hover:bg-slate-100"
              onClick={() => {
                onSelect(diagnosis);
                setText('');
              }}
            >
              <span className="font-medium">{diagnosis.code}</span> {diagnosis.name}
            </button>
          </li>
        ))}
      </ul>
    </div>
  );
}
