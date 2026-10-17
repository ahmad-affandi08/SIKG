import { router, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import {
  approveSchema,
  CLAIM_TONE,
  receiveSchema,
  rejectSchema,
} from '@/features/insurance/schemas';
import { formatDateTime, formatRupiah } from '@/lib/format';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface Props {
  claim: {
    id: string;
    claim_no: string;
    invoice_id: string;
    invoice_number: string | null;
    patient_name: string;
    medical_record_no: string;
    insurer_name: string;
    policy_no: string | null;
    status: string;
    status_label: string;
    claimed_amount: number;
    approved_amount: number | null;
    paid_amount: number;
    outstanding_amount: number;
    notes: string | null;
    decision_note: string | null;
    submitted_at: string | null;
    decided_at: string | null;
    paid_at: string | null;
  };
  receipts: {
    id: string;
    amount: number;
    reference_no: string | null;
    received_at: string;
    method_name: string;
  }[];
  methods: SelectOption[];
  can: { submit: boolean; cancel: boolean; decide: boolean; receive: boolean };
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col sm:flex-row sm:gap-3">
      <dt className="text-muted w-48 shrink-0 text-sm">{label}</dt>
      <dd className="text-sm">{children}</dd>
    </div>
  );
}

function collect(issues: { path: PropertyKey[]; message: string }[]): Record<string, string> {
  const next: Record<string, string> = {};
  for (const issue of issues) {
    next[String(issue.path[0])] ??= issue.message;
  }
  return next;
}

/** P2-02: rincian klaim, keputusan penjamin, dan pencatatan uang yang diterima. */
export default function Show({ claim, receipts, methods, can }: Props) {
  const timezone = useClinicTimezone();
  const remaining = (claim.approved_amount ?? claim.claimed_amount) - claim.paid_amount;
  const approve = useForm({ approved_amount: String(claim.claimed_amount), note: '' });
  const reject = useForm({ reason: '' });
  const receive = useForm({
    payment_method_id: '',
    amount: String(remaining),
    reference_no: '',
    idempotency_key: '',
  });
  const keyRef = useRef(crypto.randomUUID());
  const [errors, setErrors] = useState<Record<string, string>>({});
  const base = `/insurance/claims/${claim.id}`;
  const err = (key: string, server?: string) => errors[key] ?? server;

  function post(action: string) {
    router.post(`${base}/${action}`, {}, { preserveScroll: true });
  }

  function submitApprove(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = approveSchema(claim.claimed_amount).safeParse(approve.data);
    if (!result.success) {
      setErrors(collect(result.error.issues));
      return;
    }
    setErrors({});
    approve.post(`${base}/approve`, { preserveScroll: true });
  }

  function submitReject(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = rejectSchema.safeParse(reject.data);
    if (!result.success) {
      setErrors(collect(result.error.issues));
      return;
    }
    setErrors({});
    reject.post(`${base}/reject`, { preserveScroll: true });
  }

  function submitReceive(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = receiveSchema(remaining).safeParse(receive.data);
    if (!result.success) {
      setErrors(collect(result.error.issues));
      return;
    }
    setErrors({});
    receive.transform((data) => ({ ...data, idempotency_key: keyRef.current }));
    receive.post(`${base}/receive`, {
      preserveScroll: true,
      onSuccess: () => {
        keyRef.current = crypto.randomUUID();
      },
    });
  }

  return (
    <AppLayout>
      <main className="flex flex-col gap-4 p-3">
        <PageHead title={`Klaim ${claim.claim_no}`} />
        <PageHeader
          title={`Klaim ${claim.claim_no}`}
          back={{ href: '/insurance/claims', label: 'klaim penjamin' }}
          description={`${claim.patient_name} (${claim.medical_record_no})`}
        />

        <dl className="flex flex-col gap-1">
          <Row label="Status">
            <Badge tone={CLAIM_TONE[claim.status] ?? 'neutral'}>{claim.status_label}</Badge>
          </Row>
          <Row label="Penjamin">{claim.insurer_name}</Row>
          <Row label="Nomor polis">{claim.policy_no ?? '-'}</Row>
          <Row label="Tagihan">{claim.invoice_number ?? '-'}</Row>
          <Row label="Diklaim">{formatRupiah(claim.claimed_amount)}</Row>
          <Row label="Disetujui">
            {claim.approved_amount === null ? '-' : formatRupiah(claim.approved_amount)}
          </Row>
          <Row label="Diterima">{formatRupiah(claim.paid_amount)}</Row>
          <Row label="Piutang penjamin">{formatRupiah(claim.outstanding_amount)}</Row>
          <Row label="Diajukan">
            {claim.submitted_at ? formatDateTime(claim.submitted_at, timezone) : '-'}
          </Row>
          <Row label="Keputusan">
            {claim.decided_at
              ? `${formatDateTime(claim.decided_at, timezone)}${claim.decision_note ? ` - ${claim.decision_note}` : ''}`
              : '-'}
          </Row>
          {claim.notes ? <Row label="Catatan">{claim.notes}</Row> : null}
        </dl>

        {can.submit || can.cancel ? (
          <div className="flex flex-wrap gap-2">
            {can.submit ? (
              <ConfirmAction
                label="Ajukan ke penjamin"
                message="Klaim dikunci dan dianggap sudah dikirim ke penjamin. Jumlah klaim tidak dapat diubah lagi."
                confirmLabel="Ya, ajukan"
                variant="primary"
                onConfirm={() => {
                  post('submit');
                }}
              />
            ) : null}
            {can.cancel ? (
              <ConfirmAction
                label="Batalkan draft"
                message="Klaim draft dibatalkan dan tidak dihitung lagi terhadap plafon."
                confirmLabel="Ya, batalkan"
                onConfirm={() => {
                  post('cancel');
                }}
              />
            ) : null}
          </div>
        ) : null}

        {can.decide ? (
          <div className="grid gap-4 lg:grid-cols-2">
            <form
              onSubmit={submitApprove}
              noValidate
              aria-label="Setujui klaim"
              className="form-grid"
            >
              <h2>Penjamin menyetujui</h2>
              <TextField
                label="Jumlah disetujui (Rp)"
                inputMode="numeric"
                value={approve.data.approved_amount}
                onChange={(event) => {
                  approve.setData('approved_amount', event.target.value);
                }}
                error={err('approved_amount', approve.errors.approved_amount)}
              />
              <TextField
                label="Catatan"
                value={approve.data.note}
                onChange={(event) => {
                  approve.setData('note', event.target.value);
                }}
                error={err('note', approve.errors.note)}
              />
              <div>
                <Button type="submit" loading={approve.processing}>
                  Catat persetujuan
                </Button>
              </div>
            </form>
            <form onSubmit={submitReject} noValidate aria-label="Tolak klaim" className="form-grid">
              <h2>Penjamin menolak</h2>
              <TextField
                label="Alasan penolakan"
                value={reject.data.reason}
                onChange={(event) => {
                  reject.setData('reason', event.target.value);
                }}
                error={err('reason', reject.errors.reason)}
              />
              <div>
                <Button type="submit" variant="danger" loading={reject.processing}>
                  Catat penolakan
                </Button>
              </div>
            </form>
          </div>
        ) : null}

        {can.receive ? (
          <form
            onSubmit={submitReceive}
            noValidate
            aria-label="Terima pembayaran penjamin"
            className="form-grid max-w-xl"
          >
            <h2>Terima pembayaran dari penjamin</h2>
            <p className="text-muted text-sm">
              Sisa yang disetujui: {formatRupiah(remaining)}. Pembayaran juga tercatat pada tagihan.
            </p>
            <Select
              label="Metode pembayaran"
              placeholder="Pilih metode"
              options={methods}
              value={receive.data.payment_method_id}
              onChange={(event) => {
                receive.setData('payment_method_id', event.target.value);
              }}
              error={err('payment_method_id', receive.errors.payment_method_id)}
            />
            <TextField
              label="Jumlah diterima (Rp)"
              inputMode="numeric"
              value={receive.data.amount}
              onChange={(event) => {
                receive.setData('amount', event.target.value);
              }}
              error={err('amount', receive.errors.amount)}
            />
            <TextField
              label="Nomor referensi"
              value={receive.data.reference_no}
              onChange={(event) => {
                receive.setData('reference_no', event.target.value);
              }}
              error={err('reference_no', receive.errors.reference_no)}
            />
            <div>
              <Button type="submit" loading={receive.processing}>
                Catat pembayaran
              </Button>
            </div>
          </form>
        ) : null}

        <section aria-label="Pembayaran diterima" className="flex flex-col gap-2">
          <h2>Pembayaran dari penjamin</h2>
          {receipts.length === 0 ? (
            <p className="text-muted text-sm">Belum ada pembayaran.</p>
          ) : (
            <table className="w-full text-left text-sm">
              <thead>
                <tr>
                  <th scope="col">Tanggal</th>
                  <th scope="col">Metode</th>
                  <th scope="col">Referensi</th>
                  <th scope="col" className="text-right">
                    Jumlah
                  </th>
                </tr>
              </thead>
              <tbody>
                {receipts.map((r) => (
                  <tr key={r.id}>
                    <td>{formatDateTime(r.received_at, timezone)}</td>
                    <td>{r.method_name}</td>
                    <td>{r.reference_no ?? '-'}</td>
                    <td className="text-right">{formatRupiah(r.amount)}</td>
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
