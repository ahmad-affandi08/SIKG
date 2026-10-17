import { useId, useState } from 'react';

import { FieldLabel } from '@/components/ui/FieldLabel';
import { useRequired } from '@/lib/useRequired';

import type { MedicineOption } from '@/features/pharmacy/api';
import { useMedicineSearch } from '@/features/pharmacy/queries';
import { formatRupiah } from '@/lib/format';
import { useDebouncedValue } from '@/lib/useDebouncedValue';

interface MedicinePickerProps {
  /** Nama isian di server; dipakai mencari tanda wajib dari prop `required` halaman. */
  field?: string;
  label?: string;
  selected: { id: string; name: string } | null;
  onSelect: (medicine: MedicineOption | null) => void;
  error?: string | undefined;
}

/** Memilih obat dari katalog dengan stok tersedia dan harga jual saat ini. */
export function MedicinePicker({
  field,
  label = 'Cari obat di katalog',
  selected,
  onSelect,
  error,
}: MedicinePickerProps) {
  const [text, setText] = useState('');
  const term = useDebouncedValue(text.trim());
  const query = useMedicineSearch(selected ? '' : term);
  const id = useId();
  const isRequired = useRequired(field);
  const results = query.data ?? [];

  if (selected) {
    return (
      <div className="flex flex-col gap-1">
        <span className="text-sm font-semibold">
          Obat
          {isRequired ? (
            <span aria-hidden="true" className="text-signal-700 ml-0.5">
              *
            </span>
          ) : null}
        </span>
        <div className="flex flex-wrap items-center gap-3">
          <span className="text-sm">{selected.name}</span>
          <button
            type="button"
            className="btn btn-secondary"
            onClick={() => {
              onSelect(null);
              setText('');
            }}
          >
            Ganti obat
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-1">
      <FieldLabel htmlFor={id} required={isRequired}>
        {label}
      </FieldLabel>
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
        <p className="text-muted text-sm">Obat tidak ditemukan.</p>
      ) : null}
      {results.length > 0 ? (
        <ul className="card">
          {results.map((medicine) => (
            <li key={medicine.id}>
              <button
                type="button"
                className="min-h-touch hover:bg-brand-50 w-full px-3 text-left text-sm"
                onClick={() => {
                  onSelect(medicine);
                }}
              >
                <span className="font-medium">{medicine.name}</span> | stok {medicine.available}{' '}
                {medicine.unit} | {formatRupiah(medicine.sell_price_amount)}
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
