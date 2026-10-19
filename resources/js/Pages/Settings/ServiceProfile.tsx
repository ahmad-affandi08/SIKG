import { useForm } from '@inertiajs/react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { serviceProfileSchema } from '@/features/clinic/schemas';

interface Option {
  value: string;
  label: string;
  description: string;
}

interface Props {
  current: string;
  options: Option[];
}

/** KLU-01: profil layanan klinik. Hanya mengatur menu dan istilah yang tampil; data tidak dihapus. */
export default function ServiceProfile({ current, options }: Props) {
  const form = useForm({ profile: current });
  const changed = form.data.profile !== current;

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!serviceProfileSchema.safeParse(form.data).success) {
      return;
    }
    form.put('/settings/service-profile', { preserveScroll: true });
  }

  return (
    <AppLayout>
      <main className="flex max-w-3xl flex-col gap-4 p-3">
        <PageHead title="Profil layanan" />
        <PageHeader
          title="Profil layanan"
          description="Pilih jenis klinik Anda. Profil mengatur menu dan istilah yang tampil."
        />

        <form onSubmit={submit} noValidate aria-label="Profil layanan" className="form-grid">
          <fieldset className="flex flex-col gap-2">
            <legend className="text-lg font-semibold">Jenis klinik</legend>
            {options.map((option) => (
              <label
                key={option.value}
                htmlFor={`profile-${option.value}`}
                className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 ${
                  form.data.profile === option.value
                    ? 'border-brand-600 bg-brand-50'
                    : 'border-line-soft'
                }`}
              >
                <input
                  id={`profile-${option.value}`}
                  aria-label={option.label}
                  type="radio"
                  name="profile"
                  className="mt-1"
                  value={option.value}
                  checked={form.data.profile === option.value}
                  onChange={() => {
                    form.setData('profile', option.value);
                  }}
                />
                <span className="flex flex-col gap-0.5">
                  <span className="font-semibold">{option.label}</span>
                  <span className="text-muted text-sm">{option.description}</span>
                </span>
              </label>
            ))}
          </fieldset>

          {changed ? (
            <Alert tone="warning">
              Mengganti profil hanya menyembunyikan atau menampilkan fitur. Data yang sudah ada
              (odontogram, ortodonti, pesanan lab) tidak dihapus dan muncul lagi bila profil
              dikembalikan. Selama profil Umum, data itu tidak dapat dibuka. Selesaikan dulu cicilan
              ortodonti dan pesanan lab yang masih berjalan.
            </Alert>
          ) : null}
          {form.errors.profile ? (
            <p role="alert" className="text-danger-600 text-sm">
              {form.errors.profile}
            </p>
          ) : null}

          <div>
            <Button type="submit" loading={form.processing} disabled={!changed}>
              Simpan profil layanan
            </Button>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
