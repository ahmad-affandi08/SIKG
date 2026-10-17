import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { importFileIssue } from '@/features/patient/importFile';

interface Batch {
  id: string;
  status: 'previewed' | 'importing' | 'completed' | 'failed';
  status_label: string;
  total_rows: number;
  valid_rows: number;
  error_rows: number;
  duplicate_rows: number;
  imported_rows: number;
  skipped_rows: number;
  include_duplicates: boolean;
  errors: { row: number; message: string }[];
  duplicate_row_numbers: number[];
  failure_reason: string | null;
}

interface Props {
  batch: Batch | null;
}

const POLL_MS = 4000;

export default function Import({ batch }: Props) {
  const form = useForm<{ file: File | null }>({ file: null });
  const [fileError, setFileError] = useState<string | null>(null);
  const [includeDuplicates, setIncludeDuplicates] = useState(false);
  const importing = batch?.status === 'importing';

  // Kemajuan impor diperbarui lewat polling (tanpa WebSocket); berhenti saat tab tidak aktif.
  useEffect(() => {
    if (!importing) {
      return undefined;
    }
    const timer = setInterval(() => {
      if (document.visibilityState === 'visible') {
        router.reload({ only: ['batch'] });
      }
    }, POLL_MS);
    return () => {
      clearInterval(timer);
    };
  }, [importing]);

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const issue = importFileIssue(form.data.file);
    setFileError(issue);
    if (issue) {
      return;
    }
    form.post('/patients/import', { forceFormData: true });
  }

  return (
    <AppLayout>
      <main className="mx-auto flex max-w-3xl flex-col gap-6 p-6">
        <Head title="Impor pasien" />
        <h1 className="text-2xl font-semibold">Impor pasien dari Excel</h1>

        {batch === null ? (
          <section className="flex flex-col gap-4">
            <ol className="list-decimal pl-5 text-sm">
              <li>
                Unduh templat, isi data pasien (nama, tanggal lahir, jenis kelamin, NIK, HP, alamat,
                alergi, penyakit sistemik).
              </li>
              <li>Unggah berkas. Sistem menampilkan pratinjau sebelum ada data yang disimpan.</li>
              <li>Periksa pratinjau, lalu konfirmasi impor.</li>
            </ol>
            <div>
              <a href="/patients/import/template" className="btn btn-secondary">
                Unduh templat Excel
              </a>
            </div>
            <form onSubmit={submit} noValidate className="flex flex-col gap-3">
              <div className="flex flex-col gap-1">
                <label htmlFor="import-file" className="text-sm font-medium">
                  Berkas Excel (.xlsx, maksimal 5 MB)
                </label>
                <input
                  id="import-file"
                  type="file"
                  accept=".xlsx"
                  onChange={(event) => {
                    form.setData('file', event.target.files?.[0] ?? null);
                  }}
                />
              </div>
              {fileError ? <Alert tone="error">{fileError}</Alert> : null}
              {form.errors.file ? <Alert tone="error">{form.errors.file}</Alert> : null}
              <div>
                <Button type="submit" loading={form.processing}>
                  Unggah dan pratinjau
                </Button>
              </div>
            </form>
          </section>
        ) : (
          <section className="flex flex-col gap-4" aria-label="Hasil impor">
            <div className="flex items-center gap-2">
              <span className="text-sm">Status:</span>
              <Badge
                tone={
                  batch.status === 'completed'
                    ? 'success'
                    : batch.status === 'failed'
                      ? 'danger'
                      : 'warning'
                }
              >
                {batch.status_label}
              </Badge>
            </div>

            <dl className="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
              <div>
                <dt className="text-slate-600">Total baris</dt>
                <dd className="text-lg font-semibold">{batch.total_rows}</dd>
              </div>
              <div>
                <dt className="text-slate-600">Valid</dt>
                <dd className="text-lg font-semibold">{batch.valid_rows}</dd>
              </div>
              <div>
                <dt className="text-slate-600">Galat</dt>
                <dd className="text-lg font-semibold">{batch.error_rows}</dd>
              </div>
              <div>
                <dt className="text-slate-600">Dugaan duplikat</dt>
                <dd className="text-lg font-semibold">{batch.duplicate_rows}</dd>
              </div>
            </dl>

            {batch.status === 'importing' ? (
              <Alert tone="info">
                Impor berjalan di latar belakang: {batch.imported_rows} pasien masuk,{' '}
                {batch.skipped_rows} baris dilewati. Halaman memperbarui diri.
              </Alert>
            ) : null}
            {batch.status === 'completed' ? (
              <Alert tone="success">
                Impor selesai: {batch.imported_rows} pasien masuk, {batch.skipped_rows} baris
                dilewati. Persetujuan data pasien hasil impor perlu dicatat dari halaman pasien.
              </Alert>
            ) : null}
            {batch.status === 'failed' ? (
              <Alert tone="error">{batch.failure_reason ?? 'Impor gagal.'}</Alert>
            ) : null}

            {batch.errors.length > 0 ? (
              <div>
                <h2 className="text-lg font-semibold">Baris galat (tidak diimpor)</h2>
                <table className="w-full text-left text-sm">
                  <caption className="sr-only">Daftar baris galat</caption>
                  <thead>
                    <tr>
                      <th scope="col" className="py-1">
                        Baris
                      </th>
                      <th scope="col">Masalah</th>
                    </tr>
                  </thead>
                  <tbody>
                    {batch.errors.map((error) => (
                      <tr key={error.row} className="border-t border-slate-200">
                        <td className="py-1 pr-3">{error.row}</td>
                        <td>{error.message}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : null}

            {batch.duplicate_row_numbers.length > 0 ? (
              <p className="text-sm">
                Dugaan duplikat pada baris: {batch.duplicate_row_numbers.join(', ')}
                {batch.duplicate_rows > batch.duplicate_row_numbers.length ? ', ...' : ''}
              </p>
            ) : null}

            {batch.status === 'previewed' ? (
              <div className="flex flex-col gap-3">
                <label className="min-h-touch flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={includeDuplicates}
                    onChange={(event) => {
                      setIncludeDuplicates(event.target.checked);
                    }}
                  />
                  Ikut impor dugaan duplikat (ditandai untuk digabung kemudian)
                </label>
                <div>
                  <ConfirmAction
                    label="Impor sekarang"
                    message={`${String(batch.valid_rows)} pasien valid akan dibuat${includeDuplicates ? ` bersama ${String(batch.duplicate_rows)} dugaan duplikat` : ''}. Baris galat dilewati.`}
                    confirmLabel="Ya, impor"
                    variant="primary"
                    onConfirm={() => {
                      router.post(`/patients/import/${batch.id}/commit`, {
                        include_duplicates: includeDuplicates,
                      });
                    }}
                  />
                </div>
              </div>
            ) : null}

            <div>
              <Link href="/patients/import" className="btn btn-secondary">
                Impor berkas lain
              </Link>
            </div>
          </section>
        )}
      </main>
    </AppLayout>
  );
}
