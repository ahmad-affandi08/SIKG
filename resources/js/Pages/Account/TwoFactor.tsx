import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { twoFactorChangeSchema } from '@/features/identityAccess/schemas';
import type { TwoFactorChangeValues } from '@/features/identityAccess/schemas';

interface Props {
  status: {
    enabled: boolean;
    pending: boolean;
    recovery_remaining: number;
    secret: string | null;
    uri: string | null;
  };
  recoveryCodes: string[] | null;
}

type Action = 'confirm' | 'disable' | 'recovery-codes';

/** AUTH-05: aktifkan, matikan, dan perbarui kode pemulihan verifikasi dua langkah (TOTP) milik sendiri. */
export default function TwoFactor({ status, recoveryCodes }: Props) {
  const form = useForm<TwoFactorChangeValues>({ password: '', code: '' });
  const [errors, setErrors] = useState<Record<string, string>>({});

  function submit(action: Action) {
    return (event: SyntheticEvent<HTMLFormElement>) => {
      event.preventDefault();
      const result = twoFactorChangeSchema.safeParse(form.data);
      if (!result.success) {
        const next: Record<string, string> = {};
        for (const issue of result.error.issues) {
          next[String(issue.path[0])] ??= issue.message;
        }
        setErrors(next);
        return;
      }
      setErrors({});
      form.post(`/account/two-factor/${action}`, {
        preserveScroll: true,
        onFinish: () => {
          form.reset();
        },
      });
    };
  }

  const error = (key: keyof TwoFactorChangeValues): string | undefined =>
    errors[key] ?? form.errors[key];

  const fields = (
    <>
      <TextField
        label="Kata sandi Anda"
        type="password"
        autoComplete="current-password"
        value={form.data.password}
        onChange={(event) => {
          form.setData('password', event.target.value);
        }}
        error={error('password')}
      />
      <TextField
        label="Kode dari aplikasi autentikator"
        autoComplete="one-time-code"
        value={form.data.code}
        onChange={(event) => {
          form.setData('code', event.target.value);
        }}
        error={error('code')}
      />
    </>
  );

  return (
    <AppLayout>
      <main className="flex max-w-xl flex-col gap-3 p-3">
        <PageHead title="Verifikasi dua langkah" />
        <PageHeader
          title="Verifikasi dua langkah"
          back={{ href: '/account/password', label: 'akun' }}
          description="Opsional. Setelah aktif, masuk juga meminta kode dari aplikasi autentikator di ponsel Anda."
        />

        {recoveryCodes ? (
          <section aria-label="Kode pemulihan" className="flex flex-col gap-2">
            <Alert tone="warning">
              Simpan kode pemulihan ini di tempat aman. Tiap kode hanya berlaku sekali dan tidak
              akan ditampilkan lagi.
            </Alert>
            <ul className="grid grid-cols-2 gap-1 font-mono text-sm">
              {recoveryCodes.map((code) => (
                <li key={code}>{code}</li>
              ))}
            </ul>
          </section>
        ) : null}

        {status.enabled ? (
          <>
            <Alert tone="success">
              Verifikasi dua langkah aktif. Sisa kode pemulihan: {status.recovery_remaining}.
            </Alert>
            <form
              onSubmit={submit('recovery-codes')}
              noValidate
              aria-label="Perbarui kode pemulihan"
              className="form-grid"
            >
              <h2>Perbarui kode pemulihan</h2>
              <p className="text-muted text-sm">Kode lama langsung tidak berlaku lagi.</p>
              {fields}
              <div>
                <Button type="submit" variant="secondary" loading={form.processing}>
                  Buat kode pemulihan baru
                </Button>
              </div>
            </form>
            <form
              onSubmit={submit('disable')}
              noValidate
              aria-label="Matikan verifikasi dua langkah"
              className="form-grid"
            >
              <h2>Matikan verifikasi dua langkah</h2>
              <p className="text-muted text-sm">
                Masuk kembali hanya memakai kata sandi. Gunakan kode autentikator atau kode
                pemulihan untuk mengonfirmasi.
              </p>
              {fields}
              <div>
                <Button type="submit" variant="danger" loading={form.processing}>
                  Matikan
                </Button>
              </div>
            </form>
          </>
        ) : status.pending && status.secret && status.uri ? (
          <form
            onSubmit={submit('confirm')}
            noValidate
            aria-label="Aktifkan verifikasi dua langkah"
            className="form-grid"
          >
            <h2>1. Daftarkan di aplikasi autentikator</h2>
            <p className="text-sm">
              Buka aplikasi autentikator (misalnya Google Authenticator, Microsoft Authenticator,
              atau Aegis), pilih tambah akun dengan kunci setelan, lalu masukkan kunci ini. Jenis
              kunci: berbasis waktu.
            </p>
            <p className="field font-mono text-base" aria-label="Kunci setelan">
              {status.secret}
            </p>
            <p className="text-muted text-sm">
              Alamat setelan untuk aplikasi yang menerimanya:{' '}
              <span className="font-mono break-all">{status.uri}</span>
            </p>
            <h2>2. Konfirmasi</h2>
            {fields}
            <div className="flex gap-3">
              <Button type="submit" loading={form.processing}>
                Aktifkan
              </Button>
              <Button
                variant="secondary"
                onClick={() => {
                  router.post('/account/two-factor/start', {}, { preserveScroll: true });
                }}
              >
                Buat kunci baru
              </Button>
            </div>
          </form>
        ) : (
          <section aria-label="Mulai" className="flex flex-col gap-2">
            <p className="text-sm">
              Verifikasi dua langkah belum aktif. Anda akan memerlukan aplikasi autentikator di
              ponsel. Kode pemulihan diberikan untuk berjaga-jaga bila ponsel hilang.
            </p>
            <div>
              <Button
                onClick={() => {
                  router.post('/account/two-factor/start', {}, { preserveScroll: true });
                }}
              >
                Mulai aktifkan
              </Button>
            </div>
          </section>
        )}
      </main>
    </AppLayout>
  );
}
