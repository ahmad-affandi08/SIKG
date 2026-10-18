import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { Select } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { dicomUploadSchema, formatBytes, importSchema } from '@/features/imaging/schemas';

interface Props {
  files: { name: string; size: number; modified: string }[];
  encounters: { value: string; label: string }[];
}

/** P3-03: gambar rontgen yang masuk dari sensor atau pesawat rontgen, dikaitkan ke kunjungan yang sedang berjalan. */
export default function Inbox({ files, encounters }: Props) {
  return (
    <AppLayout>
      <main className="flex flex-col gap-4 p-3">
        <PageHead title="Rontgen masuk" />
        <PageHeader
          title="Rontgen digital masuk"
          description="Gambar dari sensor atau pesawat rontgen (DICOM, JPG, PNG) menunggu dikaitkan ke kunjungan."
        />
        {encounters.length === 0 ? (
          <Alert tone="warning">
            Tidak ada kunjungan yang sedang berjalan sebagai tujuan gambar.
          </Alert>
        ) : null}

        <section aria-label="Berkas masuk" className="flex flex-col gap-2">
          <h2>Menunggu ({String(files.length)})</h2>
          {files.length === 0 ? (
            <p className="text-muted text-sm">
              Tidak ada berkas. Atur perangkat lunak sensor untuk menyimpan ke folder masuk klinik
              di server.
            </p>
          ) : null}
          {files.map((file) => (
            <InboxRow key={file.name} file={file} encounters={encounters} />
          ))}
        </section>

        <DicomUpload encounters={encounters} />
      </main>
    </AppLayout>
  );
}

function InboxRow({
  file,
  encounters,
}: {
  file: Props['files'][number];
  encounters: Props['encounters'];
}) {
  const [encounterId, setEncounterId] = useState('');
  const [tooth, setTooth] = useState('');
  const [mismatch, setMismatch] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  function attach() {
    const result = importSchema.safeParse({
      name: file.name,
      encounter_id: encounterId,
      tooth_fdi: tooth,
    });
    if (!result.success) {
      setError(result.error.issues[0]?.message ?? 'Isian tidak valid.');
      return;
    }
    setError(null);
    router.post(
      '/imaging/inbox/import',
      {
        name: file.name,
        encounter_id: encounterId,
        tooth_fdi: tooth === '' ? null : tooth,
        confirm_mismatch: mismatch,
      },
      {
        preserveScroll: true,
        onStart: () => {
          setBusy(true);
        },
        onFinish: () => {
          setBusy(false);
        },
      },
    );
  }

  return (
    <article className="card flex flex-col gap-2 p-3" aria-label={`Berkas ${file.name}`}>
      <div className="flex flex-wrap items-baseline gap-x-4">
        <strong>{file.name}</strong>
        <span className="text-muted text-sm">
          {formatBytes(file.size)} - masuk {file.modified}
        </span>
      </div>
      {error ? <Alert tone="error">{error}</Alert> : null}
      <div className="flex flex-wrap items-end gap-2">
        <Select
          label="Kunjungan tujuan"
          required
          placeholder="Pilih kunjungan"
          value={encounterId}
          options={encounters}
          onChange={(event) => {
            setEncounterId(event.target.value);
          }}
        />
        <TextField
          label="Gigi (FDI, opsional)"
          required={false}
          inputMode="numeric"
          value={tooth}
          onChange={(event) => {
            setTooth(event.target.value);
          }}
        />
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={mismatch}
            onChange={(event) => {
              setMismatch(event.target.checked);
            }}
          />
          Tegaskan gambar ini milik pasien tersebut walau ID di dalamnya berbeda
        </label>
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <Button loading={busy} onClick={attach}>
          Lampirkan ke kunjungan
        </Button>
        <ConfirmAction
          label="Buang"
          message="Berkas dipindah ke arsip ditolak dan tidak dilampirkan ke kunjungan mana pun. Lanjutkan?"
          confirmLabel="Ya, buang"
          onConfirm={() => {
            router.post('/imaging/inbox/discard', { name: file.name }, { preserveScroll: true });
          }}
        />
      </div>
    </article>
  );
}

function DicomUpload({ encounters }: { encounters: Props['encounters'] }) {
  const form = useForm<{
    encounter_id: string;
    tooth_fdi: string;
    caption: string;
    confirm_mismatch: boolean;
    file: File | null;
  }>({ encounter_id: '', tooth_fdi: '', caption: '', confirm_mismatch: false, file: null });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const err = (key: string) =>
    errors[key] ?? (form.errors as Record<string, string | undefined>)[key];

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = dicomUploadSchema.safeParse(form.data);
    const next: Record<string, string> = {};
    if (!result.success) {
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
    }
    if (form.data.file === null) {
      next.file = 'Pilih berkas DICOM.';
    }
    setErrors(next);
    if (Object.keys(next).length > 0) {
      return;
    }
    form.post('/imaging/dicom', { forceFormData: true, preserveScroll: true });
  }

  return (
    <section aria-label="Unggah DICOM" className="flex max-w-xl flex-col gap-2">
      <h2>Unggah berkas DICOM</h2>
      <p className="text-muted text-sm">
        Untuk gambar yang diekspor dari perangkat lunak rontgen. DICOM tanpa kompresi diubah menjadi
        gambar PNG untuk ditampilkan; berkas aslinya tetap disimpan dan dapat diunduh.
      </p>
      <form onSubmit={submit} noValidate aria-label="Unggah DICOM" className="form-grid">
        <Select
          label="Kunjungan tujuan"
          required
          placeholder="Pilih kunjungan"
          value={form.data.encounter_id}
          options={encounters}
          error={err('encounter_id')}
          onChange={(event) => {
            form.setData('encounter_id', event.target.value);
          }}
        />
        <div className="flex flex-col gap-1">
          <label htmlFor="dicom-file" className="text-sm font-semibold">
            Berkas DICOM
            <span aria-hidden="true" className="text-signal-700 ml-0.5">
              *
            </span>
          </label>
          <input
            id="dicom-file"
            type="file"
            accept=".dcm,.dicom,application/dicom"
            onChange={(event) => {
              form.setData('file', event.target.files?.[0] ?? null);
            }}
          />
          {err('file') ? (
            <p role="alert" className="text-danger-600 text-sm">
              {err('file')}
            </p>
          ) : null}
        </div>
        <TextField
          label="Gigi (FDI, opsional)"
          required={false}
          inputMode="numeric"
          value={form.data.tooth_fdi}
          error={err('tooth_fdi')}
          onChange={(event) => {
            form.setData('tooth_fdi', event.target.value);
          }}
        />
        <TextField
          label="Keterangan (opsional)"
          required={false}
          value={form.data.caption}
          error={err('caption')}
          onChange={(event) => {
            form.setData('caption', event.target.value);
          }}
        />
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={form.data.confirm_mismatch}
            onChange={(event) => {
              form.setData('confirm_mismatch', event.target.checked);
            }}
          />
          Tegaskan gambar ini milik pasien tersebut walau ID di dalamnya berbeda
        </label>
        <div>
          <Button type="submit" loading={form.processing}>
            Impor DICOM
          </Button>
        </div>
      </form>
    </section>
  );
}
