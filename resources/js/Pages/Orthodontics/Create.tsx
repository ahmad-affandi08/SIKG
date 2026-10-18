import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { DatePicker } from '@/components/ui/DatePicker';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextArea } from '@/components/ui/TextArea';
import { MoneyField } from '@/components/ui/MoneyField';
import { TextField } from '@/components/ui/TextField';
import { caseSchema, previewInstallments } from '@/features/orthodontics/schemas';
import type { CaseValues } from '@/features/orthodontics/schemas';
import { formatRupiah } from '@/lib/format';

interface Props {
  patient: { id: string; name: string; medical_record_no: string };
  doctors: SelectOption[];
  appliances: SelectOption[];
  today: string;
}

/** P2-04: membuat paket perawatan ortodonti beserta jadwal cicilannya. */
export default function Create({ patient, doctors, appliances, today }: Props) {
  const form = useForm<CaseValues & { patient_id: string }>({
    patient_id: patient.id,
    doctor_id: '',
    appliance: '',
    title: '',
    plan_notes: '',
    total_fee_amount: '',
    down_payment_amount: '',
    installment_count: '12',
    start_date: today,
    expected_months: '18',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  function submit(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = caseSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.post('/orthodontics');
  }

  const error = (key: keyof CaseValues): string | undefined => errors[key] ?? form.errors[key];
  const text = (key: keyof CaseValues, label: string, numeric = false) => (
    <TextField
      label={label}
      {...(numeric ? { inputMode: 'numeric' as const } : {})}
      value={form.data[key]}
      onChange={(event) => {
        form.setData(key, event.target.value);
      }}
      error={error(key)}
    />
  );
  const money = (key: 'total_fee_amount' | 'down_payment_amount', label: string) => (
    <MoneyField
      label={label}
      value={form.data[key]}
      onValueChange={(digits) => {
        form.setData(key, digits);
      }}
      error={error(key)}
    />
  );
  const preview = previewInstallments(
    Number(form.data.total_fee_amount) || 0,
    Number(form.data.installment_count) || 0,
    Number(form.data.down_payment_amount) || 0,
  );

  return (
    <AppLayout>
      <main className="flex max-w-xl flex-col gap-3 p-3">
        <PageHead title="Paket ortodonti baru" />
        <PageHeader
          title="Paket ortodonti baru"
          back={{ href: `/patients/${patient.id}`, label: 'rekam pasien' }}
          description={`${patient.name} (${patient.medical_record_no})`}
        />
        <form onSubmit={submit} noValidate aria-label="Paket ortodonti baru" className="form-grid">
          <Select
            label="Dokter"
            placeholder="Pilih dokter"
            options={doctors}
            value={form.data.doctor_id}
            onChange={(event) => {
              form.setData('doctor_id', event.target.value);
            }}
            error={error('doctor_id')}
          />
          <Select
            label="Jenis alat"
            placeholder="Pilih jenis alat"
            options={appliances}
            value={form.data.appliance}
            onChange={(event) => {
              form.setData('appliance', event.target.value);
            }}
            error={error('appliance')}
          />
          {text('title', 'Nama paket')}
          <TextArea
            label="Rencana perawatan"
            value={form.data.plan_notes}
            onChange={(event) => {
              form.setData('plan_notes', event.target.value);
            }}
            error={error('plan_notes')}
          />
          {money('total_fee_amount', 'Biaya paket')}
          {money('down_payment_amount', 'Uang muka (kosongkan bila tidak ada)')}
          {text('installment_count', 'Jumlah cicilan (termasuk uang muka)', true)}
          <DatePicker
            label="Tanggal mulai"
            value={form.data.start_date}
            onChange={(value) => {
              form.setData('start_date', value);
            }}
            error={error('start_date')}
            clearable={false}
          />
          {text('expected_months', 'Perkiraan lama perawatan (bulan)', true)}
          {preview.length > 0 ? (
            <p className="text-muted text-sm">
              Pratinjau cicilan:{' '}
              {preview
                .map((amount, index) => `${String(index + 1)}. ${formatRupiah(amount)}`)
                .join(', ')}
              .
            </p>
          ) : null}
          <div>
            <Button type="submit" loading={form.processing}>
              Buat paket
            </Button>
          </div>
        </form>
      </main>
    </AppLayout>
  );
}
