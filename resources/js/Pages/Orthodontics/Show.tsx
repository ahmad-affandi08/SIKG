import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { DatePicker } from '@/components/ui/DatePicker';
import { EmptyState } from '@/components/ui/EmptyState';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextArea } from '@/components/ui/TextArea';
import { TextField } from '@/components/ui/TextField';
import { controlSchema } from '@/features/orthodontics/schemas';
import type { ControlValues } from '@/features/orthodontics/schemas';
import { formatDate, formatRupiah } from '@/lib/format';

interface Installment {
  id: string;
  seq: number;
  due_date: string;
  amount: number;
  status: string;
  status_label: string;
  invoice_id: string | null;
  invoice_number: string | null;
  invoice_status_label: string | null;
  overdue: boolean;
}

interface Control {
  id: string;
  control_date: string;
  wire_or_tray: string | null;
  adjustments: string | null;
  notes: string | null;
  next_control_date: string | null;
  photos: { id: string; kind_label: string; caption: string | null }[];
}

interface Props {
  case: {
    id: string;
    patient_id: string;
    patient_name: string;
    medical_record_no: string;
    doctor_name: string;
    title: string;
    appliance_label: string;
    status: string;
    status_label: string;
    start_date: string;
    next_control_date: string | null;
    control_overdue: boolean;
    unbilled_amount: number;
    total_fee_amount: number;
    expected_months: number;
    plan_notes: string | null;
    ended_note: string | null;
  };
  installments: Installment[];
  controls: Control[];
  open_encounters: SelectOption[];
  can_clinical: boolean;
  can: { manage: boolean; control: boolean; charge: boolean };
}

function ChargeInstallment({
  installment,
  encounters,
}: {
  installment: Installment;
  encounters: SelectOption[];
}) {
  const form = useForm({ encounter_id: encounters[0]?.value ?? '' });
  if (encounters.length === 0) {
    return <span className="text-muted">Tidak ada kunjungan berjalan</span>;
  }
  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        form.post(`/orthodontics/payment-plan/${installment.id}/charge`, { preserveScroll: true });
      }}
      aria-label={`Tagihkan cicilan ${String(installment.seq)}`}
      className="flex items-end gap-2"
    >
      <Select
        label="Kunjungan"
        options={encounters}
        value={form.data.encounter_id}
        onChange={(event) => {
          form.setData('encounter_id', event.target.value);
        }}
      />
      <Button type="submit" variant="secondary" loading={form.processing}>
        Tagihkan
      </Button>
    </form>
  );
}

/** P2-04: rincian paket ortodonti: cicilan, kontrol bulanan, dan foto progres. */
export default function Show({
  case: c,
  installments,
  controls,
  open_encounters: encounters,
  can_clinical: canClinical,
  can,
}: Props) {
  const active = c.status === 'active';
  const form = useForm<ControlValues>({
    control_date: '',
    encounter_id: '',
    wire_or_tray: '',
    adjustments: '',
    notes: '',
    next_control_date: '',
  });
  const stop = useForm({ reason: '' });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const base = `/orthodontics/cases/${c.id}`;

  function submitControl(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = controlSchema.safeParse(form.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    form.post(`${base}/controls`, {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
      },
    });
  }

  const error = (key: keyof ControlValues): string | undefined => errors[key] ?? form.errors[key];

  return (
    <AppLayout>
      <main className="flex flex-col gap-4 p-3">
        <PageHead title={c.title} />
        <PageHeader
          title={c.title}
          back={{ href: '/orthodontics', label: 'ortodonti' }}
          description={`${c.patient_name} (${c.medical_record_no}) - ${c.appliance_label} - ${c.doctor_name}`}
        />

        <dl className="flex flex-col gap-1 text-sm">
          <div className="flex gap-3">
            <dt className="text-muted w-48">Status</dt>
            <dd>
              <Badge tone={active ? 'warning' : 'neutral'}>{c.status_label}</Badge>
            </dd>
          </div>
          <div className="flex gap-3">
            <dt className="text-muted w-48">Mulai</dt>
            <dd>
              {formatDate(c.start_date)} (perkiraan {c.expected_months} bulan)
            </dd>
          </div>
          <div className="flex gap-3">
            <dt className="text-muted w-48">Biaya paket</dt>
            <dd>{formatRupiah(c.total_fee_amount)}</dd>
          </div>
          <div className="flex gap-3">
            <dt className="text-muted w-48">Cicilan belum ditagih</dt>
            <dd>{formatRupiah(c.unbilled_amount)}</dd>
          </div>
          {active ? (
            <div className="flex gap-3">
              <dt className="text-muted w-48">Kontrol berikutnya</dt>
              <dd>
                {c.next_control_date ? formatDate(c.next_control_date) : '-'}
                {c.control_overdue ? <span className="text-danger-600"> (terlambat)</span> : null}
              </dd>
            </div>
          ) : null}
          {c.ended_note ? (
            <div className="flex gap-3">
              <dt className="text-muted w-48">Catatan akhir</dt>
              <dd>{c.ended_note}</dd>
            </div>
          ) : null}
          {c.plan_notes ? (
            <div className="flex gap-3">
              <dt className="text-muted w-48">Rencana</dt>
              <dd>{c.plan_notes}</dd>
            </div>
          ) : null}
        </dl>
        <p className="text-sm">
          <Link href={`/patients/${c.patient_id}`} className="underline">
            Buka rekam pasien
          </Link>
        </p>

        <section aria-label="Cicilan" className="flex flex-col gap-2">
          <h2>Jadwal cicilan</h2>
          <table className="w-full text-left text-sm">
            <caption className="sr-only">Jadwal cicilan paket</caption>
            <thead>
              <tr>
                <th scope="col">Ke</th>
                <th scope="col">Jatuh tempo</th>
                <th scope="col" className="text-right">
                  Jumlah
                </th>
                <th scope="col">Status</th>
                <th scope="col">Tagihan</th>
                {can.charge && active ? <th scope="col">Aksi</th> : null}
              </tr>
            </thead>
            <tbody>
              {installments.map((i) => (
                <tr key={i.id}>
                  <td>{i.seq}</td>
                  <td>
                    {formatDate(i.due_date)}
                    {i.overdue ? <span className="text-danger-600"> (lewat)</span> : null}
                  </td>
                  <td className="text-right">{formatRupiah(i.amount)}</td>
                  <td>{i.status_label}</td>
                  <td>
                    {i.invoice_id ? (
                      <Link href={`/invoices/${i.invoice_id}`} className="underline">
                        {i.invoice_number ?? 'Draft'}{' '}
                        {i.invoice_status_label ? `(${i.invoice_status_label})` : ''}
                      </Link>
                    ) : (
                      '-'
                    )}
                  </td>
                  {can.charge && active ? (
                    <td>
                      {i.status === 'scheduled' ? (
                        <ChargeInstallment installment={i} encounters={encounters} />
                      ) : null}
                    </td>
                  ) : null}
                </tr>
              ))}
            </tbody>
          </table>
        </section>

        {canClinical ? (
          <section aria-label="Kontrol" className="flex flex-col gap-3">
            <h2>Kontrol bulanan</h2>
            {can.control && active ? (
              <form
                onSubmit={submitControl}
                noValidate
                aria-label="Catat kontrol"
                className="form-grid max-w-xl"
              >
                <DatePicker
                  required
                  label="Tanggal kontrol"
                  value={form.data.control_date}
                  onChange={(value) => {
                    form.setData('control_date', value);
                  }}
                  error={error('control_date')}
                  clearable={false}
                />
                <TextField
                  label="Kawat, komponen, atau nomor tray"
                  value={form.data.wire_or_tray}
                  onChange={(event) => {
                    form.setData('wire_or_tray', event.target.value);
                  }}
                  error={error('wire_or_tray')}
                />
                <TextArea
                  label="Penyesuaian yang dilakukan"
                  value={form.data.adjustments}
                  onChange={(event) => {
                    form.setData('adjustments', event.target.value);
                  }}
                  error={error('adjustments')}
                />
                <TextArea
                  label="Catatan"
                  value={form.data.notes}
                  onChange={(event) => {
                    form.setData('notes', event.target.value);
                  }}
                  error={error('notes')}
                />
                <Select
                  label="Kunjungan (untuk foto progres)"
                  placeholder="Tanpa kunjungan"
                  options={encounters}
                  value={form.data.encounter_id}
                  onChange={(event) => {
                    form.setData('encounter_id', event.target.value);
                  }}
                  error={error('encounter_id')}
                />
                <DatePicker
                  label="Kontrol berikutnya"
                  value={form.data.next_control_date}
                  onChange={(value) => {
                    form.setData('next_control_date', value);
                  }}
                  error={error('next_control_date')}
                />
                <div>
                  <Button type="submit" loading={form.processing}>
                    Simpan kontrol
                  </Button>
                </div>
              </form>
            ) : null}
            {controls.length === 0 ? (
              <EmptyState kind="tooth" size="compact" title="Belum ada catatan kontrol" />
            ) : (
              <ul className="flex flex-col gap-2">
                {controls.map((control) => (
                  <li key={control.id} className="pane p-2 text-sm">
                    <div className="font-semibold">{formatDate(control.control_date)}</div>
                    {control.wire_or_tray ? (
                      <div>Kawat atau tray: {control.wire_or_tray}</div>
                    ) : null}
                    {control.adjustments ? <div>Penyesuaian: {control.adjustments}</div> : null}
                    {control.notes ? <div>Catatan: {control.notes}</div> : null}
                    {control.next_control_date ? (
                      <div>Kontrol berikutnya: {formatDate(control.next_control_date)}</div>
                    ) : null}
                    {control.photos.length > 0 ? (
                      <div className="mt-1 flex flex-wrap gap-2">
                        {control.photos.map((photo) => (
                          <a
                            key={photo.id}
                            href={`/clinical-files/${photo.id}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="underline"
                          >
                            {photo.kind_label}
                            {photo.caption ? `: ${photo.caption}` : ''}
                          </a>
                        ))}
                      </div>
                    ) : null}
                  </li>
                ))}
              </ul>
            )}
          </section>
        ) : (
          <Alert tone="info">
            Catatan kontrol dan foto hanya terlihat oleh petugas yang berhak membuka rekam medis.
          </Alert>
        )}

        {can.manage && active ? (
          <section aria-label="Mengakhiri perawatan" className="flex flex-col gap-2">
            <h2>Mengakhiri perawatan</h2>
            <div className="flex flex-wrap items-end gap-3">
              <ConfirmAction
                label="Selesaikan perawatan"
                message="Paket ditandai selesai dan tidak menerima kontrol baru. Cicilan yang belum ditagihkan tetap dapat ditagihkan."
                confirmLabel="Ya, selesaikan"
                variant="primary"
                onConfirm={() => {
                  router.post(`${base}/complete`, {}, { preserveScroll: true });
                }}
              />
              <form
                onSubmit={(event) => {
                  event.preventDefault();
                  stop.post(`${base}/stop`, { preserveScroll: true });
                }}
                aria-label="Hentikan perawatan"
                className="flex items-end gap-2"
              >
                <TextField
                  required
                  label="Alasan menghentikan"
                  value={stop.data.reason}
                  onChange={(event) => {
                    stop.setData('reason', event.target.value);
                  }}
                  error={stop.errors.reason}
                />
                <Button type="submit" variant="danger" loading={stop.processing}>
                  Hentikan
                </Button>
              </form>
            </div>
          </section>
        ) : null}
      </main>
    </AppLayout>
  );
}
