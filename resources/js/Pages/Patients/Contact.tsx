import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { TextField } from '@/components/ui/TextField';
import { buildContactSchema } from '@/features/patient/schemas';
import type { ContactFormValues } from '@/features/patient/schemas';

interface Props {
  patient: { id: string; name: string; medical_record_no: string };
  contact: {
    email: string | null;
    telegram_linked: boolean;
    link_pending: boolean;
    reminders_opt_in: boolean;
    opt_in_at: string | null;
    email_available: boolean;
    telegram_available: boolean;
  };
  telegramLink: { url: string; expires_at: string } | null;
}

/** JDW-07: kontak pengingat janji (email dan Telegram) beserta persetujuan pasien. */
export default function Contact({ patient, contact, telegramLink }: Props) {
  const form = useForm<ContactFormValues>({
    email: contact.email ?? '',
    reminders_opt_in: contact.reminders_opt_in,
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const back = `/patients/${patient.id}`;

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = buildContactSchema({ telegramLinked: contact.telegram_linked }).safeParse(
      form.data,
    );
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.put(`/patients/${patient.id}/contact`);
  }

  const err = (key: keyof ContactFormValues) => errors[key] ?? form.errors[key];

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Kontak pengingat" />
        <PageHeader
          title="Kontak pengingat janji"
          back={{ href: back, label: 'rekam pasien' }}
          description={`${patient.name} (${patient.medical_record_no})`}
        />

        <form onSubmit={submit} noValidate aria-label="Kontak pengingat" className="form-grid">
          <fieldset className="form-grid">
            <legend className="text-lg font-semibold">Email</legend>
            <TextField
              field="email"
              label="Alamat email pasien"
              type="email"
              inputMode="email"
              value={form.data.email}
              onChange={(event) => {
                form.setData('email', event.target.value);
              }}
              error={err('email')}
            />
            {contact.email_available ? null : (
              <Alert tone="warning">
                Pengiriman email belum diatur di server. Alamat tetap disimpan, tetapi belum ada
                email yang terkirim.
              </Alert>
            )}
          </fieldset>

          <fieldset className="form-grid">
            <legend className="text-lg font-semibold">Persetujuan pengingat</legend>
            <label className="min-h-touch flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={form.data.reminders_opt_in}
                aria-invalid={err('reminders_opt_in') ? true : undefined}
                onChange={(event) => {
                  form.setData('reminders_opt_in', event.target.checked);
                }}
              />
              <span>
                Pasien setuju menerima pengingat jadwal kunjungan (sehari sebelumnya dan pada hari
                kunjungan) lewat saluran di atas.
              </span>
            </label>
            {err('reminders_opt_in') ? (
              <p role="alert" className="text-danger-600 text-sm">
                {err('reminders_opt_in')}
              </p>
            ) : null}
            {contact.opt_in_at ? (
              <p className="text-muted text-sm">Persetujuan pertama dicatat sebelumnya.</p>
            ) : null}
            <p className="text-muted text-sm">
              Pengingat hanya memuat waktu janji dan nama dokter, tanpa diagnosis atau tindakan.
            </p>
          </fieldset>

          <div className="flex flex-wrap gap-3">
            <Button type="submit" loading={form.processing}>
              Simpan kontak
            </Button>
          </div>
        </form>

        <section aria-label="Telegram" className="form-grid">
          <h2>Telegram</h2>
          {contact.telegram_linked ? (
            <div className="flex flex-wrap items-center gap-3">
              <Alert tone="success">Akun Telegram pasien sudah tertaut.</Alert>
              <ConfirmAction
                label="Lepas Telegram"
                message="Pasien tidak lagi menerima pengingat lewat Telegram. Persetujuan pengingat ikut dimatikan bila tidak ada email."
                confirmLabel="Ya, lepas"
                onConfirm={() => {
                  router.delete(`/patients/${patient.id}/contact/telegram`);
                }}
              />
            </div>
          ) : contact.telegram_available ? (
            <div className="form-grid">
              <p className="text-sm">
                Buat tautan sekali pakai, lalu minta pasien membukanya di ponsel dan menekan tombol
                Mulai. Tautan berlaku 7 hari.
              </p>
              {telegramLink ? (
                <div className="flex flex-col gap-1">
                  <Alert tone="info">
                    Tautan dibuat. Kirimkan ke pasien; tautan ini hanya tampil sekali.
                  </Alert>
                  <input
                    readOnly
                    aria-label="Tautan penautan Telegram"
                    className="field font-mono text-sm"
                    value={telegramLink.url}
                    onFocus={(event) => {
                      event.target.select();
                    }}
                  />
                </div>
              ) : contact.link_pending ? (
                <p className="text-muted text-sm">
                  Ada tautan yang belum dipakai. Membuat tautan baru mematikan tautan lama.
                </p>
              ) : null}
              <div>
                <Button
                  variant="secondary"
                  onClick={() => {
                    router.post(`/patients/${patient.id}/contact/telegram-link`);
                  }}
                >
                  Buat tautan Telegram
                </Button>
              </div>
            </div>
          ) : (
            <Alert tone="warning">
              Bot Telegram belum diatur oleh administrator server, sehingga Telegram belum dapat
              dipakai.
            </Alert>
          )}
        </section>
      </main>
    </AppLayout>
  );
}
