import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { DatePicker } from '@/components/ui/DatePicker';
import { Select } from '@/components/ui/Select';
import { TextArea } from '@/components/ui/TextArea';
import { TextField } from '@/components/ui/TextField';
import { bookingFormSchema } from '@/features/booking/schemas';
import { usePublicSlots } from '@/features/booking/queries';
import { RequiredProvider } from '@/lib/useRequired';

interface Props {
  slug: string;
  clinic: { name: string; address: string | null };
  welcome: string | null;
  slot_minutes: number;
  today: string;
  last_date: string;
  doctors: { id: string; name: string }[];
  verification: boolean;
}

/** P3-01: halaman booking publik. Pasien memilih dokter dan slot kosong; klinik mengonfirmasi permintaannya. */
export default function Index({
  slug,
  clinic,
  welcome,
  slot_minutes: slotMinutes,
  today,
  last_date: lastDate,
  doctors,
  verification,
}: Props) {
  const { flash } = usePage().props;
  const form = useForm({
    doctor_id: '',
    date: '',
    time: '',
    name: '',
    phone: '',
    complaint: '',
    consent: false,
    website: '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const slots = usePublicSlots(slug, form.data.doctor_id, form.data.date);
  const err = (key: string) =>
    errors[key] ?? (form.errors as Record<string, string | undefined>)[key];

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = bookingFormSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.post(`/book/${slug}`, { preserveScroll: true });
  }

  return (
    <div className="flex min-h-screen items-start justify-center p-3">
      <main className="pane w-full max-w-xl shadow-[3px_3px_6px_rgb(0_0_0/0.3)]">
        <Head title={`Booking online - ${clinic.name}`} />
        <div className="pane-head status-bar border-0 text-white">
          <span>{clinic.name}</span>
        </div>
        <div className="flex flex-col gap-3 p-4">
          <header>
            <h1>Booking janji temu</h1>
            {clinic.address ? <p className="text-muted mt-1 text-sm">{clinic.address}</p> : null}
            {welcome ? <p className="mt-2 text-sm">{welcome}</p> : null}
          </header>
          <Alert tone="info">
            {verification
              ? 'Kami akan mengirim kode verifikasi ke WhatsApp Anda. Janji baru pasti setelah dikonfirmasi klinik.'
              : 'Permintaan Anda dikonfirmasi oleh petugas klinik, yang dapat menghubungi nomor HP Anda. Janji baru pasti setelah dikonfirmasi.'}
          </Alert>
          {flash.error ? <Alert tone="error">{flash.error}</Alert> : null}

          <RequiredProvider>
            <form onSubmit={submit} noValidate aria-label="Booking online" className="form-grid">
              <Select
                label="Dokter"
                required
                placeholder="Pilih dokter"
                value={form.data.doctor_id}
                options={doctors.map((doctor) => ({
                  value: doctor.id,
                  label: doctor.name,
                }))}
                error={err('doctor_id')}
                onChange={(event) => {
                  form.setData((data) => ({
                    ...data,
                    doctor_id: event.target.value,
                    time: '',
                  }));
                }}
              />
              <DatePicker
                label="Tanggal"
                required
                clearable={false}
                value={form.data.date}
                min={today}
                max={lastDate}
                error={err('date')}
                onChange={(value) => {
                  form.setData((data) => ({
                    ...data,
                    date: value,
                    time: '',
                  }));
                }}
              />
              {form.data.doctor_id !== '' && form.data.date !== '' ? (
                <div className="flex flex-col gap-1">
                  <span className="text-sm font-semibold">
                    Jam kosong ({String(slotMinutes)} menit)
                    <span aria-hidden="true" className="text-signal-700 ml-0.5">
                      *
                    </span>
                  </span>
                  {slots.isSuccess && slots.data.length === 0 ? (
                    <p className="text-muted text-sm">Tidak ada jam kosong pada tanggal itu.</p>
                  ) : null}
                  {slots.isError ? (
                    <p role="alert" className="text-danger-600 text-sm">
                      Jam kosong tidak dapat dimuat. Coba lagi.
                    </p>
                  ) : null}
                  <div className="flex flex-wrap gap-2">
                    {(slots.data ?? []).map((slot) => (
                      <button
                        key={slot}
                        type="button"
                        aria-pressed={slot === form.data.time}
                        className="btn btn-secondary"
                        onClick={() => {
                          form.setData('time', slot);
                        }}
                      >
                        {slot}
                      </button>
                    ))}
                  </div>
                  {err('time') ? (
                    <p role="alert" className="text-danger-600 text-sm">
                      {err('time')}
                    </p>
                  ) : null}
                </div>
              ) : null}
              <TextField
                label="Nama lengkap"
                required
                autoComplete="name"
                value={form.data.name}
                error={err('name')}
                onChange={(event) => {
                  form.setData('name', event.target.value);
                }}
              />
              <TextField
                label="Nomor HP / WhatsApp"
                required
                type="tel"
                autoComplete="tel"
                inputMode="tel"
                value={form.data.phone}
                error={err('phone')}
                onChange={(event) => {
                  form.setData('phone', event.target.value);
                }}
              />
              <TextArea
                label="Keluhan singkat (opsional)"
                rows={3}
                required={false}
                maxLength={300}
                value={form.data.complaint}
                error={err('complaint')}
                onChange={(event) => {
                  form.setData('complaint', event.target.value);
                }}
              />
              {/* Jebakan bot: manusia tidak melihat dan tidak mengisinya. */}
              <div aria-hidden="true" className="absolute -left-[9999px]">
                <label>
                  Situs web
                  <input
                    type="text"
                    tabIndex={-1}
                    autoComplete="off"
                    value={form.data.website}
                    onChange={(event) => {
                      form.setData('website', event.target.value);
                    }}
                  />
                </label>
              </div>
              <label className="flex items-start gap-2 text-sm">
                <input
                  type="checkbox"
                  className="mt-1"
                  checked={form.data.consent}
                  onChange={(event) => {
                    form.setData('consent', event.target.checked);
                  }}
                />
                <span>
                  Saya setuju nomor HP saya dipakai klinik untuk mengonfirmasi dan mengingatkan
                  janji ini.
                </span>
              </label>
              {err('consent') ? (
                <p role="alert" className="text-danger-600 text-sm">
                  {err('consent')}
                </p>
              ) : null}
              <div>
                <Button type="submit" loading={form.processing}>
                  Kirim permintaan
                </Button>
              </div>
            </form>
          </RequiredProvider>
          <p className="text-muted text-xs">
            Jangan menuliskan data kesehatan rinci di keluhan. Data Anda hanya dipakai klinik ini.
          </p>
        </div>
      </main>
    </div>
  );
}
