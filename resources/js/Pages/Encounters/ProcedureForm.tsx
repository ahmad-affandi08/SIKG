import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import type { ProcedureOption } from '@/features/clinical/api';
import { EncounterFormShell } from '@/features/clinical/components/EncounterFormShell';
import type { EncounterRef } from '@/features/clinical/components/EncounterFormShell';
import { collect } from '@/features/clinical/components/EncounterPanels';
import { ProcedurePicker } from '@/features/clinical/components/ProcedurePicker';
import { procedureEntrySchema } from '@/features/clinical/schemas';
import { SURFACES } from '@/features/clinical/tooth';

interface Prefill {
  plan_item_id: string;
  plan_title: string;
  procedure_id: string;
  code: string;
  name: string;
  scope: ProcedureOption['scope'];
  price_amount: number;
  tooth_fdi: number | null;
  quantity: number;
  notes: string | null;
}

interface Props {
  encounter: EncounterRef;
  jaws: SelectOption[];
  prefill?: Prefill | null;
}

export default function ProcedureForm({ encounter, jaws, prefill = null }: Props) {
  const [procedure, setProcedure] = useState<ProcedureOption | null>(
    prefill
      ? {
          id: prefill.procedure_id,
          code: prefill.code,
          name: prefill.name,
          scope: prefill.scope,
          price_amount: prefill.price_amount,
        }
      : null,
  );
  const [tooth, setTooth] = useState(prefill?.tooth_fdi ? String(prefill.tooth_fdi) : '');
  const [surfaces, setSurfaces] = useState<string[]>([]);
  const [jaw, setJaw] = useState('');
  const [quantity, setQuantity] = useState(prefill ? String(prefill.quantity) : '1');
  const [notes, setNotes] = useState(prefill?.notes ?? '');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const scope = procedure?.scope ?? null;
  const back = `/encounters/${encounter.id}?tab=procedures`;

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const values = {
      procedure_id: procedure?.id ?? '',
      tooth_fdi: tooth,
      surfaces,
      jaw,
      quantity,
      notes,
    };
    const result = procedureEntrySchema(scope).safeParse(values);
    if (!result.success) {
      setErrors(collect(result.error.issues));
      return;
    }
    setErrors({});
    setSaving(true);
    router.post(
      `/encounters/${encounter.id}/procedures`,
      {
        procedure_id: values.procedure_id,
        tooth_fdi: scope === 'tooth' || scope === 'surface' ? Number(tooth) : null,
        surfaces: scope === 'surface' ? surfaces : [],
        jaw: scope === 'jaw' ? jaw : null,
        quantity: result.data.quantity,
        notes,
        ...(prefill ? { plan_item_id: prefill.plan_item_id } : {}),
      },
      {
        onError: (serverErrors) => {
          setErrors(serverErrors);
        },
        onFinish: () => {
          setSaving(false);
        },
      },
    );
  }

  return (
    <EncounterFormShell title="Tambah tindakan" encounter={encounter} tab="procedures">
      <form onSubmit={submit} noValidate aria-label="Tambah tindakan" className="form-grid">
        {prefill ? (
          <p className="text-muted text-sm">
            Dari rencana perawatan: {prefill.plan_title}. Setelah disimpan, langkah ini berstatus
            Selesai.
          </p>
        ) : null}
        <ProcedurePicker
          field="procedure_id"
          selected={procedure}
          onSelect={setProcedure}
          error={errors.procedure_id}
        />
        {scope === 'tooth' || scope === 'surface' ? (
          <TextField
            field="tooth_fdi"
            label="Nomor gigi (FDI)"
            inputMode="numeric"
            value={tooth}
            onChange={(e) => {
              setTooth(e.target.value);
            }}
            error={errors.tooth_fdi}
          />
        ) : null}
        {scope === 'surface' ? (
          <fieldset className="form-grid">
            <legend className="text-sm font-semibold">Permukaan</legend>
            <div className="flex flex-wrap gap-3">
              {SURFACES.map((surface) => (
                <label key={surface.value} className="min-h-touch flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={surfaces.includes(surface.value)}
                    onChange={(event) => {
                      setSurfaces((current) =>
                        event.target.checked
                          ? [...current, surface.value]
                          : current.filter((s) => s !== surface.value),
                      );
                    }}
                  />
                  {surface.label}
                </label>
              ))}
            </div>
            {errors.surfaces ? (
              <p role="alert" className="text-danger-600 text-sm">
                {errors.surfaces}
              </p>
            ) : null}
          </fieldset>
        ) : null}
        {scope === 'jaw' ? (
          <Select
            field="jaw"
            label="Rahang"
            placeholder="Pilih rahang"
            options={jaws}
            value={jaw}
            onChange={(e) => {
              setJaw(e.target.value);
            }}
            error={errors.jaw}
          />
        ) : null}
        <TextField
          field="quantity"
          label="Jumlah"
          inputMode="numeric"
          value={quantity}
          onChange={(e) => {
            setQuantity(e.target.value);
          }}
          error={errors.quantity}
        />
        <TextField
          field="notes"
          label="Catatan"
          value={notes}
          onChange={(e) => {
            setNotes(e.target.value);
          }}
          error={errors.notes}
        />
        {errors.domain ? (
          <p role="alert" className="text-danger-600 text-sm">
            {errors.domain}
          </p>
        ) : null}
        <div className="flex flex-wrap gap-3">
          <Button type="submit" loading={saving}>
            Simpan tindakan
          </Button>
          <Link href={back} className="btn btn-secondary">
            Batal
          </Link>
        </div>
      </form>
    </EncounterFormShell>
  );
}
