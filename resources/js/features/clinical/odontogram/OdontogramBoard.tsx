import { useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import type { OdontogramData, ToothCondition } from '@/features/clinical/odontogram/api';
import {
  conditionAt,
  findCondition,
  mesialOnRight,
  SURFACE_LABEL,
  toothLabel,
} from '@/features/clinical/odontogram/model';
import type { SurfaceCode, TargetSurface } from '@/features/clinical/odontogram/model';
import { useOdontogram, useSetCondition } from '@/features/clinical/odontogram/queries';
import { allTeeth, toothRows } from '@/features/clinical/tooth';
import type { Dentition } from '@/features/clinical/tooth';
import { HttpError } from '@/lib/http';

const SIZE = 40;

/** Poligon tiap permukaan pada kotak SIZE x SIZE: bukal di atas, lingual di bawah, oklusal di tengah. */
function polygons(fdi: number): Record<SurfaceCode, string> {
  const s = SIZE;
  const a = 11;
  const b = s - 11;
  const mesialRight = mesialOnRight(fdi);
  const left = `0,0 0,${String(s)} ${String(a)},${String(b)} ${String(a)},${String(a)}`;
  const right = `${String(s)},0 ${String(s)},${String(s)} ${String(b)},${String(b)} ${String(b)},${String(a)}`;
  return {
    B: `0,0 ${String(s)},0 ${String(b)},${String(a)} ${String(a)},${String(a)}`,
    L: `0,${String(s)} ${String(s)},${String(s)} ${String(b)},${String(b)} ${String(a)},${String(b)}`,
    O: `${String(a)},${String(a)} ${String(b)},${String(a)} ${String(b)},${String(b)} ${String(a)},${String(b)}`,
    M: mesialRight ? right : left,
    D: mesialRight ? left : right,
  };
}

function textColor(hex: string): string {
  const value = hex.replace('#', '');
  const r = parseInt(value.slice(0, 2), 16);
  const g = parseInt(value.slice(2, 4), 16);
  const b = parseInt(value.slice(4, 6), 16);
  return r * 0.299 + g * 0.587 + b * 0.114 > 150 ? '#0F172A' : '#FFFFFF';
}

interface ToothProps {
  fdi: number;
  data: OdontogramData;
  selected: boolean;
  selectedSurface: TargetSurface;
  focusable: boolean;
  onSelect: (fdi: number, surface: TargetSurface) => void;
  register: (fdi: number, node: SVGGElement | null) => void;
  onKey: (event: KeyboardEvent<SVGGElement>, fdi: number) => void;
}

function Tooth({
  fdi,
  data,
  selected,
  selectedSurface,
  focusable,
  onSelect,
  register,
  onKey,
}: ToothProps) {
  const polys = polygons(fdi);
  const whole = conditionAt(data, fdi, 'W');
  const wholeCondition = findCondition(data.conditions, whole?.condition_code);

  return (
    <g
      ref={(node) => {
        register(fdi, node);
      }}
      role="button"
      tabIndex={focusable ? 0 : -1}
      aria-label={toothLabel(data, fdi)}
      aria-pressed={selected}
      onKeyDown={(event) => {
        onKey(event, fdi);
      }}
      onClick={() => {
        onSelect(fdi, selectedSurface);
      }}
      className="cursor-pointer outline-none focus-visible:[&>rect]:stroke-[3]"
      transform="translate(2 2)"
    >
      <rect
        x={-2}
        y={-2}
        width={SIZE + 4}
        height={SIZE + 4}
        fill="none"
        stroke={selected ? '#1D4ED8' : 'transparent'}
        strokeWidth={selected ? 3 : 0}
      />
      {(Object.keys(polys) as SurfaceCode[]).map((surface) => {
        const entry = conditionAt(data, fdi, surface);
        const condition = findCondition(data.conditions, entry?.condition_code);
        const fill = condition?.color ?? '#FFFFFF';
        return (
          <polygon
            key={surface}
            points={polys[surface]}
            fill={fill}
            stroke="#334155"
            strokeWidth={selected && selectedSurface === surface ? 2.5 : 1}
            onClick={(event) => {
              event.stopPropagation();
              onSelect(fdi, surface);
            }}
          >
            <title>{`Gigi ${String(fdi)}, ${SURFACE_LABEL[surface]}${condition ? `: ${condition.name}` : ''}`}</title>
          </polygon>
        );
      })}
      {(Object.keys(polys) as SurfaceCode[]).map((surface) => {
        const entry = conditionAt(data, fdi, surface);
        const condition = findCondition(data.conditions, entry?.condition_code);
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
            pointerEvents="none"
          >
            {condition.code.slice(0, surface === 'O' ? 3 : 1)}
          </text>
        );
      })}
      {wholeCondition ? (
        <>
          <line
            x1={0}
            y1={0}
            x2={SIZE}
            y2={SIZE}
            stroke={wholeCondition.color}
            strokeWidth={3}
            pointerEvents="none"
          />
          <line
            x1={SIZE}
            y1={0}
            x2={0}
            y2={SIZE}
            stroke={wholeCondition.color}
            strokeWidth={3}
            pointerEvents="none"
          />
        </>
      ) : null}
      <text
        x={SIZE / 2}
        y={SIZE + 14}
        fontSize={11}
        textAnchor="middle"
        fill="#0F172A"
        pointerEvents="none"
      >
        {fdi}
      </text>
      {wholeCondition ? (
        <text
          x={SIZE / 2}
          y={SIZE + 26}
          fontSize={9}
          textAnchor="middle"
          fill="#0F172A"
          pointerEvents="none"
        >
          {wholeCondition.code}
        </text>
      ) : null}
    </g>
  );
}

function Row({
  teeth,
  ...rest
}: { teeth: number[] } & Omit<ToothProps, 'fdi' | 'selected' | 'focusable'> & {
    selectedFdi: number | null;
    firstFocusable: number;
  }) {
  const width = teeth.length * (SIZE + 8);
  return (
    <svg
      role="group"
      aria-label={`Deret gigi ${String(teeth[0])} sampai ${String(teeth[teeth.length - 1])}`}
      viewBox={`0 0 ${String(width)} ${String(SIZE + 32)}`}
      width={width}
      height={SIZE + 32}
      className="max-w-full"
    >
      {teeth.map((fdi, index) => (
        <g key={fdi} transform={`translate(${String(index * (SIZE + 8))} 0)`}>
          <Tooth
            fdi={fdi}
            data={rest.data}
            selected={rest.selectedFdi === fdi}
            selectedSurface={rest.selectedSurface}
            focusable={fdi === rest.firstFocusable}
            onSelect={rest.onSelect}
            register={rest.register}
            onKey={rest.onKey}
          />
        </g>
      ))}
    </svg>
  );
}

interface OdontogramBoardProps {
  patientId: string;
  encounterId: string;
  canWrite: boolean;
  defaultDentition?: Dentition;
}

const DENTITIONS: { value: Dentition; label: string }[] = [
  { value: 'permanent', label: 'Gigi dewasa (32)' },
  { value: 'primary', label: 'Gigi sulung (20)' },
  { value: 'mixed', label: 'Gigi campuran' },
];

export function OdontogramBoard({
  patientId,
  encounterId,
  canWrite,
  defaultDentition = 'permanent',
}: OdontogramBoardProps) {
  const query = useOdontogram(patientId, encounterId);
  const mutation = useSetCondition(patientId, encounterId);
  const [dentition, setDentition] = useState<Dentition>(defaultDentition);
  const [selectedFdi, setSelectedFdi] = useState<number | null>(null);
  const [surface, setSurface] = useState<TargetSurface>('O');
  const [conditionCode, setConditionCode] = useState('');
  const nodes = useRef(new Map<number, SVGGElement>());

  if (query.isPending) {
    return <p className="text-muted text-sm">Memuat odontogram...</p>;
  }
  if (query.isError) {
    return <Alert tone="error">Odontogram tidak dapat dimuat. Muat ulang halaman.</Alert>;
  }

  const data = query.data;
  const order = allTeeth(dentition);
  const sets: ('permanent' | 'primary')[] =
    dentition === 'mixed' ? ['permanent', 'primary'] : [dentition];
  const active = data.conditions.filter((c) => c.active);
  const applicable = (c: ToothCondition) =>
    c.scope === 'both' || (surface === 'W' ? c.scope === 'tooth' : c.scope === 'surface');
  const options = active
    .filter(applicable)
    .map((c) => ({ value: c.code, label: `${c.code} - ${c.name}` }));
  const firstFocusable = selectedFdi ?? order[0] ?? 11;

  function onKey(event: KeyboardEvent<SVGGElement>, fdi: number) {
    const index = order.indexOf(fdi);
    const move = (to: number | undefined) => {
      if (to !== undefined) {
        event.preventDefault();
        nodes.current.get(to)?.focus();
      }
    };
    if (event.key === 'ArrowRight') {
      move(order[index + 1]);
    } else if (event.key === 'ArrowLeft') {
      move(order[index - 1]);
    } else if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      setSelectedFdi(fdi);
    }
  }

  function apply() {
    if (selectedFdi === null || conditionCode === '') {
      return;
    }
    mutation.mutate({ fdi: selectedFdi, surface, conditionCode });
  }

  const selectedState = selectedFdi === null ? null : conditionAt(data, selectedFdi, surface);
  const errorMessage =
    mutation.error instanceof HttpError
      ? mutation.error.message
      : mutation.isError
        ? 'Perubahan gagal disimpan.'
        : null;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end gap-3">
        <Select
          label="Jenis gigi"
          options={DENTITIONS}
          value={dentition}
          onChange={(event) => {
            setDentition(event.target.value as Dentition);
          }}
        />
        <a
          href={`/patients/${patientId}/odontogram/print`}
          target="_blank"
          rel="noopener noreferrer"
          className="btn btn-secondary"
        >
          Cetak PDF
        </a>
      </div>

      <div className="overflow-x-auto" aria-label="Odontogram">
        {sets.map((set) => {
          const rows = toothRows(set);
          const shared = {
            data,
            selectedFdi,
            selectedSurface: surface,
            firstFocusable,
            onSelect: (fdi: number, s: TargetSurface) => {
              setSelectedFdi(fdi);
              setSurface(s);
              setConditionCode('');
            },
            register: (fdi: number, node: SVGGElement | null) => {
              if (node) {
                nodes.current.set(fdi, node);
              } else {
                nodes.current.delete(fdi);
              }
            },
            onKey,
          };
          return (
            <div key={set} className="mb-4 flex flex-col gap-2">
              <Row teeth={rows.upper} {...shared} />
              <Row teeth={rows.lower} {...shared} />
            </div>
          );
        })}
      </div>

      <ul className="flex flex-wrap gap-3 text-xs" aria-label="Legenda kondisi">
        {active.map((c) => (
          <li key={c.code} className="flex items-center gap-1">
            <span
              aria-hidden
              className="border-ink inline-block h-3 w-3 border"
              style={{ backgroundColor: c.color }}
            />
            <span className="font-medium">{c.code}</span> {c.name}
          </li>
        ))}
      </ul>

      <section aria-label="Panel gigi" className="card flex flex-col gap-3 p-3">
        {selectedFdi === null ? (
          <p className="text-muted text-sm">
            Pilih gigi (klik atau tekan Enter) untuk melihat atau mengubah kondisinya.
          </p>
        ) : (
          <>
            <h3 className="text-base font-semibold">Gigi {selectedFdi}</h3>
            <p className="text-sm">{toothLabel(data, selectedFdi)}</p>
            <Select
              label="Permukaan"
              options={[
                ...(['M', 'D', 'O', 'B', 'L'] as const).map((s) => ({
                  value: s,
                  label: SURFACE_LABEL[s],
                })),
                { value: 'W', label: SURFACE_LABEL.W },
              ]}
              value={surface}
              onChange={(event) => {
                setSurface(event.target.value as TargetSurface);
                setConditionCode('');
              }}
            />
            <p className="text-muted text-sm">
              Kondisi sekarang:{' '}
              {selectedState
                ? (findCondition(data.conditions, selectedState.condition_code)?.name ??
                  selectedState.condition_code)
                : 'belum dicatat'}
              {selectedState?.in_this_encounter ? ' (diubah pada kunjungan ini)' : ''}
            </p>
            {canWrite ? (
              <>
                <Select
                  label="Kondisi baru"
                  placeholder="Pilih kondisi"
                  options={options}
                  value={conditionCode}
                  onChange={(event) => {
                    setConditionCode(event.target.value);
                  }}
                />
                {errorMessage ? <Alert tone="error">{errorMessage}</Alert> : null}
                <div>
                  <Button
                    loading={mutation.isPending}
                    onClick={apply}
                    disabled={conditionCode === ''}
                  >
                    Simpan kondisi
                  </Button>
                </div>
              </>
            ) : null}
          </>
        )}
      </section>
    </div>
  );
}
