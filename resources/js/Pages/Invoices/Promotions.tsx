import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { SyntheticEvent } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageHead } from '@/components/layout/PageTitle';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { ConfirmAction } from '@/components/ui/ConfirmAction';
import { Select } from '@/components/ui/Select';
import { TextField } from '@/components/ui/TextField';
import { packageUseSchema, pointsSchema, voucherCodeSchema } from '@/features/loyalty/schemas';
import { formatDate, formatRupiah } from '@/lib/format';

interface Props {
  invoice: {
    id: string;
    number: string | null;
    patient_id: string;
    patient_name: string;
    medical_record_no: string;
    subtotal_amount: number;
    total_amount: number;
    editable: boolean;
    has_manual_discount: boolean;
  };
  promotion: { type: string; label: string; discount_amount: number } | null;
  loyalty: {
    enabled: boolean;
    balance: number;
    point_value_amount: number;
    min_redeem_points: number;
    max_points: number;
  };
  items: {
    id: string;
    description: string;
    unit_price_amount: number;
    discount_amount: number;
    package_covered: number | null;
  }[];
  packages: { id: string; name: string; sessions_left: number; expires_on: string | null }[];
  can: { edit: boolean };
}

/** P3-06: voucher, penukaran poin, dan sesi paket kunjungan pada satu tagihan. */
export default function Promotions({ invoice, promotion, loyalty, items, packages, can }: Props) {
  const base = `/invoices/${invoice.id}/promotions`;
  const voucher = useForm({ code: '' });
  const points = useForm({ points: '' });
  const pack = useForm({ item_id: '', patient_package_id: '' });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const err = (key: string, server?: string) => errors[key] ?? server;
  const editable = can.edit && invoice.editable;

  function submitVoucher(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = voucherCodeSchema.safeParse(voucher.data);
    if (!result.success) {
      setErrors({ code: result.error.issues[0]?.message ?? '' });
      return;
    }
    setErrors({});
    voucher.post(`${base}/voucher`, {
      preserveScroll: true,
      onSuccess: () => {
        voucher.reset();
      },
    });
  }

  function submitPoints(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = pointsSchema(
      loyalty.min_redeem_points,
      loyalty.max_points,
      loyalty.balance,
    ).safeParse(points.data);
    if (!result.success) {
      setErrors({ points: result.error.issues[0]?.message ?? '' });
      return;
    }
    setErrors({});
    points.post(`${base}/points`, {
      preserveScroll: true,
      onSuccess: () => {
        points.reset();
      },
    });
  }

  function submitPackage(event: SyntheticEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = packageUseSchema.safeParse(pack.data);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        next[String(issue.path[0])] ??= issue.message;
      }
      setErrors(next);
      return;
    }
    setErrors({});
    pack.post(`${base}/package`, { preserveScroll: true });
  }

  return (
    <AppLayout>
      <main className="flex max-w-3xl flex-col gap-4 p-3">
        <PageHead title="Promo tagihan" />
        <PageHeader
          title={`Promo dan poin tagihan ${invoice.number ?? '(draft)'}`}
          back={{ href: `/invoices/${invoice.id}`, label: 'tagihan' }}
          description={`${invoice.patient_name} (${invoice.medical_record_no})`}
        />
        <dl className="text-sm">
          <div className="flex gap-3">
            <dt className="text-muted w-48">Subtotal</dt>
            <dd>{formatRupiah(invoice.subtotal_amount)}</dd>
          </div>
          <div className="flex gap-3">
            <dt className="text-muted w-48">Total tagihan</dt>
            <dd>{formatRupiah(invoice.total_amount)}</dd>
          </div>
        </dl>
        {!invoice.editable ? (
          <Alert tone="warning">
            Tagihan sudah memiliki pembayaran atau dibatalkan, sehingga promo tidak dapat diubah.
          </Alert>
        ) : null}

        <section aria-labelledby="promo-aktif" className="flex flex-col gap-2">
          <h2 id="promo-aktif">Promo tagihan</h2>
          {promotion ? (
            <div className="flex flex-wrap items-center gap-3">
              <p className="text-sm">
                {promotion.label}: potongan {formatRupiah(promotion.discount_amount)}
              </p>
              {editable ? (
                <ConfirmAction
                  label="Lepas promo"
                  message="Diskon promo dicabut dan voucher atau poin dikembalikan. Lanjutkan?"
                  confirmLabel="Ya, lepas"
                  onConfirm={() => {
                    router.post(`${base}/remove`, {}, { preserveScroll: true });
                  }}
                />
              ) : null}
            </div>
          ) : invoice.has_manual_discount ? (
            <Alert tone="info">
              Tagihan memakai diskon manual; hapus diskon itu dulu untuk memakai voucher atau poin.
            </Alert>
          ) : editable ? (
            <div className="flex flex-col gap-4">
              <form
                onSubmit={submitVoucher}
                noValidate
                aria-label="Pakai voucher"
                className="form-grid max-w-md"
              >
                <TextField
                  label="Kode promo"
                  value={voucher.data.code}
                  onChange={(event) => {
                    voucher.setData('code', event.target.value);
                  }}
                  error={err('code', voucher.errors.code)}
                />
                <div>
                  <Button type="submit" loading={voucher.processing}>
                    Pakai voucher
                  </Button>
                </div>
              </form>

              {loyalty.enabled ? (
                <form
                  onSubmit={submitPoints}
                  noValidate
                  aria-label="Tukar poin"
                  className="form-grid max-w-md"
                >
                  <p className="text-sm">
                    Saldo {loyalty.balance} poin (1 poin ={' '}
                    {formatRupiah(loyalty.point_value_amount)}). Minimal {loyalty.min_redeem_points}{' '}
                    poin, maksimal {loyalty.max_points} poin untuk tagihan ini.
                  </p>
                  <TextField
                    label="Poin ditukar"
                    inputMode="numeric"
                    value={points.data.points}
                    onChange={(event) => {
                      points.setData('points', event.target.value);
                    }}
                    error={err('points', points.errors.points)}
                  />
                  <div>
                    <Button type="submit" variant="secondary" loading={points.processing}>
                      Tukar poin
                    </Button>
                  </div>
                </form>
              ) : (
                <p className="text-muted text-sm">Poin loyalitas belum diaktifkan di klinik ini.</p>
              )}
            </div>
          ) : (
            <p className="text-muted text-sm">Tidak ada promo pada tagihan ini.</p>
          )}
        </section>

        <section aria-labelledby="paket" className="flex flex-col gap-2">
          <h2 id="paket">Sesi paket kunjungan</h2>
          <table className="w-full text-left text-sm">
            <caption className="sr-only">Item tagihan</caption>
            <thead>
              <tr>
                <th scope="col">Item</th>
                <th scope="col" className="text-right">
                  Harga satuan
                </th>
                <th scope="col" className="text-right">
                  Diskon
                </th>
                <th scope="col">
                  <span className="sr-only">Aksi</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.id}>
                  <td>{item.description}</td>
                  <td className="text-right">{formatRupiah(item.unit_price_amount)}</td>
                  <td className="text-right">{formatRupiah(item.discount_amount)}</td>
                  <td>
                    {item.package_covered !== null && editable ? (
                      <ConfirmAction
                        label="Kembalikan sesi"
                        message="Diskon sesi paket dicabut dan sesinya dikembalikan ke paket. Lanjutkan?"
                        confirmLabel="Ya, kembalikan"
                        onConfirm={() => {
                          router.post(
                            `${base}/package/remove`,
                            { item_id: item.id },
                            { preserveScroll: true },
                          );
                        }}
                      />
                    ) : item.package_covered !== null ? (
                      'Ditanggung paket'
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {editable && packages.length > 0 ? (
            <form
              onSubmit={submitPackage}
              noValidate
              aria-label="Pakai sesi paket"
              className="form-grid max-w-md"
            >
              <Select
                label="Item tagihan"
                placeholder="Pilih item"
                options={items
                  .filter((i) => i.discount_amount === 0)
                  .map((i) => ({ value: i.id, label: i.description }))}
                value={pack.data.item_id}
                onChange={(event) => {
                  pack.setData('item_id', event.target.value);
                }}
                error={err('item_id', pack.errors.item_id)}
              />
              <Select
                label="Paket pasien"
                placeholder="Pilih paket"
                options={packages.map((p) => ({
                  value: p.id,
                  label: `${p.name} (sisa ${String(p.sessions_left)} sesi${p.expires_on ? `, sampai ${formatDate(p.expires_on)}` : ''})`,
                }))}
                value={pack.data.patient_package_id}
                onChange={(event) => {
                  pack.setData('patient_package_id', event.target.value);
                }}
                error={err('patient_package_id', pack.errors.patient_package_id)}
              />
              <div>
                <Button type="submit" variant="secondary" loading={pack.processing}>
                  Pakai satu sesi
                </Button>
              </div>
            </form>
          ) : (
            <p className="text-muted text-sm">
              {packages.length === 0 ? 'Pasien tidak memiliki paket aktif.' : null}
            </p>
          )}
        </section>
      </main>
    </AppLayout>
  );
}
