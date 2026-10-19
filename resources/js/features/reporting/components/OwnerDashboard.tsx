import { Link } from '@inertiajs/react';

import { formatPercent } from '@/features/reporting/cell';
import type { Dashboard } from '@/features/reporting/schemas';
import { formatDate, formatRupiah } from '@/lib/format';

const CHART_HEIGHT = 96;
const BAR_WIDTH = 10;
const BAR_GAP = 4;

function Card({
  title,
  value,
  hint,
  href,
}: {
  title: string;
  value: string;
  hint?: string;
  href?: string;
}) {
  const body = (
    <>
      <span className="pane-head">{title}</span>
      <span className="block p-2 text-lg font-semibold">{value}</span>
      {hint ? <span className="text-muted block px-2 pb-2 text-xs">{hint}</span> : null}
    </>
  );
  return href ? (
    <Link href={href} className="pane hover:bg-brand-50 block h-full">
      {body}
    </Link>
  ) : (
    <div className="pane h-full">{body}</div>
  );
}

/** Teks perubahan pendapatan bulan ini dibanding periode yang sama bulan lalu. */
export function changeText(current: number, previous: number, basisPoints: number | null): string {
  if (basisPoints === null) {
    return previous === 0 && current === 0
      ? 'Belum ada pendapatan pada periode yang sama bulan lalu.'
      : 'Bulan lalu pada periode yang sama belum ada pendapatan.';
  }
  if (basisPoints === 0) {
    return 'Sama dengan bulan lalu pada periode yang sama.';
  }
  return `${basisPoints > 0 ? 'Naik' : 'Turun'} ${formatPercent(Math.abs(basisPoints))} dari bulan lalu pada periode yang sama (${formatRupiah(previous)}).`;
}

/** Grafik batang pendapatan 30 hari terakhir dalam SVG; tabel tersembunyi menyajikan angka yang sama bagi pembaca layar. */
function RevenueChart({ series }: { series: Dashboard['series'] }) {
  const max = Math.max(1, ...series.map((point) => point.revenue));
  const width = series.length * (BAR_WIDTH + BAR_GAP);
  return (
    <section aria-label="Pendapatan 30 hari terakhir" className="pane">
      <h2 className="pane-head">Pendapatan 30 hari terakhir</h2>
      <div className="overflow-x-auto p-2">
        <svg
          role="img"
          aria-label={`Grafik batang pendapatan harian, ${series.length.toString()} hari terakhir`}
          viewBox={`0 0 ${width.toString()} ${CHART_HEIGHT.toString()}`}
          className="h-28 w-full min-w-[420px]"
          preserveAspectRatio="none"
        >
          {series.map((point, index) => {
            const height = Math.round((point.revenue / max) * (CHART_HEIGHT - 4));
            return (
              <rect
                key={point.date}
                x={index * (BAR_WIDTH + BAR_GAP)}
                y={CHART_HEIGHT - height}
                width={BAR_WIDTH}
                height={Math.max(height, point.revenue > 0 ? 1 : 0)}
                className="fill-brand-600"
              >
                <title>{`${formatDate(point.date)}: ${formatRupiah(point.revenue)}`}</title>
              </rect>
            );
          })}
        </svg>
        <p className="text-muted mt-1 flex justify-between text-xs">
          <span>{series[0] ? formatDate(series[0].date) : ''}</span>
          <span>
            {series[series.length - 1] ? formatDate(series[series.length - 1]?.date ?? '') : ''}
          </span>
        </p>
      </div>
      <table className="sr-only">
        <caption>Pendapatan harian 30 hari terakhir</caption>
        <thead>
          <tr>
            <th scope="col">Tanggal</th>
            <th scope="col">Pendapatan</th>
          </tr>
        </thead>
        <tbody>
          {series.map((point) => (
            <tr key={point.date}>
              <td>{formatDate(point.date)}</td>
              <td>{formatRupiah(point.revenue)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  );
}

/** Dasbor ringkasan bisnis (PRD 11): untuk pemegang izin laporan keuangan dan klinis. */
export function OwnerDashboard({ dashboard }: { dashboard: Dashboard }) {
  return (
    <section aria-label="Ringkasan bisnis" className="flex flex-col gap-2">
      <h2 className="text-base">Ringkasan bisnis {formatDate(dashboard.date)}</h2>
      <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        <Card
          title="Pendapatan hari ini"
          value={formatRupiah(dashboard.revenue_today)}
          href="/reports/daily-summary"
        />
        <Card
          title="Pendapatan bulan ini"
          value={formatRupiah(dashboard.revenue_month)}
          hint={changeText(
            dashboard.revenue_month,
            dashboard.revenue_prev_month_same_period,
            dashboard.revenue_change_basis_points,
          )}
          href="/reports/revenue"
        />
        <Card
          title="Kunjungan"
          value={`${dashboard.visits_today.toString()} hari ini`}
          hint={`${dashboard.visits_month.toString()} kunjungan bulan ini`}
          href="/reports/visits"
        />
        <Card
          title="Tingkat janji tidak datang"
          value={formatPercent(dashboard.no_show_basis_points)}
          hint={`${dashboard.no_show_count.toString()} janji tidak datang bulan ini`}
          href="/reports/visits"
        />
        <Card
          title="Total piutang"
          value={formatRupiah(dashboard.receivables_total)}
          hint={`${dashboard.receivables_count.toString()} tagihan belum lunas`}
          href="/reports/receivables"
        />
        <Card
          title="Stok menipis"
          value={`${dashboard.low_stock_count.toString()} jenis`}
          hint="Di bawah stok minimum"
          href="/reports/stock"
        />
      </div>
      <RevenueChart series={dashboard.series} />
    </section>
  );
}
