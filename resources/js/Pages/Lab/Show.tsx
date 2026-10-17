import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { DatePicker } from '@/components/ui/DatePicker';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { cancelSchema, LAB_TONE, receiveSchema, sendSchema } from '@/features/lab/schemas';
import { formatDate, formatRupiah } from '@/lib/format';

interface Props {
  order: {
    id: string;
    order_no: string;
    patient_id: string;
    patient_name: string;
    medical_record_no: string;
    doctor_name: string;
    laboratory_name: string;
    work_type_label: string;
    status: string;
    status_label: string;
    ordered_date: string;
    due_date: string | null;
    sent_date: string | null;
    received_date: string | null;
    lab_cost_amount: number;
    patient_charge_amount: number;
    billed: boolean;
    tooth_notes: string | null;
    description: string | null;
    shade: string | null;
    cancel_note: string | null;
  };
  open_encounters: SelectOption[];
  can_clinical: boolean;
  can: { update: boolean; charge: boolean };
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col sm:flex-row sm:gap-3">
      <dt className="text-muted w-48 shrink-0 text-sm">{label}</dt>
      <dd className="text-sm">{children}</dd>
    </div>
  );
}

/** P2-06: rincian pesanan lab, perubahan status, dan penagihan ke pasien. */
export default function Show({ order, open_encounters, can_clinical, can }: Props) {
  const base = `/lab-orders/${order.id}`;
  const send = useForm({ date: '' });
  const receive = useForm({ date: '', lab_cost_amount: String(order.lab_cost_amount) });
  const cancel = useForm({ reason: '' });
  const charge = useForm({ encounter_id: '' });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const err = (key: string, server?: string) => errors[key] ?? server;

  function guarded(
    schema: {
      safeParse: (v: unknown) => {
        success: boolean;
        error?: { issues: { path: PropertyKey[]; message: string }[] };
      };
    },
    form: {
      data: Record<string, string>;
      post: (url: string, o?: { preserveScroll: boolean }) => void;
    },
    action: string,
  ) {
    return (event: SyntheticEvent<HTMLFormElement>) => {
      event.preventDefault();
      const result = schema.safeParse(form.data);
      if (!result.success) {
        const next: Record<string, string> = {};
        for (const issue of result.error?.issues ?? []) {
          next[String(issue.path[0])] ??= issue.message;
        }
        setErrors(next);
        return;
      }
      setErrors({});
      form.post(`${base}/${action}`, { preserveScroll: true });
    };
  }

  return (
    <AppLayout>
      <main className="flex flex-col gap-4 p-3">
        <PageHead title={`Pesanan lab ${order.order_no}`} />
        <PageHeader
          title={`Pesanan lab ${order.order_no}`}
          back={{ href: '/lab-orders', label: 'pesanan lab' }}
          description={`${order.patient_name} (${order.medical_record_no})`}
        />
        <dl className="flex flex-col gap-1">
          <Row label="Status">
            <Badge tone={LAB_TONE[order.status] ?? 'neutral'}>{order.status_label}</Badge>
            {order.billed ? ' Sudah ditagihkan ke pasien' : ''}
          </Row>
          <Row label="Pekerjaan">{order.work_type_label}</Row>
          <Row label="Laboratorium">{order.laboratory_name}</Row>
          <Row label="Dokter">{order.doctor_name}</Row>
          {can_clinical ? (
            <>
              <Row label="Gigi">{order.tooth_notes ?? '-'}</Row>
              <Row label="Uraian">{order.description ?? '-'}</Row>
              <Row label="Warna gigi">{order.shade ?? '-'}</Row>
            </>
          ) : null}
          <Row label="Tanggal pesan">{formatDate(order.ordered_date)}</Row>
          <Row label="Target selesai">{order.due_date ? formatDate(order.due_date) : '-'}</Row>
          <Row label="Tanggal kirim">{order.sent_date ? formatDate(order.sent_date) : '-'}</Row>
          <Row label="Tanggal terima">
            {order.received_date ? formatDate(order.received_date) : '-'}
          </Row>
          <Row label="Biaya lab">{formatRupiah(order.lab_cost_amount)}</Row>
          <Row label="Harga ke pasien">{formatRupiah(order.patient_charge_amount)}</Row>
          {order.cancel_note ? <Row label="Alasan batal">{order.cancel_note}</Row> : null}
        </dl>

        {can.update && order.status === 'ordered' ? (
          <form
            onSubmit={guarded(sendSchema, send, 'send')}
            noValidate
            aria-label="Kirim ke lab"
            className="form-grid max-w-md"
          >
            <h2>Kirim ke lab</h2>
            <DatePicker
              label="Tanggal kirim"
              value={send.data.date}
              min={order.ordered_date}
              onChange={(value) => {
                send.setData('date', value);
              }}
              error={err('date', send.errors.date)}
              required
            />
            <div>
              <Button type="submit" loading={send.processing}>
                Tandai terkirim
              </Button>
            </div>
          </form>
        ) : null}

        {can.update && order.status === 'sent' ? (
          <form
            onSubmit={guarded(receiveSchema, receive, 'receive')}
            noValidate
            aria-label="Terima dari lab"
            className="form-grid max-w-md"
          >
            <h2>Terima dari lab</h2>
            <DatePicker
              label="Tanggal terima"
              value={receive.data.date}
              min={order.sent_date ?? order.ordered_date}
              onChange={(value) => {
                receive.setData('date', value);
              }}
              error={err('date', receive.errors.date)}
              required
            />
            <TextField
              label="Biaya lab aktual (Rp)"
              inputMode="numeric"
              value={receive.data.lab_cost_amount}
              onChange={(event) => {
                receive.setData('lab_cost_amount', event.target.value);
              }}
              error={err('lab_cost_amount', receive.errors.lab_cost_amount)}
              required
            />
            <div>
              <Button type="submit" loading={receive.processing}>
                Catat penerimaan
              </Button>
            </div>
          </form>
        ) : null}

        {can.charge && order.status === 'received' && !order.billed ? (
          <form
            onSubmit={(event) => {
              event.preventDefault();
              if (charge.data.encounter_id === '') {
                setErrors({ encounter_id: 'Pilih kunjungan yang sedang berjalan.' });
                return;
              }
              setErrors({});
              charge.post(`${base}/charge`, { preserveScroll: true });
            }}
            noValidate
            aria-label="Tagihkan ke pasien"
            className="form-grid max-w-md"
          >
            <h2>Tagihkan ke pasien</h2>
            <p className="text-muted text-sm">
              Harga {formatRupiah(order.patient_charge_amount)} ditambahkan sebagai item tagihan
              pada kunjungan pasien yang sedang berjalan.
            </p>
            <Select
              label="Kunjungan"
              placeholder={
                open_encounters.length === 0 ? 'Tidak ada kunjungan berjalan' : 'Pilih kunjungan'
              }
              options={open_encounters}
              value={charge.data.encounter_id}
              onChange={(event) => {
                charge.setData('encounter_id', event.target.value);
              }}
              error={err('encounter_id', charge.errors.encounter_id)}
              required
            />
            <div>
              <Button
                type="submit"
                loading={charge.processing}
                disabled={open_encounters.length === 0}
              >
                Tambahkan ke tagihan
              </Button>
            </div>
          </form>
        ) : null}

        {can.update && (order.status === 'ordered' || order.status === 'sent') ? (
          <form
            onSubmit={guarded(cancelSchema, cancel, 'cancel')}
            noValidate
            aria-label="Batalkan pesanan"
            className="form-grid max-w-md"
          >
            <h2>Batalkan pesanan</h2>
            <TextField
              label="Alasan pembatalan"
              value={cancel.data.reason}
              onChange={(event) => {
                cancel.setData('reason', event.target.value);
              }}
              error={err('reason', cancel.errors.reason)}
              required
            />
            <div>
              <Button type="submit" variant="danger" loading={cancel.processing}>
                Batalkan pesanan
              </Button>
            </div>
          </form>
        ) : null}
        <button
          type="button"
          className="btn btn-secondary w-fit"
          onClick={() => {
            router.visit(`/patients/${order.patient_id}`);
          }}
        >
          Buka halaman pasien
        </button>
      </main>
    </AppLayout>
  );
}
