import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { MoneyField, digitsOnly } from '@/components/ui/MoneyField';
import { TextField } from '@/components/ui/TextField';
import { TimePicker } from '@/components/ui/TimePicker';
import { basisPointsToPercent, doctorFormSchema, feeValueToText } from '@/features/clinic/doctor';
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
    fee_scheme: {
      default: RuleDto;
      overrides: Record<string, RuleDto>;
      tiers?: { up_to: number | null; rate_bp: number }[];
      lab_share_bp?: number;
    };
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
      fee_tiers: [],
      fee_lab_share: '0',
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
    fee_tiers: (doctor.fee_scheme.tiers ?? []).map((tier) => ({
      up_to: tier.up_to === null ? '' : String(tier.up_to),
      rate: basisPointsToPercent(tier.rate_bp),
    })),
    fee_lab_share: basisPointsToPercent(doctor.fee_scheme.lab_share_bp ?? 0),
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

  function patchTier(index: number, patch: Partial<DoctorFormValues['fee_tiers'][number]>) {
    form.setData(
      'fee_tiers',
      form.data.fee_tiers.map((tier, i) => (i === index ? { ...tier, ...patch } : tier)),
    );
  }

  function patchSlot(index: number, patch: Partial<DoctorFormValues['schedule'][number]>) {
    form.setData(
      'schedule',
      form.data.schedule.map((slot, i) => (i === index ? { ...slot, ...patch } : slot)),
    );
  }

  /** Nilai persen bisa berpecahan (32.5); rupiah tidak. Saat pindah ke rupiah, buang pecahannya. */
  const wholeRupiah = (value: string): string => digitsOnly(value.split('.')[0] ?? '');

  const valueHelp =
    form.data.fee_mode === 'percentage' ? 'Dalam persen, contoh 30 atau 32.5.' : 'Rupiah bulat.';

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title={editing ? 'Ubah dokter' : 'Tambah dokter'} />
        <h1>{editing ? 'Ubah dokter' : 'Tambah dokter'}</h1>

        <form onSubmit={submit} noValidate className="form-grid">
          <section className="form-grid form-wide" aria-label="Data dokter">
            <TextField
              field="name"
              label="Nama dokter"
              value={form.data.name}
              onChange={(event) => {
                form.setData('name', event.target.value);
              }}
              error={error('name')}
            />
            <TextField
              field="sip_no"
              label="Nomor SIP"
              value={form.data.sip_no}
              onChange={(event) => {
                form.setData('sip_no', event.target.value);
              }}
              error={error('sip_no')}
            />
            <TextField
              field="specialty"
              label="Spesialisasi"
              value={form.data.specialty}
              onChange={(event) => {
                form.setData('specialty', event.target.value);
              }}
              error={error('specialty')}
            />
            <Select
              field="branch_id"
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
              field="user_id"
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

          <fieldset className="form-grid">
            <legend className="text-lg font-semibold">Skema jasa dokter</legend>
            <Select
              field="fee_mode"
              label="Cara hitung bawaan"
              options={feeModes}
              value={form.data.fee_mode}
              onChange={(event) => {
                const mode = event.target.value === 'fixed' ? 'fixed' : 'percentage';
                form.setData({
                  ...form.data,
                  fee_mode: mode,
                  fee_value:
                    mode === 'fixed' ? wholeRupiah(form.data.fee_value) : form.data.fee_value,
                });
              }}
              error={error('fee_mode')}
            />
            {form.data.fee_mode === 'fixed' ? (
              <MoneyField
                field="fee_value"
                label="Nilai jasa bawaan"
                value={form.data.fee_value}
                onValueChange={(digits) => {
                  form.setData('fee_value', digits);
                }}
                error={error('fee_value')}
              />
            ) : (
              <TextField
                field="fee_value"
                label="Nilai jasa bawaan"
                inputMode="decimal"
                value={form.data.fee_value}
                onChange={(event) => {
                  form.setData('fee_value', event.target.value);
                }}
                error={error('fee_value')}
              />
            )}
            <p className="text-muted text-sm">{valueHelp}</p>

            <h2>Pengecualian per tindakan</h2>
            {form.data.fee_overrides.map((row, index) => (
              <div key={index} className="card flex flex-col gap-3 p-3">
                <TextField
                  field={`fee_overrides.${String(index)}.code`}
                  label={`Kode tindakan ${String(index + 1)}`}
                  value={row.code}
                  onChange={(event) => {
                    patchOverride(index, { code: event.target.value });
                  }}
                  error={error(`fee_overrides.${String(index)}.code`)}
                />
                <Select
                  field={`fee_overrides.${String(index)}.mode`}
                  label={`Cara hitung ${String(index + 1)}`}
                  options={feeModes}
                  value={row.mode}
                  onChange={(event) => {
                    const mode = event.target.value === 'fixed' ? 'fixed' : 'percentage';
                    patchOverride(index, {
                      mode,
                      ...(mode === 'fixed' ? { value: wholeRupiah(row.value) } : {}),
                    });
                  }}
                />
                {row.mode === 'fixed' ? (
                  <MoneyField
                    field={`fee_overrides.${String(index)}.value`}
                    label={`Nilai jasa ${String(index + 1)}`}
                    value={row.value}
                    onValueChange={(digits) => {
                      patchOverride(index, { value: digits });
                    }}
                    error={error(`fee_overrides.${String(index)}.value`)}
                  />
                ) : (
                  <TextField
                    field={`fee_overrides.${String(index)}.value`}
                    label={`Nilai jasa ${String(index + 1)}`}
                    inputMode="decimal"
                    value={row.value}
                    onChange={(event) => {
                      patchOverride(index, { value: event.target.value });
                    }}
                    error={error(`fee_overrides.${String(index)}.value`)}
                  />
                )}
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

            <h2>Jasa bertingkat (opsional)</h2>
            <p className="text-muted text-sm">
              Persentase tiap tingkat berlaku untuk bagian produksi tindakan bulanan di dalam
              batasnya. Tindakan yang punya pengecualian di atas tidak ikut bertingkat. Bila diisi,
              tingkat ini menggantikan nilai bawaan untuk tindakan tanpa pengecualian. Tingkat
              terakhir tanpa batas.
            </p>
            {error('fee_tiers') ? (
              <p role="alert" className="text-danger-600 text-sm">
                {error('fee_tiers')}
              </p>
            ) : null}
            {form.data.fee_tiers.map((tier, index) => {
              const last = index === form.data.fee_tiers.length - 1;
              return (
                <div key={index} className="card flex flex-wrap items-end gap-3 p-3">
                  <MoneyField
                    field={`fee_tiers.${String(index)}.up_to`}
                    label={
                      last
                        ? `Batas tingkat ${String(index + 1)} (tanpa batas)`
                        : `Sampai produksi ${String(index + 1)}`
                    }
                    disabled={last}
                    value={last ? '' : tier.up_to}
                    onValueChange={(digits) => {
                      patchTier(index, { up_to: digits });
                    }}
                    error={error(`fee_tiers.${String(index)}.up_to`)}
                  />
                  <TextField
                    field={`fee_tiers.${String(index)}.rate`}
                    label={`Persen jasa ${String(index + 1)}`}
                    inputMode="decimal"
                    value={tier.rate}
                    onChange={(event) => {
                      patchTier(index, { rate: event.target.value });
                    }}
                    error={error(`fee_tiers.${String(index)}.rate`)}
                  />
                  <Button
                    variant="secondary"
                    onClick={() => {
                      form.setData(
                        'fee_tiers',
                        form.data.fee_tiers.filter((_, i) => i !== index),
                      );
                    }}
                  >
                    {`Hapus tingkat ${String(index + 1)}`}
                  </Button>
                </div>
              );
            })}
            <div>
              <Button
                variant="secondary"
                onClick={() => {
                  form.setData('fee_tiers', [...form.data.fee_tiers, { up_to: '', rate: '0' }]);
                }}
              >
                Tambah tingkat
              </Button>
            </div>

            <h2>Beban biaya lab</h2>
            <TextField
              field="fee_lab_share"
              label="Bagian biaya lab yang dipotong dari jasa (%)"
              inputMode="decimal"
              value={form.data.fee_lab_share}
              onChange={(event) => {
                form.setData('fee_lab_share', event.target.value);
              }}
              error={error('fee_lab_share')}
            />
            <p className="text-muted text-sm">
              0 berarti biaya lab ditanggung klinik; 100 berarti seluruh biaya lab pesanan dokter
              ini dipotong dari jasanya pada bulan pekerjaan lab diterima.
            </p>
          </fieldset>

          <fieldset className="form-grid">
            <legend className="text-lg font-semibold">Jadwal praktik mingguan</legend>
            {form.data.schedule.map((slot, index) => (
              <div key={index} className="card flex flex-wrap items-end gap-3 p-3">
                <Select
                  field={`schedule.${String(index)}.weekday`}
                  label={`Hari ${String(index + 1)}`}
                  options={WEEKDAYS.map((w) => ({ value: w.day, label: w.label }))}
                  value={String(slot.weekday)}
                  onChange={(event) => {
                    patchSlot(index, { weekday: Number(event.target.value) });
                  }}
                />
                <TimePicker
                  field={`schedule.${String(index)}.start`}
                  label={`Mulai ${String(index + 1)}`}
                  step={15}
                  value={slot.start}
                  onChange={(value) => {
                    patchSlot(index, { start: value });
                  }}
                  error={error(`schedule.${String(index)}.start`)}
                />
                <TimePicker
                  field={`schedule.${String(index)}.end`}
                  label={`Selesai ${String(index + 1)}`}
                  step={15}
                  value={slot.end}
                  onChange={(value) => {
                    patchSlot(index, { end: value });
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
