import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { FieldLabel } from '@/components/ui/FieldLabel';
import { Select } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { EncounterFormShell } from '@/features/clinical/components/EncounterFormShell';
import type { EncounterRef } from '@/features/clinical/components/EncounterFormShell';
import { collect } from '@/features/clinical/components/EncounterPanels';
import { FILE_KINDS, fileIssue, fileMetaSchema } from '@/features/clinical/files';
import { isValidFdi } from '@/features/clinical/tooth';
import { usePageRequired } from '@/lib/useRequired';

export default function FileForm({ encounter }: { encounter: EncounterRef }) {
  const [file, setFile] = useState<File | null>(null);
  const [kind, setKind] = useState('xray');
  const [tooth, setTooth] = useState('');
  const [caption, setCaption] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const fileRequired = usePageRequired('file');
  const back = `/encounters/${encounter.id}?tab=files`;

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const issue = fileIssue(file);
    const meta = fileMetaSchema.safeParse({ kind, tooth_fdi: tooth, caption });
    const next: Record<string, string> = meta.success ? {} : collect(meta.error.issues);
    if (issue) {
      next.file = issue;
    }
    if (tooth !== '' && !isValidFdi(Number(tooth))) {
      next.tooth_fdi = 'Nomor gigi tidak valid menurut FDI.';
    }
    if (Object.keys(next).length > 0) {
      setErrors(next);
      return;
    }
    setErrors({});
    setSaving(true);
    router.post(
      `/encounters/${encounter.id}/files`,
      { file, kind, tooth_fdi: tooth === '' ? null : Number(tooth), caption },
      {
        forceFormData: true,
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
    <EncounterFormShell title="Unggah berkas" encounter={encounter} tab="files">
      <form onSubmit={submit} noValidate aria-label="Unggah berkas" className="form-grid">
        <div className="flex flex-col gap-1">
          <FieldLabel htmlFor="clinical-file" required={fileRequired}>
            Berkas (JPG, PNG, atau PDF, maksimal 10 MB)
          </FieldLabel>
          <input
            id="clinical-file"
            aria-required={fileRequired ? true : undefined}
            type="file"
            accept="image/jpeg,image/png,application/pdf"
            className="min-h-touch py-2 text-sm"
            onChange={(event) => {
              setFile(event.target.files?.[0] ?? null);
            }}
          />
          {errors.file ? (
            <p role="alert" className="text-danger-600 text-sm">
              {errors.file}
            </p>
          ) : null}
        </div>
        <Select
          field="kind"
          label="Jenis"
          options={FILE_KINDS.map((k) => ({ value: k.value, label: k.label }))}
          value={kind}
          onChange={(event) => {
            setKind(event.target.value);
          }}
          error={errors.kind}
        />
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
          field="caption"
          label="Keterangan"
          value={caption}
          onChange={(e) => {
            setCaption(e.target.value);
          }}
          error={errors.caption}
        />
        {errors.domain ? (
          <p role="alert" className="text-danger-600 text-sm">
            {errors.domain}
          </p>
        ) : null}
        <div className="flex flex-wrap gap-3">
          <Button type="submit" loading={saving}>
            Unggah berkas
          </Button>
          <Link href={back} className="btn btn-secondary">
            Batal
          </Link>
        </div>
      </form>
    </EncounterFormShell>
  );
}
