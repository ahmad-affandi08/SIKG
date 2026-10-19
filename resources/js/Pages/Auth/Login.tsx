import { Head, useForm } from '@inertiajs/react';

import { AuthBrand, type AuthBrandData } from '@/features/identityAccess/components/AuthBrand';
import { RequiredProvider } from '@/lib/useRequired';
import { LoginForm } from '@/features/identityAccess/components/LoginForm';
import type { LoginValues } from '@/features/identityAccess/schemas';

export default function Login({ brand }: { brand?: AuthBrandData | null }) {
  const form = useForm<LoginValues>({ identifier: '', password: '' });

  function submit(values: LoginValues) {
    form.transform(() => values);
    form.post('/login', {
      preserveScroll: true,
      onFinish: () => {
        form.reset('password');
      },
    });
  }

  const serverErrors: Partial<Record<keyof LoginValues, string>> = {};
  if (form.errors.identifier) {
    serverErrors.identifier = form.errors.identifier;
  }
  if (form.errors.password) {
    serverErrors.password = form.errors.password;
  }

  return (
    <div className="login-backdrop flex min-h-screen items-center justify-center p-3">
      <main className="pane w-full max-w-sm shadow-[3px_3px_6px_rgb(0_0_0/0.3)]">
        <Head title="Masuk" />
        <div className="pane-head status-bar border-0 text-white">
          <span>SIKG - Sistem Informasi Klinik Gigi</span>
        </div>
        <div className="flex flex-col gap-3 p-4">
          <AuthBrand brand={brand} />
          <header>
            <h1>Masuk</h1>
            <p className="text-muted mt-1">Gunakan email atau nama pengguna Anda.</p>
          </header>
          <RequiredProvider>
            <LoginForm serverErrors={serverErrors} processing={form.processing} onSubmit={submit} />
          </RequiredProvider>
          <p className="text-muted text-xs">Data pasien dilindungi dan setiap akses tercatat.</p>
        </div>
      </main>
    </div>
  );
}
