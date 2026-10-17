import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { PatientPicker } from '@/features/patient/components/PatientPicker';
import { mergeFormSchema, summarizeCounts } from '@/features/patient/merge';
import type { MergeCount, MergeFormValues } from '@/features/patient/merge';
import { formatDate } from '@/lib/format';

interface PatientCard {
  id: string;
  medical_record_no: string;
  name: string;
  nik: string | null;
  birth_date: string;
  sex: string;
  address: string | null;
  phone: string | null;
}

interface Props {
  target: PatientCard;
  source: PatientCard | null;
  counts: MergeCount[];
  warnings: string[];
  blocker: string | null;
}

function Card({ title, patient }: { title: string; patient: PatientCard }) {
  return (
    <section className="pane" aria-label={title}>
      <h2 className="pane-head">{title}</h2>
      <dl className="grid grid-cols-[8rem_1fr] gap-x-3 gap-y-1 p-2 text-sm">
        <dt className="text-muted">Nomor RM</dt>
        <dd>{patient.medical_record_no}</dd>
        <dt className="text-muted">Nama</dt>
        <dd>{patient.name}</dd>
        <dt className="text-muted">NIK</dt>
        <dd>{patient.nik ?? '-'}</dd>
        <dt className="text-muted">Tanggal lahir</dt>
        <dd>{formatDate(patient.birth_date)}</dd>
        <dt className="text-muted">Jenis kelamin</dt>
        <dd>{patient.sex}</dd>
        <dt className="text-muted">Nomor HP</dt>
        <dd>{patient.phone ?? '-'}</dd>
        <dt className="text-muted">Alamat</dt>
        <dd>{patient.address ?? '-'}</dd>
      </dl>
    </section>
  );
}

/** PAS-07: pilih pasien ganda, tinjau perbandingan dan data yang akan berpindah, lalu gabungkan ke pasien utama. */
export default function Merge({ target, source, counts, warnings, blocker }: Props) {
  const form = useForm<MergeFormValues>({ reason: '', confirm: false });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const back = `/patients/${target.id}`;

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = mergeFormSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    if (source) {
      form.transform((data) => ({ ...data, source_id: source.id }));
      form.post(`/patients/${target.id}/merge`);
    }
  }

  const err = (key: keyof MergeFormValues) => errors[key] ?? form.errors[key];

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Gabung data pasien ganda" />
        <PageHeader
          title="Gabung data pasien ganda"
          back={{ href: back, label: 'rekam pasien' }}
          description={`Pasien utama: ${target.name} (${target.medical_record_no})`}
        />
        <p className="text-muted text-sm">
          Semua janji, kunjungan, odontogram, berkas, resep, tagihan, riwayat medis, dan persetujuan
          milik pasien ganda dipindahkan ke pasien utama. Pasien ganda diarsipkan (tidak dihapus)
          dan penggabungan tercatat di audit log.
        </p>

        <PatientPicker
          selected={source ? { id: source.id, name: source.name } : null}
          onSelect={(picked) => {
            if (picked === null) {
              router.get(`/patients/${target.id}/merge`);
            } else if (picked.id !== target.id) {
              router.get(`/patients/${target.id}/merge`, { source: picked.id });
            }
          }}
        />

        {source ? (
          <>
            <div className="grid gap-2 lg:grid-cols-2">
              <Card title="Pasien utama (dipertahankan)" patient={target} />
              <Card title="Pasien ganda (diarsipkan)" patient={source} />
            </div>
            <p className="text-sm">
              <strong>Data yang dipindahkan:</strong> {summarizeCounts(counts)}.
            </p>
            {warnings.map((warning) => (
              <Alert key={warning} tone="warning">
                {warning} Pastikan keduanya memang orang yang sama.
              </Alert>
            ))}
            {blocker ? (
              <Alert tone="error">{blocker}</Alert>
            ) : (
              <form
                onSubmit={submit}
                noValidate
                aria-label="Gabung data pasien"
                className="form-grid"
              >
                <TextField
                  field="reason"
                  label="Alasan penggabungan"
                  value={form.data.reason}
                  error={err('reason')}
                  onChange={(event) => {
                    form.setData('reason', event.target.value);
                  }}
                />
                <label className="min-h-touch flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={form.data.confirm}
                    onChange={(event) => {
                      form.setData('confirm', event.target.checked);
                    }}
                  />
                  <span>
                    Saya yakin kedua data ini orang yang sama. Penggabungan tidak dapat dibatalkan
                    lewat aplikasi.
                  </span>
                </label>
                {err('confirm') ? (
                  <p role="alert" className="text-signal-700 text-sm">
                    {err('confirm')}
                  </p>
                ) : null}
                <div className="flex gap-2">
                  <Button type="submit" variant="danger" loading={form.processing}>
                    Gabungkan pasien
                  </Button>
                </div>
              </form>
            )}
          </>
        ) : (
          <p className="text-muted text-sm">
            Cari dan pilih pasien ganda yang akan digabung ke pasien ini.
          </p>
        )}
      </main>
    </AppLayout>
  );
}
