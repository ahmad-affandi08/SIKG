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

interface Props {
  encounter: EncounterRef;
  jaws: SelectOption[];
}

export default function ProcedureForm({ encounter, jaws }: Props) {
  const [procedure, setProcedure] = useState<ProcedureOption | null>(null);
  const [tooth, setTooth] = useState('');
  const [surfaces, setSurfaces] = useState<string[]>([]);
  const [jaw, setJaw] = useState('');
  const [quantity, setQuantity] = useState('1');
  const [notes, setNotes] = useState('');
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
      <form
        onSubmit={submit}
        noValidate
        aria-label="Tambah tindakan"
        className="flex flex-col gap-5"
      >
        <ProcedurePicker selected={procedure} onSelect={setProcedure} error={errors.procedure_id} />
        {scope === 'tooth' || scope === 'surface' ? (
          <TextField
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
          <fieldset className="flex flex-col gap-1">
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
          label="Jumlah"
          inputMode="numeric"
          value={quantity}
          onChange={(e) => {
            setQuantity(e.target.value);
          }}
          error={errors.quantity}
        />
        <TextField
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
