import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { changePasswordSchema } from '@/features/identityAccess/schemas';
import type { ChangePasswordValues } from '@/features/identityAccess/schemas';

interface Props {
  forced: boolean;
  minLength: number;
}

/** AUTH-06: ganti kata sandi sendiri; wajib bila kata sandi saat ini masih sementara. */
export default function Password({ forced, minLength }: Props) {
  const form = useForm<ChangePasswordValues>({
    current_password: '',
    password: '',
    password_confirmation: '',
  });
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = changePasswordSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setClientErrors(next);
      return;
    }
    setClientErrors({});
    form.put('/account/password', {
      onSuccess: () => {
        form.reset();
      },
    });
  }

  const error = (key: keyof ChangePasswordValues): string | undefined =>
    clientErrors[key] ?? form.errors[key];

  return (
    <AppLayout>
      <main className="flex max-w-xl flex-col gap-3 p-3">
        <PageHead title="Ganti kata sandi" />
        <h1>Ganti kata sandi</h1>
        {forced ? (
          <Alert tone="warning">
            Kata sandi Anda masih sementara. Buat kata sandi baru sebelum memakai fitur lain.
          </Alert>
        ) : (
          <p className="text-muted text-sm">
            Setelah diganti, sesi Anda di perangkat lain akan diakhiri.
          </p>
        )}

        <form onSubmit={submit} noValidate className="form-grid">
          <TextField
            field="current_password"
            label="Kata sandi saat ini"
            type="password"
            autoComplete="current-password"
            value={form.data.current_password}
            onChange={(event) => {
              form.setData('current_password', event.target.value);
            }}
            error={error('current_password')}
          />
          <TextField
            field="password"
            label="Kata sandi baru"
            type="password"
            autoComplete="new-password"
            value={form.data.password}
            onChange={(event) => {
              form.setData('password', event.target.value);
            }}
            error={error('password')}
          />
          <p className="text-muted -mt-1 text-sm">
            Minimal {minLength} karakter, memuat huruf dan angka.
          </p>
          <TextField
            field="password_confirmation"
            label="Ulangi kata sandi baru"
            type="password"
            autoComplete="new-password"
            value={form.data.password_confirmation}
            onChange={(event) => {
              form.setData('password_confirmation', event.target.value);
            }}
            error={error('password_confirmation')}
          />
          <div className="flex gap-3">
            <Button type="submit" loading={form.processing}>
              Ganti kata sandi
            </Button>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
