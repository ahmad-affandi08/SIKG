import { Link, router } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { percentText, STATUS_TONE } from '@/features/billing/schemas';
import type { InvoiceItemRow, InvoiceStatus, PaymentRow } from '@/features/billing/schemas';
import { formatDateTime, formatRupiah } from '@/lib/format';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface Props {
  invoice: {
    id: string;
    number: string | null;
    lifecycle: 'draft' | 'issued' | 'void';
    status: InvoiceStatus;
    status_label: string;
    patient_id: string;
    patient_name: string | null;
    medical_record_no: string | null;
    encounter_id: string;
    subtotal_amount: number;
    discount_amount: number;
    discount_type: 'percent' | 'amount' | null;
    discount_value: number | null;
    discount_reason: string | null;
    total_amount: number;
    paid_amount: number;
    balance_amount: number;
    issued_at: string | null;
    voided_at: string | null;
    void_reason: string | null;
    voided_by_name: string | null;
    editable: boolean;
  };
  items: InvoiceItemRow[];
  payments: PaymentRow[];
  can: { edit: boolean; discount: boolean; pay: boolean; refund: boolean; void: boolean };
}

function discountLabel(type: 'percent' | 'amount' | null, value: number | null): string {
  if (type === null || value === null) {
    return '';
  }
  return type === 'percent' ? ` (${percentText(value)})` : '';
}

export default function Show({ invoice, items, payments, can }: Props) {
  const timezone = useClinicTimezone();
  const voided = invoice.status === 'void';
  const canPay = can.pay && !voided && invoice.status !== 'paid';
  const print = (query: string) => `/invoices/${invoice.id}/print?${query}`;

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title={invoice.number ?? 'Tagihan draft'} />
        <PageHeader
          title={`Tagihan ${invoice.number ?? '(draft)'}`}
          back={{ href: '/invoices', label: 'daftar tagihan' }}
          description={`${invoice.patient_name ?? 'Pasien'} (${invoice.medical_record_no ?? '-'})`}
          actions={
            <>
              {invoice.number !== null ? (
                <>
                  <a
                    href={print('doc=invoice&format=a4')}
                    target="_blank"
                    rel="noreferrer"
                    className="btn btn-secondary"
                  >
                    Cetak invoice A4
                  </a>
                  <a
                    href={print('doc=invoice&format=thermal')}
                    target="_blank"
                    rel="noreferrer"
                    className="btn btn-secondary"
                  >
                    Cetak 80 mm
                  </a>
                  <a href={print('doc=invoice&format=a4&download=1')} className="btn btn-secondary">
                    Unduh PDF
                  </a>
                </>
              ) : null}
              {canPay ? (
                <Link href={`/invoices/${invoice.id}/payments/create`} className="btn btn-primary">
                  Catat pembayaran
                </Link>
              ) : null}
            </>
          }
        />

        <div className="flex flex-wrap items-center gap-3">
          <Badge tone={STATUS_TONE[invoice.status]}>{invoice.status_label}</Badge>
          <Link href={`/encounters/${invoice.encounter_id}`} className="text-brand-700 underline">
            Buka kunjungan
          </Link>
          {invoice.issued_at ? (
            <span className="text-muted">Terbit {formatDateTime(invoice.issued_at, timezone)}</span>
          ) : null}
        </div>

        {voided ? (
          <Alert tone="error">
            Tagihan dibatalkan{invoice.voided_by_name ? ` oleh ${invoice.voided_by_name}` : ''}
            {invoice.voided_at ? ` pada ${formatDateTime(invoice.voided_at, timezone)}` : ''}.
            Alasan: {invoice.void_reason}
          </Alert>
        ) : null}
        {invoice.lifecycle === 'draft' ? (
          <Alert tone="info">
            Draft: item bertambah otomatis selama kunjungan berjalan dan tagihan terbit saat
            kunjungan ditutup.
          </Alert>
        ) : null}

        <section aria-labelledby="item-judul" className="flex flex-col gap-2">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h2 id="item-judul" className="flex-1">
              Item tagihan
            </h2>
            {can.edit && invoice.editable ? (
              <Link href={`/invoices/${invoice.id}/items/create`} className="btn btn-secondary">
                Tambah item
              </Link>
            ) : null}
            {can.discount && invoice.editable ? (
              <Link href={`/invoices/${invoice.id}/discount`} className="btn btn-secondary">
                Diskon tagihan
              </Link>
            ) : null}
            {can.edit && invoice.lifecycle === 'draft' ? (
              <ConfirmAction
                label="Terbitkan tagihan"
                message="Tagihan mendapat nomor invoice dan dapat dibayar. Item klinis tidak bertambah lagi."
                confirmLabel="Ya, terbitkan"
                variant="primary"
                onConfirm={() => {
                  router.post(`/invoices/${invoice.id}/issue`);
                }}
              />
            ) : null}
          </div>
          <table>
            <caption className="sr-only">Item tagihan</caption>
            <thead>
              <tr>
                <th scope="col">Uraian</th>
                <th scope="col" className="text-right">
                  Jml
                </th>
                <th scope="col" className="text-right">
                  Harga
                </th>
                <th scope="col" className="text-right">
                  Diskon
                </th>
                <th scope="col" className="text-right">
                  Total
                </th>
                <th scope="col">
                  <span className="sr-only">Aksi</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {items.length === 0 ? (
                <tr>
                  <td colSpan={6} className="text-muted">
                    Belum ada item.
                  </td>
                </tr>
              ) : null}
              {items.map((item) => (
                <tr key={item.id}>
                  <td>
                    {item.description}
                    {item.discount_reason ? (
                      <span className="text-muted block">Diskon: {item.discount_reason}</span>
                    ) : null}
                  </td>
                  <td className="text-right">{item.quantity}</td>
                  <td className="text-right">{formatRupiah(item.unit_price_amount)}</td>
                  <td className="text-right">
                    {item.discount_amount > 0 ? formatRupiah(item.discount_amount) : '-'}
                    {discountLabel(item.discount_type, item.discount_value)}
                  </td>
                  <td className="text-right">{formatRupiah(item.total_amount)}</td>
                  <td>
                    <span className="flex flex-wrap justify-end gap-1">
                      {can.discount && invoice.editable ? (
                        <Link
                          href={`/invoices/${invoice.id}/items/${item.id}/discount`}
                          className="btn btn-secondary"
                          aria-label={`Diskon ${item.description}`}
                        >
                          Diskon
                        </Link>
                      ) : null}
                      {can.edit && invoice.editable && item.manual ? (
                        <>
                          <Link
                            href={`/invoices/${invoice.id}/items/${item.id}/edit`}
                            className="btn btn-secondary"
                            aria-label={`Ubah ${item.description}`}
                          >
                            Ubah
                          </Link>
                          <ConfirmAction
                            label="Hapus"
                            message={`Hapus ${item.description} dari tagihan.`}
                            confirmLabel="Ya, hapus"
                            onConfirm={() => {
                              router.post(`/invoices/${invoice.id}/items/${item.id}/remove`);
                            }}
                          />
                        </>
                      ) : null}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr>
                <td colSpan={4} className="text-right">
                  Subtotal
                </td>
                <td className="text-right">{formatRupiah(invoice.subtotal_amount)}</td>
                <td />
              </tr>
              {invoice.discount_amount > 0 ? (
                <tr>
                  <td colSpan={4} className="text-right">
                    Diskon tagihan{discountLabel(invoice.discount_type, invoice.discount_value)}
                    {invoice.discount_reason ? ` - ${invoice.discount_reason}` : ''}
                  </td>
                  <td className="text-right">-{formatRupiah(invoice.discount_amount)}</td>
                  <td />
                </tr>
              ) : null}
              <tr>
                <td colSpan={4} className="text-right font-bold">
                  Total
                </td>
                <td className="text-right font-bold">{formatRupiah(invoice.total_amount)}</td>
                <td />
              </tr>
              <tr>
                <td colSpan={4} className="text-right">
                  Dibayar
                </td>
                <td className="text-right">{formatRupiah(invoice.paid_amount)}</td>
                <td />
              </tr>
              <tr>
                <td colSpan={4} className="text-right font-bold">
                  Sisa tagihan
                </td>
                <td className="text-right font-bold">{formatRupiah(invoice.balance_amount)}</td>
                <td />
              </tr>
            </tfoot>
          </table>
        </section>

        <section aria-labelledby="bayar-judul" className="flex flex-col gap-2">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h2 id="bayar-judul" className="flex-1">
              Pembayaran
            </h2>
            {can.refund && !voided && invoice.paid_amount > 0 ? (
              <Link href={`/invoices/${invoice.id}/refunds/create`} className="btn btn-secondary">
                Pengembalian dana
              </Link>
            ) : null}
            {can.void && !voided ? (
              <Link href={`/invoices/${invoice.id}/void`} className="btn btn-danger">
                Batalkan tagihan
              </Link>
            ) : null}
          </div>
          {payments.length === 0 ? (
            <p className="text-muted">Belum ada pembayaran.</p>
          ) : (
            <table>
              <caption className="sr-only">Riwayat pembayaran</caption>
              <thead>
                <tr>
                  <th scope="col">Waktu</th>
                  <th scope="col">Jenis</th>
                  <th scope="col">Metode</th>
                  <th scope="col">Kwitansi</th>
                  <th scope="col">Penerima</th>
                  <th scope="col" className="text-right">
                    Jumlah
                  </th>
                  <th scope="col">
                    <span className="sr-only">Cetak</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {payments.map((p) => (
                  <tr key={p.id}>
                    <td>{formatDateTime(p.paid_at, timezone)}</td>
                    <td>
                      {p.type_label}
                      {p.reason ? <span className="text-muted block">{p.reason}</span> : null}
                    </td>
                    <td>
                      {p.method_name ?? '-'}
                      {p.reference_no ? (
                        <span className="text-muted block">Ref. {p.reference_no}</span>
                      ) : null}
                    </td>
                    <td>{p.receipt_no ?? '-'}</td>
                    <td>{p.received_by_name ?? '-'}</td>
                    <td className="text-right">
                      {p.type === 'refund' ? '-' : ''}
                      {formatRupiah(p.amount)}
                    </td>
                    <td>
                      {p.type !== 'refund' ? (
                        <span className="flex flex-wrap justify-end gap-1">
                          <a
                            href={print(`doc=receipt&format=a4&payment=${p.id}`)}
                            target="_blank"
                            rel="noreferrer"
                            className="btn btn-secondary"
                            aria-label={`Cetak kwitansi ${p.receipt_no ?? ''} A4`}
                          >
                            Kwitansi A4
                          </a>
                          <a
                            href={print(`doc=receipt&format=thermal&payment=${p.id}`)}
                            target="_blank"
                            rel="noreferrer"
                            className="btn btn-secondary"
                            aria-label={`Cetak kwitansi ${p.receipt_no ?? ''} 80 mm`}
                          >
                            80 mm
                          </a>
                        </span>
                      ) : null}
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
