import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { doctorFormSchema, feeValueToText } from '@/features/clinic/doctor';
import type { DoctorFormValues } from '@/features/clinic/doctor';
import { WEEKDAYS } from '@/features/clinic/schemas';

interface RuleDto {
  mode: 'percentage' | 'fixed';
  value: number;
}

interface Props {
  doctor: {
    id: string;
    name: string;
    sip_no: string | null;
    specialty: string | null;
    branch_id: string | null;
    user_id: string | null;
    fee_scheme: { default: RuleDto; overrides: Record<string, RuleDto> };
    schedule: { weekday: number; start: string; end: string }[];
  } | null;
  branches: SelectOption[];
  users: SelectOption[];
  feeModes: SelectOption[];
}

function initialValues(doctor: Props['doctor']): DoctorFormValues {
  if (doctor === null) {
    return {
      name: '',
      sip_no: '',
      specialty: '',
      branch_id: '',
      user_id: '',
      fee_mode: 'percentage',
      fee_value: '0',
      fee_overrides: [],
      schedule: [],
    };
  }
  return {
    name: doctor.name,
    sip_no: doctor.sip_no ?? '',
    specialty: doctor.specialty ?? '',
    branch_id: doctor.branch_id ?? '',
    user_id: doctor.user_id ?? '',
    fee_mode: doctor.fee_scheme.default.mode,
    fee_value: feeValueToText(doctor.fee_scheme.default.mode, doctor.fee_scheme.default.value),
    fee_overrides: Object.entries(doctor.fee_scheme.overrides).map(([code, rule]) => ({
      code,
      mode: rule.mode,
      value: feeValueToText(rule.mode, rule.value),
    })),
    schedule: doctor.schedule,
  };
}

export default function Form({ doctor, branches, users, feeModes }: Props) {
  const form = useForm<DoctorFormValues>(initialValues(doctor));
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});
  const editing = doctor !== null;

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = doctorFormSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[issue.path.map(String).join('.')] ??= issue.message;
      }
      setClientErrors(next);
      return;
    }
    setClientErrors({});
    if (editing) {
      form.put(`/settings/doctors/${doctor.id}`);
    } else {
      form.post('/settings/doctors');
    }
  }

  const error = (path: string): string | undefined =>
    clientErrors[path] ?? (form.errors as Record<string, string | undefined>)[path];

  function patchOverride(index: number, patch: Partial<DoctorFormValues['fee_overrides'][number]>) {
    form.setData(
      'fee_overrides',
      form.data.fee_overrides.map((row, i) => (i === index ? { ...row, ...patch } : row)),
    );
  }

  function patchSlot(index: number, patch: Partial<DoctorFormValues['schedule'][number]>) {
    form.setData(
      'schedule',
      form.data.schedule.map((slot, i) => (i === index ? { ...slot, ...patch } : slot)),
    );
  }

  const valueHelp =
    form.data.fee_mode === 'percentage' ? 'Dalam persen, contoh 30 atau 32.5.' : 'Rupiah bulat.';

  return (
    <AppLayout>
      <main className="mx-auto flex max-w-3xl flex-col gap-4 p-6 lg:px-12 lg:py-10">
        <Head title={editing ? 'Ubah dokter' : 'Tambah dokter'} />
        <h1>{editing ? 'Ubah dokter' : 'Tambah dokter'}</h1>

        <form onSubmit={submit} noValidate className="flex flex-col gap-6">
          <section className="flex flex-col gap-4" aria-label="Data dokter">
            <TextField
              label="Nama dokter"
              value={form.data.name}
              onChange={(event) => {
                form.setData('name', event.target.value);
              }}
              error={error('name')}
            />
            <TextField
              label="Nomor SIP"
              value={form.data.sip_no}
              onChange={(event) => {
                form.setData('sip_no', event.target.value);
              }}
              error={error('sip_no')}
            />
            <TextField
              label="Spesialisasi"
              value={form.data.specialty}
              onChange={(event) => {
                form.setData('specialty', event.target.value);
              }}
              error={error('specialty')}
            />
            <Select
              label="Cabang"
              placeholder="Semua cabang"
              options={branches}
              value={form.data.branch_id}
              onChange={(event) => {
                form.setData('branch_id', event.target.value);
              }}
              error={error('branch_id')}
            />
            <Select
              label="Akun pengguna (opsional)"
              placeholder="Tidak ditautkan"
              options={users}
              value={form.data.user_id}
              onChange={(event) => {
                form.setData('user_id', event.target.value);
              }}
              error={error('user_id')}
            />
          </section>

          <fieldset className="flex flex-col gap-4">
            <legend className="text-lg font-semibold">Skema jasa dokter</legend>
            <Select
              label="Cara hitung bawaan"
              options={feeModes}
              value={form.data.fee_mode}
              onChange={(event) => {
                form.setData('fee_mode', event.target.value === 'fixed' ? 'fixed' : 'percentage');
              }}
              error={error('fee_mode')}
            />
            <TextField
              label="Nilai jasa bawaan"
              inputMode="decimal"
              value={form.data.fee_value}
              onChange={(event) => {
                form.setData('fee_value', event.target.value);
              }}
              error={error('fee_value')}
            />
            <p className="text-muted text-sm">{valueHelp}</p>

            <h2>Pengecualian per tindakan</h2>
            {form.data.fee_overrides.map((row, index) => (
              <div key={index} className="card flex flex-col gap-3 p-3">
                <TextField
                  label={`Kode tindakan ${String(index + 1)}`}
                  value={row.code}
                  onChange={(event) => {
                    patchOverride(index, { code: event.target.value });
                  }}
                  error={error(`fee_overrides.${String(index)}.code`)}
                />
                <Select
                  label={`Cara hitung ${String(index + 1)}`}
                  options={feeModes}
                  value={row.mode}
                  onChange={(event) => {
                    patchOverride(index, {
                      mode: event.target.value === 'fixed' ? 'fixed' : 'percentage',
                    });
                  }}
                />
                <TextField
                  label={`Nilai jasa ${String(index + 1)}`}
                  inputMode="decimal"
                  value={row.value}
                  onChange={(event) => {
                    patchOverride(index, { value: event.target.value });
                  }}
                  error={error(`fee_overrides.${String(index)}.value`)}
                />
                <div>
                  <Button
                    variant="secondary"
                    onClick={() => {
                      form.setData(
                        'fee_overrides',
                        form.data.fee_overrides.filter((_, i) => i !== index),
                      );
                    }}
                  >
                    {`Hapus pengecualian ${String(index + 1)}`}
                  </Button>
                </div>
              </div>
            ))}
            <div>
              <Button
                variant="secondary"
                onClick={() => {
                  form.setData('fee_overrides', [
                    ...form.data.fee_overrides,
                    { code: '', mode: 'fixed', value: '0' },
                  ]);
                }}
              >
                Tambah pengecualian
              </Button>
            </div>
          </fieldset>

          <fieldset className="flex flex-col gap-4">
            <legend className="text-lg font-semibold">Jadwal praktik mingguan</legend>
            {form.data.schedule.map((slot, index) => (
              <div key={index} className="card flex flex-wrap items-end gap-3 p-3">
                <Select
                  label={`Hari ${String(index + 1)}`}
                  options={WEEKDAYS.map((w) => ({ value: w.day, label: w.label }))}
                  value={String(slot.weekday)}
                  onChange={(event) => {
                    patchSlot(index, { weekday: Number(event.target.value) });
                  }}
                />
                <TextField
                  label={`Mulai ${String(index + 1)}`}
                  type="time"
                  value={slot.start}
                  onChange={(event) => {
                    patchSlot(index, { start: event.target.value });
                  }}
                  error={error(`schedule.${String(index)}.start`)}
                />
                <TextField
                  label={`Selesai ${String(index + 1)}`}
                  type="time"
                  value={slot.end}
                  onChange={(event) => {
                    patchSlot(index, { end: event.target.value });
                  }}
                  error={error(`schedule.${String(index)}.end`)}
                />
                <Button
                  variant="secondary"
                  onClick={() => {
                    form.setData(
                      'schedule',
                      form.data.schedule.filter((_, i) => i !== index),
                    );
                  }}
                >
                  {`Hapus blok ${String(index + 1)}`}
                </Button>
              </div>
            ))}
            <div>
              <Button
                variant="secondary"
                onClick={() => {
                  form.setData('schedule', [
                    ...form.data.schedule,
                    { weekday: 1, start: '09:00', end: '12:00' },
                  ]);
                }}
              >
                Tambah blok jam
              </Button>
            </div>
          </fieldset>

          <div className="flex gap-3">
            <Button type="submit" loading={form.processing}>
              {editing ? 'Simpan perubahan' : 'Tambah dokter'}
            </Button>
            <Link href="/settings/doctors" className="btn btn-secondary">
              Kembali
            </Link>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
