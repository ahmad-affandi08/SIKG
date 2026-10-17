import { useId, useState } from 'react';

import { FieldLabel } from '@/components/ui/FieldLabel';
import { useRequired } from '@/lib/useRequired';

import { useDiagnosisSearch } from '@/features/clinical/queries';
import type { Diagnosis } from '@/features/clinical/api';
import { useDebouncedValue } from '@/lib/useDebouncedValue';

interface DiagnosisPickerProps {
  /** Nama isian di server; dipakai mencari tanda wajib dari prop `required` halaman. */
  field?: string;
  onSelect: (diagnosis: Diagnosis) => void;
  label?: string;
}

/** Pencarian diagnosa ICD-10 dengan autocomplete: ketik kode (K02) atau nama (pulpitis), pilih satu. */
export function DiagnosisPicker({
  field,
  onSelect,
  label = 'Cari diagnosa (kode atau nama)',
}: DiagnosisPickerProps) {
  const [text, setText] = useState('');
  const term = useDebouncedValue(text.trim());
  const query = useDiagnosisSearch(term);
  const inputId = useId();
  const isRequired = useRequired(field);
  const listId = `${inputId}-list`;
  const results = query.data ?? [];
  const showList = term !== '' && results.length > 0;

  return (
    <div className="flex flex-col gap-1">
      <FieldLabel htmlFor={inputId} required={isRequired}>
        {label}
      </FieldLabel>
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
        className="field"
      />
      {query.isError ? (
        <p role="alert" className="text-danger-600 text-sm">
          Pencarian diagnosa gagal. Coba lagi.
        </p>
      ) : null}
      {term !== '' && query.isSuccess && results.length === 0 ? (
        <p className="text-muted text-sm">Diagnosa tidak ditemukan.</p>
      ) : null}
      <ul id={listId} role="listbox" hidden={!showList} className="card">
        {results.map((diagnosis) => (
          <li key={diagnosis.id} role="option" aria-selected={false}>
            <button
              type="button"
              className="min-h-touch hover:bg-brand-50 w-full px-3 text-left text-sm"
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
