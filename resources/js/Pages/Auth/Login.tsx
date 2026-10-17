import { Head, useForm } from '@inertiajs/react';

import { LoginForm } from '@/features/identityAccess/components/LoginForm';
import type { LoginValues } from '@/features/identityAccess/schemas';

export default function Login() {
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
    <main className="mx-auto flex min-h-screen max-w-sm flex-col justify-center gap-6 p-6">
      <Head title="Masuk" />
      <header>
        <h1 className="text-2xl font-semibold">Masuk ke SIKG</h1>
        <p className="mt-1 text-sm text-slate-600">Sistem Informasi Klinik Gigi</p>
      </header>
      <LoginForm serverErrors={serverErrors} processing={form.processing} onSubmit={submit} />
    </main>
  );
}
