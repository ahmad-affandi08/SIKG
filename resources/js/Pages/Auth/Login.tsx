import { Head, useForm } from '@inertiajs/react';

import { RequiredProvider } from '@/lib/useRequired';
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
    <div className="grid min-h-screen lg:grid-cols-[1.1fr_1fr]">
      <aside className="border-ink hidden flex-col justify-between border-r p-12 lg:flex">
        <p className="text-xl font-bold tracking-tight">SIKG</p>
        <div className="max-w-lg">
          <p className="eyebrow text-signal-700">Sistem Informasi Klinik Gigi</p>
          <h2 className="mt-3 border-0 p-0 text-5xl leading-[1.05] font-semibold tracking-tight normal-case">
            Rekam medis dan operasional klinik, dalam satu tempat.
          </h2>
          <ul className="border-ink mt-8 border-t-2 text-sm">
            <li className="border-b border-[#ddd] py-3">Pendaftaran, jadwal, dan antrean pasien</li>
            <li className="border-b border-[#ddd] py-3">
              Odontogram, diagnosa, tindakan, dan resep
            </li>
            <li className="border-b border-[#ddd] py-3">
              Stok obat dengan peringatan dan jejak audit
            </li>
          </ul>
        </div>
        <p className="text-muted text-xs">Data pasien dilindungi dan setiap akses tercatat.</p>
      </aside>

      <main className="flex items-center justify-center p-6">
        <Head title="Masuk" />
        <div className="w-full max-w-sm">
          <header className="mb-8">
            <h1>Masuk</h1>
            <p className="text-muted mt-1 text-sm">Gunakan email atau nama pengguna Anda.</p>
          </header>
          <RequiredProvider>
            <LoginForm serverErrors={serverErrors} processing={form.processing} onSubmit={submit} />
          </RequiredProvider>
        </div>
      </main>
    </div>
  );
}
