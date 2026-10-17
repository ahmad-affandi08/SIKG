import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { ChangeEvent, SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { TextField } from '@/components/ui/TextField';
import { clinicProfileSchema, logoFileIssue } from '@/features/clinic/schemas';
import type { ClinicProfileValues } from '@/features/clinic/schemas';

interface Props {
  profile: {
    name: string;
    address: string | null;
    license_no: string | null;
    receipt_header: string | null;
    prescription_header: string | null;
    has_logo: boolean;
  };
}

export default function ClinicProfile({ profile }: Props) {
  const form = useForm<ClinicProfileValues>({
    name: profile.name,
    address: profile.address ?? '',
    license_no: profile.license_no ?? '',
    receipt_header: profile.receipt_header ?? '',
    prescription_header: profile.prescription_header ?? '',
  });
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});
  const [logoError, setLogoError] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = clinicProfileSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setClientErrors(next);
      return;
    }
    setClientErrors({});
    form.put('/settings/profile', { preserveScroll: true });
  }

  function upload(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    if (!file) {
      return;
    }
    const issue = logoFileIssue(file);
    setLogoError(issue);
    if (issue) {
      return;
    }
    setUploading(true);
    router.post(
      '/settings/profile/logo',
      { logo: file },
      {
        forceFormData: true,
        preserveScroll: true,
        onError: (errors) => {
          setLogoError(errors.logo ?? 'Logo gagal diunggah.');
        },
        onFinish: () => {
          setUploading(false);
        },
      },
    );
  }

  const error = (key: keyof ClinicProfileValues): string | undefined =>
    clientErrors[key] ?? form.errors[key];

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Profil klinik" />
        <h1>Profil klinik</h1>

        <form onSubmit={submit} noValidate className="form-grid">
          <TextField
            field="name"
            label="Nama klinik"
            value={form.data.name}
            onChange={(event) => {
              form.setData('name', event.target.value);
            }}
            error={error('name')}
          />
          <TextField
            field="address"
            label="Alamat"
            value={form.data.address}
            onChange={(event) => {
              form.setData('address', event.target.value);
            }}
            error={error('address')}
          />
          <TextField
            field="license_no"
            label="Nomor izin klinik"
            value={form.data.license_no}
            onChange={(event) => {
              form.setData('license_no', event.target.value);
            }}
            error={error('license_no')}
          />
          <TextField
            field="receipt_header"
            label="Teks tambahan kop (tampil di semua cetakan)"
            value={form.data.receipt_header}
            onChange={(event) => {
              form.setData('receipt_header', event.target.value);
            }}
            error={error('receipt_header')}
          />
          <TextField
            field="prescription_header"
            label="Catatan kaki resep dan kwitansi"
            value={form.data.prescription_header}
            onChange={(event) => {
              form.setData('prescription_header', event.target.value);
            }}
            error={error('prescription_header')}
          />
          <div>
            <Button type="submit" loading={form.processing}>
              Simpan profil
            </Button>
          </div>
        </form>

        <section aria-label="Logo klinik" className="flex flex-col gap-3">
          <h2>Logo</h2>
          {profile.has_logo ? (
            <img
              src="/settings/profile/logo"
              alt="Logo klinik saat ini"
              className="max-h-32 max-w-xs self-start"
            />
          ) : (
            <p className="text-muted text-sm">Belum ada logo.</p>
          )}
          <div className="flex flex-col gap-1">
            <label htmlFor="logo-file" className="text-sm font-medium">
              Unggah logo baru (PNG atau JPG, maksimal 1 MB)
            </label>
            <input
              id="logo-file"
              type="file"
              accept="image/png,image/jpeg"
              disabled={uploading}
              onChange={upload}
            />
          </div>
          {logoError ? <Alert tone="error">{logoError}</Alert> : null}
          {profile.has_logo ? (
            <div>
              <ConfirmAction
                label="Hapus logo"
                message="Logo tidak lagi tampil di cetakan baru."
                confirmLabel="Ya, hapus logo"
                onConfirm={() => {
                  router.delete('/settings/profile/logo', { preserveScroll: true });
                }}
              />
            </div>
          ) : null}
        </section>
      </main>
    </AppLayout>
  );
}
