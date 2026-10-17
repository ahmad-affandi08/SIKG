import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { nikIssue, satusehatSettingsSchema } from '@/features/integration/schemas';
import type { SatusehatSettingsValues } from '@/features/integration/schemas';
import { formatDateTime } from '@/lib/format';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface Props {
  settings: {
    enabled: boolean;
    environment: 'staging' | 'production';
    organization_id: string | null;
    location_id: string | null;
    location_name: string | null;
    client_id: string | null;
    has_secret: boolean;
    complete: boolean;
  };
  environments: SelectOption[];
  doctors: { id: string; name: string; has_nik: boolean; ihs_id: string | null }[];
  counts: Record<string, number>;
  submissions: {
    id: string;
    medical_record_no: string | null;
    status: string;
    status_label: string;
    attempts: number;
    last_error: string | null;
    created_at: string;
    sent_at: string | null;
    can_retry: boolean;
  }[];
}

const TONE: Record<string, 'success' | 'warning' | 'danger' | 'neutral'> = {
  sent: 'success',
  pending: 'neutral',
  blocked: 'warning',
  failed: 'danger',
  skipped: 'neutral',
};

function DoctorRow({ doctor }: { doctor: Props['doctors'][number] }) {
  const form = useForm({ nik: '' });
  const [error, setError] = useState<string | null>(null);

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const issue = nikIssue(form.data.nik);
    if (issue) {
      setError(issue);
      return;
    }
    setError(null);
    form.put(`/settings/satusehat/doctors/${doctor.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
      },
    });
  }

  return (
    <tr>
      <td className="py-1">{doctor.name}</td>
      <td>{doctor.has_nik ? 'NIK tersimpan' : 'Belum diisi'}</td>
      <td>{doctor.ihs_id ? 'Terhubung' : '-'}</td>
      <td>
        <form
          onSubmit={submit}
          noValidate
          aria-label={`NIK ${doctor.name}`}
          className="flex items-start gap-2"
        >
          <TextField
            label={`NIK ${doctor.name}`}
            inputMode="numeric"
            value={form.data.nik}
            onChange={(event) => {
              form.setData('nik', event.target.value);
            }}
            error={error ?? form.errors.nik}
          />
          <Button type="submit" variant="secondary" loading={form.processing}>
            Simpan
          </Button>
        </form>
      </td>
    </tr>
  );
}

/** P2-01: pengaturan SATUSEHAT, NIK dokter, dan pemantauan pengiriman kunjungan. */
export default function Index({ settings, environments, doctors, counts, submissions }: Props) {
  const timezone = useClinicTimezone();
  const form = useForm<SatusehatSettingsValues>({
    enabled: settings.enabled,
    environment: settings.environment,
    organization_id: settings.organization_id ?? '',
    location_id: settings.location_id ?? '',
    location_name: settings.location_name ?? '',
    client_id: settings.client_id ?? '',
    client_secret: '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = satusehatSettingsSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.put('/settings/satusehat', {
      preserveScroll: true,
      onSuccess: () => {
        form.setData('client_secret', '');
      },
    });
  }

  const error = (key: keyof SatusehatSettingsValues): string | undefined =>
    errors[key] ?? form.errors[key];
  const text = (key: keyof SatusehatSettingsValues, label: string, type = 'text') => (
    <TextField
      label={label}
      type={type}
      autoComplete="off"
      value={String(form.data[key])}
      onChange={(event) => {
        form.setData(key, event.target.value as never);
      }}
      error={error(key)}
    />
  );

  return (
    <AppLayout>
      <main className="flex flex-col gap-4 p-3">
        <PageHead title="SATUSEHAT" />
        <PageHeader
          title="Integrasi SATUSEHAT"
          description="Kunjungan yang ditutup dikirim ke platform SATUSEHAT Kemenkes sebagai data FHIR (Permenkes 24/2022). Uji di staging sebelum memakai produksi."
        />

        <form
          onSubmit={submit}
          noValidate
          aria-label="Pengaturan SATUSEHAT"
          className="form-grid max-w-xl"
        >
          <h2>Koneksi</h2>
          <label className="min-h-touch flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.data.enabled}
              onChange={(event) => {
                form.setData('enabled', event.target.checked);
              }}
            />
            <span>Kirim kunjungan yang ditutup ke SATUSEHAT</span>
          </label>
          <Select
            label="Lingkungan"
            options={environments}
            value={form.data.environment}
            onChange={(event) => {
              form.setData(
                'environment',
                event.target.value as SatusehatSettingsValues['environment'],
              );
            }}
            error={error('environment')}
          />
          {text('organization_id', 'ID organisasi (Organization ID)')}
          {text('location_id', 'ID lokasi (Location ID)')}
          {text('location_name', 'Nama lokasi')}
          {text('client_id', 'Client ID')}
          {text(
            'client_secret',
            settings.has_secret ? 'Client secret (kosongkan bila tidak diganti)' : 'Client secret',
            'password',
          )}
          <div className="flex flex-wrap gap-3">
            <Button type="submit" loading={form.processing}>
              Simpan pengaturan
            </Button>
            <Button
              variant="secondary"
              disabled={!settings.complete}
              onClick={() => {
                router.post('/settings/satusehat/test', {}, { preserveScroll: true });
              }}
            >
              Uji koneksi
            </Button>
          </div>
        </form>

        <section aria-label="NIK dokter" className="flex flex-col gap-2">
          <h2>NIK dokter</h2>
          <p className="text-muted text-sm">
            SATUSEHAT mencocokkan dokter lewat NIK. NIK disimpan terenkripsi dan tidak ditampilkan
            kembali.
          </p>
          <table className="w-full text-left text-sm">
            <thead>
              <tr>
                <th scope="col">Dokter</th>
                <th scope="col">NIK</th>
                <th scope="col">ID SATUSEHAT</th>
                <th scope="col">Ubah NIK</th>
              </tr>
            </thead>
            <tbody>
              {doctors.map((doctor) => (
                <DoctorRow key={doctor.id} doctor={doctor} />
              ))}
            </tbody>
          </table>
        </section>

        <section aria-label="Pengiriman" className="flex flex-col gap-2">
          <h2>Pengiriman kunjungan</h2>
          <p className="text-sm">
            Terkirim {counts.sent ?? 0}, menunggu {counts.pending ?? 0}, perlu tindakan{' '}
            {counts.blocked ?? 0}, gagal {counts.failed ?? 0}, dilewati {counts.skipped ?? 0}.
          </p>
          {submissions.length === 0 ? (
            <Alert tone="info">Belum ada kunjungan yang masuk antrean.</Alert>
          ) : (
            <table className="w-full text-left text-sm">
              <thead>
                <tr>
                  <th scope="col">Nomor RM</th>
                  <th scope="col">Masuk antrean</th>
                  <th scope="col">Status</th>
                  <th scope="col">Keterangan</th>
                  <th scope="col">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {submissions.map((s) => (
                  <tr key={s.id}>
                    <td>{s.medical_record_no ?? '-'}</td>
                    <td>{formatDateTime(s.created_at, timezone)}</td>
                    <td>
                      <Badge tone={TONE[s.status] ?? 'neutral'}>{s.status_label}</Badge>
                    </td>
                    <td>
                      {s.last_error ??
                        (s.sent_at ? `Terkirim ${formatDateTime(s.sent_at, timezone)}` : '-')}
                    </td>
                    <td>
                      {s.can_retry ? (
                        <Button
                          variant="secondary"
                          onClick={() => {
                            router.post(
                              `/settings/satusehat/submissions/${s.id}/retry`,
                              {},
                              { preserveScroll: true },
                            );
                          }}
                        >
                          Kirim ulang
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>
      </main>
    </AppLayout>
  );
}
