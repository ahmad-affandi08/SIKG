import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Button } from '@/components/ui/Button';
import { TextArea } from '@/components/ui/TextArea';
import { TextField } from '@/components/ui/TextField';
import { bookingSettingsSchema } from '@/features/booking/schemas';

interface Props {
  settings: {
    enabled: boolean;
    slug: string;
    slot_minutes: number;
    horizon_days: number;
    min_lead_hours: number;
    hold_hours: number;
    max_active_per_phone: number;
    otp_template: string;
    welcome_text: string;
  };
}

type Key =
  | 'slug'
  | 'slot_minutes'
  | 'horizon_days'
  | 'min_lead_hours'
  | 'hold_hours'
  | 'max_active_per_phone'
  | 'otp_template'
  | 'welcome_text';

/** P3-01: pengaturan booking online (alamat halaman publik, slot, batas, dan kode verifikasi WhatsApp). */
export default function Booking({ settings }: Props) {
  const form = useForm({
    enabled: settings.enabled,
    slug: settings.slug,
    slot_minutes: String(settings.slot_minutes),
    horizon_days: String(settings.horizon_days),
    min_lead_hours: String(settings.min_lead_hours),
    hold_hours: String(settings.hold_hours),
    max_active_per_phone: String(settings.max_active_per_phone),
    otp_template: settings.otp_template,
    welcome_text: settings.welcome_text,
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const err = (key: Key) => errors[key] ?? (form.errors as Record<string, string | undefined>)[key];

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = bookingSettingsSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.post('/settings/booking', { preserveScroll: true });
  }

  const text = (key: Key) => ({
    value: form.data[key],
    onChange: (event: { target: { value: string } }) => {
      form.setData(key, event.target.value);
    },
    error: err(key),
  });

  return (
    <AppLayout>
      <main className="flex max-w-xl flex-col gap-3 p-3">
        <PageHead title="Pengaturan booking online" />
        <PageHeader
          title="Booking online"
          description="Halaman publik tempat pasien memilih dokter dan slot kosong. Permintaan dikonfirmasi petugas."
          back={{ href: '/booking-requests', label: 'permintaan' }}
        />
        <form
          onSubmit={submit}
          noValidate
          aria-label="Pengaturan booking online"
          className="form-grid"
        >
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.data.enabled}
              onChange={(event) => {
                form.setData('enabled', event.target.checked);
              }}
            />
            Aktifkan booking online
          </label>
          <TextField label="Alamat halaman (/book/...)" {...text('slug')} />
          <TextField
            label="Durasi slot (menit)"
            inputMode="numeric"
            required
            {...text('slot_minutes')}
          />
          <TextField
            label="Dapat dipesan sampai (hari ke depan)"
            inputMode="numeric"
            required
            {...text('horizon_days')}
          />
          <TextField
            label="Jeda minimum sebelum janji (jam)"
            inputMode="numeric"
            required
            {...text('min_lead_hours')}
          />
          <TextField
            label="Masa tahan permintaan (jam)"
            inputMode="numeric"
            required
            {...text('hold_hours')}
          />
          <TextField
            label="Janji aktif maksimal per nomor HP"
            inputMode="numeric"
            required
            {...text('max_active_per_phone')}
          />
          <TextField
            label="Nama templat WhatsApp kode verifikasi (opsional)"
            {...text('otp_template')}
          />
          <TextArea
            label="Teks sambutan (opsional)"
            rows={3}
            required={false}
            {...text('welcome_text')}
          />
          <p className="text-muted text-xs">
            Kode verifikasi hanya dikirim bila mode WhatsApp API pada Pengaturan pengingat siap dan
            templat diisi. Tanpa itu, petugas menghubungi pasien sebelum mengonfirmasi.
          </p>
          <div>
            <Button type="submit" loading={form.processing}>
              Simpan
            </Button>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
