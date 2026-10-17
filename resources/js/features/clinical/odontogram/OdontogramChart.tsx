import type { OdontogramData } from '@/features/clinical/odontogram/api';
import { polygons, SIZE, textColor } from '@/features/clinical/odontogram/geometry';
import { conditionAt, findCondition, toothLabel } from '@/features/clinical/odontogram/model';
import type { SurfaceCode } from '@/features/clinical/odontogram/model';
import { toothRows } from '@/features/clinical/tooth';

const STEP = SIZE + 8;

function Tooth({ fdi, data, changed }: { fdi: number; data: OdontogramData; changed: boolean }) {
  const polys = polygons(fdi);
  const whole = findCondition(data.conditions, conditionAt(data, fdi, 'W')?.condition_code);
  const label = toothLabel(data, fdi);

  return (
    <g role="img" aria-label={changed ? `${label}, berubah` : label} transform="translate(2 2)">
      {changed ? (
        <rect
          x={-3}
          y={-3}
          width={SIZE + 6}
          height={SIZE + 6}
          fill="none"
          stroke="#0F172A"
          strokeWidth={2}
          strokeDasharray="4 2"
        />
      ) : null}
      {(Object.keys(polys) as SurfaceCode[]).map((surface) => {
        const condition = findCondition(
          data.conditions,
          conditionAt(data, fdi, surface)?.condition_code,
        );
        return (
          <polygon
            key={surface}
            points={polys[surface]}
            fill={condition?.color ?? '#FFFFFF'}
            stroke="#334155"
            strokeWidth={1}
          />
        );
      })}
      {(Object.keys(polys) as SurfaceCode[]).map((surface) => {
        const condition = findCondition(
          data.conditions,
          conditionAt(data, fdi, surface)?.condition_code,
        );
        if (!condition || condition.code === 'SEH') {
          return null;
        }
        const points = polys[surface].split(' ').map((p) => p.split(',').map(Number));
        const cx = points.reduce((sum, p) => sum + (p[0] ?? 0), 0) / points.length;
        const cy = points.reduce((sum, p) => sum + (p[1] ?? 0), 0) / points.length;
        return (
          <text
            key={`t-${surface}`}
            x={cx}
            y={cy + 3}
            fontSize={8}
            textAnchor="middle"
            fill={textColor(condition.color)}
          >
            {condition.code.slice(0, surface === 'O' ? 3 : 1)}
          </text>
        );
      })}
      {whole ? (
        <>
          <line x1={0} y1={0} x2={SIZE} y2={SIZE} stroke={whole.color} strokeWidth={3} />
          <line x1={SIZE} y1={0} x2={0} y2={SIZE} stroke={whole.color} strokeWidth={3} />
        </>
      ) : null}
      <text x={SIZE / 2} y={SIZE + 14} fontSize={11} textAnchor="middle" fill="#0F172A">
        {fdi}
      </text>
      {whole ? (
        <text x={SIZE / 2} y={SIZE + 26} fontSize={9} textAnchor="middle" fill="#0F172A">
          {whole.code}
        </text>
      ) : null}
    </g>
  );
}

interface OdontogramChartProps {
  data: OdontogramData;
  /** Gigi yang berbeda dari sisi pembanding; digaris putus-putus. */
  changed: ReadonlySet<number>;
  includePrimary: boolean;
  label: string;
}

/** Bagan odontogram hanya baca (ODO-07) dengan bentuk gigi yang sama seperti papan kunjungan. */
export function OdontogramChart({ data, changed, includePrimary, label }: OdontogramChartProps) {
  const sets: ('permanent' | 'primary')[] = includePrimary
    ? ['permanent', 'primary']
    : ['permanent'];

  return (
    <div className="flex flex-col gap-2 overflow-x-auto" role="group" aria-label={label}>
      {sets.map((set) => {
        const rows = toothRows(set);
        return (
          <div key={set} className="flex flex-col gap-1">
            {[rows.upper, rows.lower].map((teeth, index) => (
              <svg
                key={index}
                viewBox={`0 0 ${String(teeth.length * STEP)} ${String(SIZE + 32)}`}
                width={teeth.length * STEP}
                height={SIZE + 32}
                className="max-w-full"
              >
                {teeth.map((fdi, i) => (
                  <g key={fdi} transform={`translate(${String(i * STEP)} 0)`}>
                    <Tooth fdi={fdi} data={data} changed={changed.has(fdi)} />
                  </g>
                ))}
              </svg>
            ))}
          </div>
        );
      })}
    </div>
  );
}
