import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { EncounterFormShell } from '@/features/clinical/components/EncounterFormShell';
import type { EncounterRef } from '@/features/clinical/components/EncounterFormShell';
import { collect } from '@/features/clinical/components/EncounterPanels';
import { addendumSchema } from '@/features/clinical/schemas';

export default function AddendumForm({ encounter }: { encounter: EncounterRef }) {
  const [values, setValues] = useState({ body: '', reason: '' });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const back = `/encounters/${encounter.id}?tab=addenda`;

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = addendumSchema.safeParse(values);
    if (!result.success) {
      setErrors(collect(result.error.issues));
      return;
    }
    setErrors({});
    setSaving(true);
    router.post(`/encounters/${encounter.id}/addenda`, values, {
      onError: (serverErrors) => {
        setErrors(serverErrors);
      },
      onFinish: () => {
        setSaving(false);
      },
    });
  }

  return (
    <EncounterFormShell title="Tambah addendum" encounter={encounter} tab="addenda">
      <form
        onSubmit={submit}
        noValidate
        aria-label="Tambah addendum"
        className="flex flex-col gap-5"
      >
        <p className="text-muted text-sm">
          Kunjungan sudah ditutup. Koreksi dicatat sebagai addendum bertanda waktu dan nama penulis;
          catatan asli tidak berubah.
        </p>
        <div className="flex flex-col gap-1">
          <label htmlFor="addendum-body" className="text-sm font-semibold">
            Isi addendum
          </label>
          <textarea
            id="addendum-body"
            rows={5}
            value={values.body}
            onChange={(event) => {
              setValues((v) => ({ ...v, body: event.target.value }));
            }}
            aria-invalid={errors.body ? true : undefined}
            className="field py-2"
          />
          {errors.body ? (
            <p role="alert" className="text-danger-600 text-sm">
              {errors.body}
            </p>
          ) : null}
        </div>
        <TextField
          label="Alasan addendum"
          value={values.reason}
          onChange={(e) => {
            setValues((v) => ({ ...v, reason: e.target.value }));
          }}
          error={errors.reason}
        />
        {errors.domain ? (
          <p role="alert" className="text-danger-600 text-sm">
            {errors.domain}
          </p>
        ) : null}
        <div className="flex flex-wrap gap-3">
          <Button type="submit" loading={saving}>
            Simpan addendum
          </Button>
          <Link href={back} className="btn btn-secondary">
            Batal
          </Link>
        </div>
      </form>
    </EncounterFormShell>
  );
}
