import { EmptyState } from '@/components/ui/EmptyState';
import { formatCell, isNumericKind } from '@/features/reporting/cell';
import type { Report, ReportTable } from '@/features/reporting/schemas';

function MetricGrid({ metrics }: { metrics: Report['metrics'] }) {
  if (metrics.length === 0) {
    return null;
  }
  return (
    <dl className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3" aria-label="Ringkasan">
      {metrics.map((metric) => (
        <div key={metric.label} className="pane">
          <dt className="pane-head">{metric.label}</dt>
          <dd className="p-2 text-lg font-semibold">
            {metric.value === null ? '-' : formatCell(metric.kind, metric.value)}
          </dd>
        </div>
      ))}
    </dl>
  );
}

function DataTable({ table }: { table: ReportTable }) {
  return (
    <section aria-label={table.title} className="flex flex-col gap-1">
      <h2 className="text-base">{table.title}</h2>
      {table.rows.length === 0 ? (
        <EmptyState
          kind="chart"
          size="section"
          title={table.empty_text ?? 'Tidak ada data'}
          description="Ubah rentang tanggal atau filter untuk melihat data lain."
        />
      ) : (
        <div className="overflow-x-auto">
          <table>
            <caption className="sr-only">{table.title}</caption>
            <thead>
              <tr>
                {table.columns.map((column) => (
                  <th
                    key={column.key}
                    scope="col"
                    className={isNumericKind(column.kind) ? 'text-right' : undefined}
                  >
                    {column.label}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {table.rows.map((row, index) => (
                <tr key={index}>
                  {table.columns.map((column) => (
                    <td
                      key={column.key}
                      className={isNumericKind(column.kind) ? 'text-right' : undefined}
                    >
                      {formatCell(column.kind, row[column.key] ?? null)}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
            {table.totals ? (
              <tfoot>
                <tr className="font-semibold">
                  {table.columns.map((column) => (
                    <td
                      key={column.key}
                      className={isNumericKind(column.kind) ? 'text-right' : undefined}
                    >
                      {formatCell(column.kind, table.totals?.[column.key] ?? null)}
                    </td>
                  ))}
                </tr>
              </tfoot>
            ) : null}
          </table>
        </div>
      )}
      {table.truncated ? (
        <p className="text-muted text-xs">
          Tabel dipotong pada baris pertama. Persempit rentang tanggal untuk melihat sisanya.
        </p>
      ) : null}
    </section>
  );
}

/** Isi satu laporan: angka ringkasan, tabel, dan catatan definisi. Bentuk yang sama dengan PDF dan Excel. */
export function ReportView({ report }: { report: Report }) {
  return (
    <div className="flex flex-col gap-4">
      <MetricGrid metrics={report.metrics} />
      {report.tables.map((table) => (
        <DataTable key={table.title} table={table} />
      ))}
      {report.notes.length > 0 ? (
        <section aria-label="Catatan definisi" className="flex flex-col gap-1">
          <h2 className="text-base">Catatan</h2>
          <ul className="text-muted list-disc pl-5 text-xs">
            {report.notes.map((note) => (
              <li key={note}>{note}</li>
            ))}
          </ul>
        </section>
      ) : null}
    </div>
  );
}
