import { Link, router } from '@inertiajs/react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PageHead } from '@/components/layout/PageTitle';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Select } from '@/components/ui/Select';
import type { SelectOption } from '@/components/ui/Select';
import { CLAIM_TONE } from '@/features/insurance/schemas';
import { formatRupiah } from '@/lib/format';

interface Row {
  id: string;
  claim_no: string;
  invoice_number: string | null;
  patient_name: string;
  medical_record_no: string;
  insurer_name: string;
  status: string;
  status_label: string;
  claimed_amount: number;
  approved_amount: number | null;
  paid_amount: number;
  outstanding_amount: number;
}

interface Props {
  claims: Row[];
  statuses: SelectOption[];
  insurers: SelectOption[];
  filters: { status: string; insurer: string };
}

/** P2-02: daftar klaim ke penjamin. */
export default function Index({ claims, statuses, insurers, filters }: Props) {
  function filter(next: Partial<Props['filters']>) {
    const query = { ...filters, ...next };
    router.get(
      '/insurance/claims',
      Object.fromEntries(Object.entries(query).filter(([, value]) => value !== '')),
      { preserveScroll: true, preserveState: true },
    );
  }

  return (
    <AppLayout>
      <main className="flex flex-col gap-3 p-3">
        <PageHead title="Klaim penjamin" />
        <PageHeader
          title="Klaim penjamin"
          description="Tagihan pasien yang ditagihkan ke asuransi atau perusahaan. Buat klaim dari halaman tagihan."
        />
        <div className="flex flex-wrap items-end gap-3">
          <Select
            label="Status"
            placeholder="Semua status"
            options={statuses}
            value={filters.status}
            onChange={(event) => {
              filter({ status: event.target.value });
            }}
          />
          <Select
            label="Penjamin"
            placeholder="Semua penjamin"
            options={insurers}
            value={filters.insurer}
            onChange={(event) => {
              filter({ insurer: event.target.value });
            }}
          />
          <Link href="/insurance/receivables" className="btn btn-secondary">
            Piutang penjamin
          </Link>
        </div>

        {claims.length === 0 ? (
          <p className="text-muted text-sm">Belum ada klaim.</p>
        ) : (
          <table className="w-full text-left text-sm">
            <caption className="sr-only">Daftar klaim penjamin</caption>
            <thead>
              <tr>
                <th scope="col">Nomor klaim</th>
                <th scope="col">Tagihan</th>
                <th scope="col">Pasien</th>
                <th scope="col">Penjamin</th>
                <th scope="col">Status</th>
                <th scope="col" className="text-right">
                  Diklaim
                </th>
                <th scope="col" className="text-right">
                  Disetujui
                </th>
                <th scope="col" className="text-right">
                  Diterima
                </th>
                <th scope="col" className="text-right">
                  Piutang
                </th>
              </tr>
            </thead>
            <tbody>
              {claims.map((claim) => (
                <tr key={claim.id}>
                  <td>
                    <Link href={`/insurance/claims/${claim.id}`} className="underline">
                      {claim.claim_no}
                    </Link>
                  </td>
                  <td>{claim.invoice_number ?? '-'}</td>
                  <td>
                    {claim.patient_name} ({claim.medical_record_no})
                  </td>
                  <td>{claim.insurer_name}</td>
                  <td>
                    <Badge tone={CLAIM_TONE[claim.status] ?? 'neutral'}>{claim.status_label}</Badge>
                  </td>
                  <td className="text-right">{formatRupiah(claim.claimed_amount)}</td>
                  <td className="text-right">
                    {claim.approved_amount === null ? '-' : formatRupiah(claim.approved_amount)}
                  </td>
                  <td className="text-right">{formatRupiah(claim.paid_amount)}</td>
                  <td className="text-right">{formatRupiah(claim.outstanding_amount)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </main>
    </AppLayout>
  );
}
