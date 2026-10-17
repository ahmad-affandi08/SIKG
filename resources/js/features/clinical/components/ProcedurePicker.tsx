import { useId, useState } from 'react';

import type { ProcedureOption } from '@/features/clinical/api';
import { useProcedureSearch } from '@/features/clinical/queries';
import { formatRupiah } from '@/lib/format';
import { useDebouncedValue } from '@/lib/useDebouncedValue';

interface ProcedurePickerProps {
  selected: ProcedureOption | null;
  onSelect: (procedure: ProcedureOption | null) => void;
  error?: string | undefined;
}

/** Memilih tindakan dari katalog (kode atau nama) dengan tarif saat ini. */
export function ProcedurePicker({ selected, onSelect, error }: ProcedurePickerProps) {
  const [text, setText] = useState('');
  const term = useDebouncedValue(text.trim());
  const query = useProcedureSearch(selected ? '' : term);
  const id = useId();
  const results = query.data ?? [];

  if (selected) {
    return (
      <div className="flex flex-col gap-1">
        <span className="text-sm font-medium text-slate-700">Tindakan</span>
        <div className="flex flex-wrap items-center gap-3">
          <span className="text-sm">
            {selected.code} - {selected.name} ({formatRupiah(selected.price_amount)})
          </span>
          <button
            type="button"
            className="btn btn-secondary"
            onClick={() => {
              onSelect(null);
              setText('');
            }}
          >
            Ganti tindakan
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-1">
      <label htmlFor={id} className="text-sm font-medium text-slate-700">
        Cari tindakan (kode atau nama)
      </label>
      <input
        id={id}
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
        <p className="text-sm text-slate-600">Tindakan tidak ditemukan.</p>
      ) : null}
      {results.length > 0 ? (
        <ul className="card">
          {results.map((procedure) => (
            <li key={procedure.id}>
              <button
                type="button"
                className="min-h-touch w-full px-3 text-left text-sm hover:bg-slate-100"
                onClick={() => {
                  onSelect(procedure);
                }}
              >
                <span className="font-medium">{procedure.code}</span> {procedure.name} (
                {formatRupiah(procedure.price_amount)})
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
