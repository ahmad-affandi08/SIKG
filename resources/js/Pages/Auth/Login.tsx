import { Head, useForm } from '@inertiajs/react';

import { Icon } from '@/components/layout/icons';
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
      <aside className="bg-ink relative hidden flex-col justify-between overflow-hidden p-12 text-slate-300 lg:flex">
        <div
          aria-hidden="true"
          className="from-brand-600/40 pointer-events-none absolute -top-32 -right-32 size-96 rounded-full bg-gradient-to-br to-transparent blur-3xl"
        />
        <div className="relative flex items-center gap-3">
          <span className="bg-brand-600 flex size-10 items-center justify-center rounded-xl text-white">
            <Icon name="tooth" className="size-6" />
          </span>
          <span className="text-lg font-semibold text-white">SIKG</span>
        </div>
        <div className="relative max-w-md">
          <h2 className="text-3xl leading-tight font-semibold tracking-tight text-white">
            Rekam medis dan operasional klinik gigi, dalam satu tempat.
          </h2>
          <ul className="mt-6 flex flex-col gap-3 text-sm">
            <li>Pendaftaran, jadwal, dan antrean pasien</li>
            <li>Odontogram, diagnosa, tindakan, dan resep</li>
            <li>Stok obat dengan peringatan dan jejak audit</li>
          </ul>
        </div>
        <p className="relative text-xs text-slate-400">
          Data pasien dilindungi dan setiap akses tercatat.
        </p>
      </aside>

      <main className="flex items-center justify-center p-6">
        <Head title="Masuk" />
        <div className="card w-full max-w-sm p-8">
          <header className="mb-6">
            <h1 className="text-2xl font-semibold">Masuk ke SIKG</h1>
            <p className="mt-1 text-sm text-slate-600">Sistem Informasi Klinik Gigi</p>
          </header>
          <LoginForm serverErrors={serverErrors} processing={form.processing} onSubmit={submit} />
        </div>
      </main>
    </div>
  );
}
