import { Head, useForm } from '@inertiajs/react';
import type { SyntheticEvent } from 'react';
import { useState } from 'react';

import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { twoFactorCodeSchema } from '@/features/identityAccess/schemas';

/** AUTH-05: langkah kedua masuk. Menerima kode 6 digit dari autentikator atau satu kode pemulihan. */
export default function TwoFactor() {
  const form = useForm({ code: '' });
  const [clientError, setClientError] = useState<string | null>(null);

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = twoFactorCodeSchema.safeParse(form.data.code);
    if (!result.success) {
      setClientError(result.error.issues[0]?.message ?? 'Kode tidak valid.');
      return;
    }
    setClientError(null);
    form.post('/login/two-factor', {
      onFinish: () => {
        form.reset('code');
      },
    });
  }

  return (
    <div className="login-backdrop flex min-h-screen items-center justify-center p-3">
      <main className="pane w-full max-w-sm shadow-[3px_3px_6px_rgb(0_0_0/0.3)]">
        <Head title="Verifikasi dua langkah" />
        <div className="pane-head status-bar border-0 text-white">
          <span>SIKG - Sistem Informasi Klinik Gigi</span>
        </div>
        <div className="flex flex-col gap-3 p-4">
          <header>
            <h1>Verifikasi dua langkah</h1>
            <p className="text-muted mt-1">
              Masukkan kode 6 digit dari aplikasi autentikator Anda. Bila perangkat hilang, pakai
              salah satu kode pemulihan.
            </p>
          </header>
          <form onSubmit={submit} noValidate className="form-grid">
            <TextField
              label="Kode verifikasi"
              autoComplete="one-time-code"
              inputMode="text"
              required
              value={form.data.code}
              onChange={(event) => {
                form.setData('code', event.target.value);
              }}
              error={clientError ?? form.errors.code}
            />
            <div className="flex gap-3">
              <Button type="submit" loading={form.processing}>
                Verifikasi
              </Button>
              <a href="/login" className="btn btn-secondary">
                Batal
              </a>
            </div>
          </form>
        </div>
      </main>
    </div>
  );
}
