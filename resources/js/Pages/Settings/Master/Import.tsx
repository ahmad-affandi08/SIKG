import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { importFileIssue } from '@/features/master/schema';
import type { MasterFieldDef } from '@/features/master/schema';

interface Preview {
  token: string;
  total_rows: number;
  valid_rows: number;
  existing_rows: number[];
  existing_count: number;
  error_rows: number;
  errors: { row: number; messages: string[] }[];
}

interface Props {
  entity: {
    key: string;
    title: string;
    import_key: string;
    warning: string | null;
    home: string;
    fields: MasterFieldDef[];
  };
  preview: Preview | null;
}

/** Impor master data dari Excel dengan pratinjau (MST-09). Tidak ada yang disimpan sebelum dikonfirmasi. */
export default function Import({ entity, preview }: Props) {
  const base = `/settings/master/${entity.key}`;
  const upload = useForm<{ file: File | null }>({ file: null });
  const confirm = useForm({});
  const [fileError, setFileError] = useState<string | null>(null);
  const [confirming, setConfirming] = useState(false);
  const keyLabel =
    entity.fields.find((field) => field.key === entity.import_key)?.label ?? entity.import_key;

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const issue = importFileIssue(upload.data.file);
    setFileError(issue);
    if (issue) {
      return;
    }
    upload.post(`${base}/import`, { forceFormData: true });
  }

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title={`Impor ${entity.title}`} />
        <PageHeader
          title={`Impor ${entity.title.toLowerCase()} dari Excel`}
          back={{
            href: entity.home,
            label: entity.home === '/stock' ? 'stok' : entity.title.toLowerCase(),
          }}
        />

        {preview === null ? (
          <section className="flex flex-col gap-4">
            {entity.warning !== null ? <Alert tone="warning">{entity.warning}</Alert> : null}
            <ol className="list-decimal pl-5 text-sm">
              <li>Unduh templat, lalu isi lembar &quot;Data&quot; mulai baris 2.</li>
              <li>
                Unggah berkas. Sistem menampilkan pratinjau; belum ada data yang disimpan sebelum
                Anda mengonfirmasi.
              </li>
              {entity.warning === null ? (
                <li>
                  Baris yang {keyLabel.toLowerCase()}-nya sudah ada dilewati, tidak ditimpa. Baris
                  yang salah tidak ikut, dan dijelaskan per baris.
                </li>
              ) : (
                <li>Baris yang salah tidak ikut, dan dijelaskan per baris.</li>
              )}
            </ol>
            <div>
              <a href={`${base}/import/template`} className="btn btn-secondary">
                Unduh templat Excel
              </a>
            </div>
            <form onSubmit={submit} noValidate className="flex flex-col gap-3">
              <div className="flex flex-col gap-1">
                <label htmlFor="master-import-file" className="text-sm font-medium">
                  Berkas Excel (.xlsx) atau CSV, maksimal 2 MB dan 1.000 baris
                </label>
                <input
                  id="master-import-file"
                  type="file"
                  accept=".xlsx,.csv"
                  onChange={(event) => {
                    upload.setData('file', event.target.files?.[0] ?? null);
                  }}
                />
              </div>
              {fileError ? <Alert tone="error">{fileError}</Alert> : null}
              {upload.errors.file ? <Alert tone="error">{upload.errors.file}</Alert> : null}
              <div>
                <Button type="submit" loading={upload.processing}>
                  Unggah dan pratinjau
                </Button>
              </div>
            </form>
          </section>
        ) : (
          <section className="flex flex-col gap-4" aria-label="Pratinjau impor">
            <dl className="grid max-w-xl grid-cols-2 gap-2 text-sm sm:grid-cols-4">
              <div className="pane p-2">
                <dt className="text-muted">Baris terbaca</dt>
                <dd className="text-lg font-semibold">{preview.total_rows}</dd>
              </div>
              <div className="pane p-2">
                <dt className="text-muted">Siap diimpor</dt>
                <dd className="text-success-600 text-lg font-semibold">{preview.valid_rows}</dd>
              </div>
              <div className="pane p-2">
                <dt className="text-muted">Sudah ada</dt>
                <dd className="text-lg font-semibold">{preview.existing_count}</dd>
              </div>
              <div className="pane p-2">
                <dt className="text-muted">Galat</dt>
                <dd className="text-signal-700 text-lg font-semibold">{preview.error_rows}</dd>
              </div>
            </dl>

            {preview.existing_count > 0 ? (
              <Alert tone="info">
                {`${String(preview.existing_count)} baris dilewati karena ${keyLabel.toLowerCase()}-nya sudah ada${
                  preview.existing_rows.length > 0
                    ? ` (baris ${preview.existing_rows.join(', ')}${
                        preview.existing_count > preview.existing_rows.length ? ', dan lainnya' : ''
                      })`
                    : ''
                }.`}
              </Alert>
            ) : null}

            {preview.errors.length > 0 ? (
              <div>
                <h2 className="text-base font-semibold">Baris yang perlu diperbaiki</h2>
                <table className="w-full text-left text-sm">
                  <caption className="sr-only">Galat per baris</caption>
                  <thead>
                    <tr>
                      <th scope="col" className="w-20 py-1">
                        Baris
                      </th>
                      <th scope="col">Masalah</th>
                    </tr>
                  </thead>
                  <tbody>
                    {preview.errors.map((item) => (
                      <tr key={item.row}>
                        <td className="py-1 align-top">{item.row}</td>
                        <td>{item.messages.join(' ')}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
                {preview.error_rows > preview.errors.length ? (
                  <p className="text-muted text-sm">
                    Menampilkan {preview.errors.length} galat pertama dari {preview.error_rows}.
                  </p>
                ) : null}
              </div>
            ) : null}

            <div className="flex flex-wrap gap-2">
              <Button
                disabled={preview.valid_rows === 0}
                loading={confirm.processing}
                onClick={() => {
                  setConfirming(true);
                }}
              >
                {`Impor ${String(preview.valid_rows)} baris`}
              </Button>
              <Link href={`${base}/import`} className="btn btn-secondary">
                Unggah berkas lain
              </Link>
            </div>
            {preview.valid_rows === 0 ? (
              <Alert tone="warning">
                Tidak ada baris yang dapat diimpor. Perbaiki berkas lalu unggah ulang.
              </Alert>
            ) : null}

            <ConfirmDialog
              open={confirming}
              title="Impor data?"
              message={`${String(preview.valid_rows)} baris akan ditambahkan ke ${entity.title.toLowerCase()}. ${
                entity.warning ?? 'Data yang sudah ada tidak diubah.'
              } Setiap baris tercatat di audit.`}
              confirmLabel="Ya, impor"
              onCancel={() => {
                setConfirming(false);
              }}
              onConfirm={() => {
                setConfirming(false);
                confirm.post(`${base}/import/${preview.token}/commit`);
              }}
            />
          </section>
        )}
      </main>
    </AppLayout>
  );
}
