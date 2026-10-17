import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { DiagnosisPicker } from '@/features/clinical/components/DiagnosisPicker';
import { EncounterFormShell } from '@/features/clinical/components/EncounterFormShell';
import type { EncounterRef } from '@/features/clinical/components/EncounterFormShell';
import { collect } from '@/features/clinical/components/EncounterPanels';
import { diagnosisEntrySchema } from '@/features/clinical/schemas';

export default function DiagnosisForm({ encounter }: { encounter: EncounterRef }) {
  const [picked, setPicked] = useState<{ id: string; code: string; name: string } | null>(null);
  const [tooth, setTooth] = useState('');
  const [notes, setNotes] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const back = `/encounters/${encounter.id}?tab=diagnosis`;

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const values = { diagnosis_id: picked?.id ?? '', tooth_fdi: tooth, notes };
    const result = diagnosisEntrySchema.safeParse(values);
    if (!result.success) {
      setErrors(collect(result.error.issues));
      return;
    }
    setErrors({});
    setSaving(true);
    router.post(
      `/encounters/${encounter.id}/diagnoses`,
      { diagnosis_id: values.diagnosis_id, tooth_fdi: tooth === '' ? null : Number(tooth), notes },
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
    <EncounterFormShell title="Tambah diagnosa" encounter={encounter} tab="diagnosis">
      <form onSubmit={submit} noValidate aria-label="Tambah diagnosa" className="form-grid">
        {picked ? (
          <div className="flex items-center gap-3">
            <span className="text-sm">
              <span className="font-semibold">{picked.code}</span> {picked.name}
            </span>
            <button
              type="button"
              className="btn btn-secondary"
              onClick={() => {
                setPicked(null);
              }}
            >
              Ganti
            </button>
          </div>
        ) : (
          <DiagnosisPicker field="diagnosis_id" onSelect={setPicked} />
        )}
        {errors.diagnosis_id ? (
          <p role="alert" className="text-danger-600 text-sm">
            {errors.diagnosis_id}
          </p>
        ) : null}
        <TextField
          field="tooth_fdi"
          label="Nomor gigi (opsional, FDI)"
          inputMode="numeric"
          value={tooth}
          onChange={(e) => {
            setTooth(e.target.value);
          }}
          error={errors.tooth_fdi}
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
            Simpan diagnosa
          </Button>
          <Link href={back} className="btn btn-secondary">
            Batal
          </Link>
        </div>
      </form>
    </EncounterFormShell>
  );
}
