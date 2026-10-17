import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { Select } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { TimePicker } from '@/components/ui/TimePicker';
import { clinicSettingsSchema, WEEKDAYS } from '@/features/clinic/schemas';
import type { ClinicSettingsValues } from '@/features/clinic/schemas';
import { formatDate } from '@/lib/format';

interface Props {
  timezone: string;
  settings: {
    session_timeout_minutes: number;
    medical_record_pattern: string;
    invoice_pattern: string;
    discount_limits: { pemilik: number; admin: number; kasir: number };
    operating_hours: Record<string, { open: string; close: string } | null>;
  };
  sessionMaxMinutes: number;
  example: { medicalRecordNo: string; invoiceNo: string };
  holidays: { id: string; date: string; name: string }[];
}

const TIMEZONES = [
  { value: 'Asia/Jakarta', label: 'WIB (Asia/Jakarta)' },
  { value: 'Asia/Makassar', label: 'WITA (Asia/Makassar)' },
  { value: 'Asia/Jayapura', label: 'WIT (Asia/Jayapura)' },
];

export default function Clinic({
  timezone,
  settings,
  sessionMaxMinutes,
  example,
  holidays,
}: Props) {
  const form = useForm<ClinicSettingsValues>({
    timezone,
    session_timeout_minutes: settings.session_timeout_minutes,
    medical_record_pattern: settings.medical_record_pattern,
    invoice_pattern: settings.invoice_pattern,
    discount_limits: settings.discount_limits,
    operating_hours: settings.operating_hours,
  });
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  const timezoneOptions = TIMEZONES.some((option) => option.value === timezone)
    ? TIMEZONES
    : [...TIMEZONES, { value: timezone, label: timezone }];

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = clinicSettingsSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        const key = issue.path.join('.');
        next[key] ??= issue.message;
      }
      setClientErrors(next);
      return;
    }
    setClientErrors({});
    form.put('/settings/clinic', { preserveScroll: true });
  }

  function setDay(day: string, value: { open: string; close: string } | null) {
    form.setData('operating_hours', { ...form.data.operating_hours, [day]: value });
  }

  const error = (key: string): string | undefined =>
    clientErrors[key] ?? (form.errors as Record<string, string | undefined>)[key];

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Pengaturan klinik" />
        <h1>Pengaturan klinik</h1>

        <form onSubmit={submit} noValidate className="form-grid">
          {error('settings') ? (
            <p role="alert" className="text-danger-600 text-sm">
              {error('settings')}
            </p>
          ) : null}

          <section aria-labelledby="umum" className="grid gap-4 sm:grid-cols-2">
            <h2 id="umum" className="sm:col-span-2">
              Umum
            </h2>
            <Select
              field="timezone"
              label="Zona waktu klinik"
              options={timezoneOptions}
              value={form.data.timezone}
              onChange={(event) => {
                form.setData('timezone', event.target.value);
              }}
              error={error('timezone')}
            />
            <TextField
              field="session_timeout_minutes"
              label={`Batas sesi tidak aktif (menit, 5 sampai ${sessionMaxMinutes})`}
              type="number"
              min={5}
              max={sessionMaxMinutes}
              value={form.data.session_timeout_minutes}
              onChange={(event) => {
                form.setData('session_timeout_minutes', Number(event.target.value));
              }}
              error={error('session_timeout_minutes')}
            />
          </section>

          <section aria-labelledby="nomor" className="grid gap-4 sm:grid-cols-2">
            <h2 id="nomor" className="sm:col-span-2">
              Format nomor
            </h2>
            <div>
              <TextField
                field="medical_record_pattern"
                label="Format nomor RM"
                value={form.data.medical_record_pattern}
                onChange={(event) => {
                  form.setData('medical_record_pattern', event.target.value);
                }}
                error={error('medical_record_pattern')}
              />
              <p className="text-muted mt-1 text-sm">Contoh tersimpan: {example.medicalRecordNo}</p>
            </div>
            <div>
              <TextField
                field="invoice_pattern"
                label="Format nomor invoice"
                value={form.data.invoice_pattern}
                onChange={(event) => {
                  form.setData('invoice_pattern', event.target.value);
                }}
                error={error('invoice_pattern')}
              />
              <p className="text-muted mt-1 text-sm">Contoh tersimpan: {example.invoiceNo}</p>
            </div>
            <p className="text-muted text-sm sm:col-span-2">
              Token: {'{seq:6}'} nomor urut 6 digit (wajib, satu kali), {'{yyyy}'} {'{yy}'} {'{mm}'}{' '}
              {'{dd}'} tanggal. Urutan mulai dari 1 lagi saat tahun atau bulan di format berganti.
            </p>
          </section>

          <section aria-labelledby="diskon" className="grid gap-4 sm:grid-cols-3">
            <h2 id="diskon" className="sm:col-span-3">
              Batas diskon per peran
            </h2>
            {(
              [
                ['kasir', 'Kasir'],
                ['admin', 'Admin'],
                ['pemilik', 'Pemilik'],
              ] as const
            ).map(([role, label]) => (
              <TextField
                key={role}
                field={`discount_limits.${role}`}
                label={`${label} (persen, 0 sampai 100)`}
                type="number"
                min={0}
                max={100}
                value={form.data.discount_limits[role]}
                onChange={(event) => {
                  form.setData('discount_limits', {
                    ...form.data.discount_limits,
                    [role]: Number(event.target.value),
                  });
                }}
                error={error(`discount_limits.${role}`)}
              />
            ))}
            <p className="text-muted text-sm sm:col-span-3">
              Diskon tagihan atau item di atas batas ini ditolak untuk peran tersebut (KSR-02).
            </p>
          </section>

          <section aria-labelledby="jam" className="flex flex-col gap-3">
            <h2 id="jam">Jam operasional</h2>
            {WEEKDAYS.map(({ day, label }) => {
              const hours = form.data.operating_hours[day] ?? null;
              const dayError = error(`operating_hours.${day}`);
              return (
                <div key={day} className="flex flex-wrap items-center gap-3">
                  <label className="min-h-touch flex w-32 items-center gap-2">
                    <input
                      type="checkbox"
                      checked={hours !== null}
                      onChange={(event) => {
                        setDay(
                          day,
                          event.target.checked ? { open: '08:00', close: '17:00' } : null,
                        );
                      }}
                    />
                    {label}
                  </label>
                  {hours !== null ? (
                    <>
                      <TimePicker
                        field={`operating_hours.${day}.open`}
                        label={`Buka ${label}`}
                        step={15}
                        value={hours.open}
                        onChange={(value) => {
                          setDay(day, { ...hours, open: value });
                        }}
                      />
                      <TimePicker
                        field={`operating_hours.${day}.close`}
                        label={`Tutup ${label}`}
                        step={15}
                        value={hours.close}
                        onChange={(value) => {
                          setDay(day, { ...hours, close: value });
                        }}
                      />
                    </>
                  ) : (
                    <span className="text-muted text-sm">Tutup</span>
                  )}
                  {dayError ? (
                    <p role="alert" className="text-danger-600 text-sm">
                      {dayError}
                    </p>
                  ) : null}
                </div>
              );
            })}
          </section>

          <div>
            <Button type="submit" loading={form.processing}>
              Simpan pengaturan
            </Button>
          </div>
        </form>

        <section aria-labelledby="libur" className="flex flex-col gap-4">
          <h2 id="libur">Hari libur</h2>
          <div>
            <Link href="/settings/holidays/create" className="btn btn-secondary">
              Tambah hari libur
            </Link>
          </div>
          {holidays.length === 0 ? (
            <p className="text-muted text-sm">Belum ada hari libur.</p>
          ) : (
            <table className="w-full text-left text-sm">
              <caption className="sr-only">Daftar hari libur</caption>
              <thead>
                <tr>
                  <th scope="col" className="py-2">
                    Tanggal
                  </th>
                  <th scope="col">Nama</th>
                  <th scope="col">
                    <span className="sr-only">Aksi</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {holidays.map((holiday) => (
                  <tr key={holiday.id}>
                    <td className="py-2">{formatDate(holiday.date)}</td>
                    <td>{holiday.name}</td>
                    <td>
                      <ConfirmAction
                        label={`Hapus ${holiday.name}`}
                        message={`Hari libur "${holiday.name}" akan dihapus dari kalender klinik.`}
                        onConfirm={() => {
                          router.delete(`/settings/holidays/${holiday.id}`, {
                            preserveScroll: true,
                          });
                        }}
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>
      </main>
    </AppLayout>
  );
}
