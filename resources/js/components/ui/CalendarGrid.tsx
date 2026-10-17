import { useEffect, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';

import {
  addDays,
  addMonths,
  clampIso,
  compareIso,
  inRange,
  MONTHS_ID,
  monthGrid,
  parseIso,
  toIso,
  weekdayIndex,
  WEEKDAYS_ID,
} from '@/lib/date';

export interface MonthCursor {
  y: number;
  m: number;
}

interface CalendarGridProps {
  month: MonthCursor;
  onMonthChange: (month: MonthCursor) => void;
  today: string;
  /** Mode satu tanggal. */
  selected?: string;
  /** Mode rentang; from atau to boleh kosong selama rentang belum lengkap. */
  range?: { from: string; to: string };
  /** Tanggal yang sedang disorot untuk pratinjau rentang. */
  hover?: string | null;
  onHover?: (iso: string | null) => void;
  min?: string | undefined;
  max?: string | undefined;
  minYear: number;
  maxYear: number;
  onPick: (iso: string) => void;
  focusOnMount?: boolean;
}

function sameMonth(iso: string, month: MonthCursor): boolean {
  const p = parseIso(iso);
  return p !== null && p.y === month.y && p.m === month.m;
}

/**
 * Kisi kalender dengan navigasi bulan dan tahun serta papan ketik: panah (hari dan pekan), Home dan End (awal dan
 * akhir pekan), PageUp dan PageDown (bulan), Shift dengan PageUp dan PageDown (tahun).
 */
export function CalendarGrid({
  month,
  onMonthChange,
  today,
  selected,
  range,
  hover,
  onHover,
  min,
  max,
  minYear,
  maxYear,
  onPick,
  focusOnMount = false,
}: CalendarGridProps) {
  const rootRef = useRef<HTMLDivElement>(null);
  const pendingFocus = useRef(focusOnMount);
  const anchor = selected || range?.from || today;
  const [focusIso, setFocusIso] = useState(anchor);
  const first = toIso({ y: month.y, m: month.m, d: 1 });
  const activeIso = sameMonth(focusIso, month) ? focusIso : clampIso(first, min, max);
  const weeks = monthGrid(month.y, month.m);

  useEffect(() => {
    if (pendingFocus.current) {
      pendingFocus.current = false;
      rootRef.current?.querySelector<HTMLButtonElement>(`[data-iso="${activeIso}"]`)?.focus();
    }
  });

  function move(next: string) {
    const bounded = clampIso(next, min, max);
    const p = parseIso(bounded);
    if (!p || p.y < minYear || p.y > maxYear) {
      return;
    }
    pendingFocus.current = true;
    setFocusIso(bounded);
    if (p.y !== month.y || p.m !== month.m) {
      onMonthChange({ y: p.y, m: p.m });
    }
  }

  function onKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    const steps: Record<string, number> = {
      ArrowLeft: -1,
      ArrowRight: 1,
      ArrowUp: -7,
      ArrowDown: 7,
    };
    let next: string | null = null;
    if (event.key in steps) {
      next = addDays(activeIso, steps[event.key] ?? 0);
    } else if (event.key === 'Home') {
      next = addDays(activeIso, -weekdayIndex(activeIso));
    } else if (event.key === 'End') {
      next = addDays(activeIso, 6 - weekdayIndex(activeIso));
    } else if (event.key === 'PageUp') {
      next = addMonths(activeIso, event.shiftKey ? -12 : -1);
    } else if (event.key === 'PageDown') {
      next = addMonths(activeIso, event.shiftKey ? 12 : 1);
    }
    if (next !== null) {
      event.preventDefault();
      move(next);
    }
  }

  function shiftMonth(delta: number) {
    const target = parseIso(addMonths(first, delta));
    if (target && target.y >= minYear && target.y <= maxYear) {
      onMonthChange({ y: target.y, m: target.m });
    }
  }

  const years: number[] = [];
  for (let y = minYear; y <= maxYear; y += 1) {
    years.push(y);
  }
  const rangeEnd = range ? range.to || (range.from && hover ? hover : '') : '';
  const lo =
    range?.from && rangeEnd ? (compareIso(range.from, rangeEnd) <= 0 ? range.from : rangeEnd) : '';
  const hi =
    range?.from && rangeEnd ? (compareIso(range.from, rangeEnd) <= 0 ? rangeEnd : range.from) : '';

  return (
    <div ref={rootRef}>
      <div className="mb-2 flex items-center gap-1">
        <button
          type="button"
          aria-label="Bulan sebelumnya"
          onClick={() => {
            shiftMonth(-1);
          }}
          className="hover:bg-brand-100 flex size-11 items-center justify-center text-lg"
        >
          <span aria-hidden="true">‹</span>
        </button>
        <select
          aria-label="Bulan"
          value={month.m}
          onChange={(event) => {
            onMonthChange({ y: month.y, m: Number(event.target.value) });
          }}
          className="field min-w-0 flex-1 bg-transparent text-sm font-semibold"
        >
          {MONTHS_ID.map((name, index) => (
            <option key={name} value={index + 1}>
              {name}
            </option>
          ))}
        </select>
        <select
          aria-label="Tahun"
          value={month.y}
          onChange={(event) => {
            onMonthChange({ y: Number(event.target.value), m: month.m });
          }}
          className="field w-24 bg-transparent text-sm font-semibold"
        >
          {years.map((y) => (
            <option key={y} value={y}>
              {y}
            </option>
          ))}
        </select>
        <button
          type="button"
          aria-label="Bulan berikutnya"
          onClick={() => {
            shiftMonth(1);
          }}
          className="hover:bg-brand-100 flex size-11 items-center justify-center text-lg"
        >
          <span aria-hidden="true">›</span>
        </button>
      </div>

      <div
        role="grid"
        tabIndex={-1}
        onKeyDown={onKeyDown}
        aria-label={`${MONTHS_ID[month.m - 1] ?? ''} ${String(month.y)}`}
        className="outline-none"
      >
        <div role="row" className="grid grid-cols-7">
          {WEEKDAYS_ID.map((day) => (
            <div
              key={day.short}
              role="columnheader"
              aria-label={day.long}
              className="text-muted py-1 text-center text-[11px] font-semibold tracking-wider uppercase"
            >
              {day.short}
            </div>
          ))}
        </div>
        {weeks.map((week) => (
          <div key={week[0]?.iso} role="row" className="grid grid-cols-7">
            {week.map(({ iso, inMonth }) => {
              const p = parseIso(iso);
              const enabled = inRange(iso, min, max);
              const isSelected = iso === selected || iso === range?.from || iso === range?.to;
              const inside = lo !== '' && compareIso(iso, lo) > 0 && compareIso(iso, hi) < 0;
              const classes = [
                'size-11 text-sm tabular-nums',
                isSelected ? 'bg-ink font-semibold text-white' : '',
                !isSelected && inside ? 'bg-brand-100' : '',
                !isSelected && !inside && enabled ? 'hover:bg-brand-100' : '',
                !inMonth && !isSelected ? 'text-[#767676]' : '',
                !enabled ? 'cursor-not-allowed text-[#b0b0b0] line-through' : '',
                iso === today && !isSelected
                  ? 'underline decoration-signal decoration-2 underline-offset-4'
                  : '',
              ]
                .filter(Boolean)
                .join(' ');
              return (
                <div key={iso} role="gridcell" aria-selected={isSelected}>
                  <button
                    type="button"
                    data-iso={iso}
                    tabIndex={iso === activeIso ? 0 : -1}
                    disabled={!enabled}
                    aria-label={
                      p ? `${String(p.d)} ${MONTHS_ID[p.m - 1] ?? ''} ${String(p.y)}` : iso
                    }
                    aria-current={iso === today ? 'date' : undefined}
                    className={classes}
                    onClick={() => {
                      setFocusIso(iso);
                      onPick(iso);
                    }}
                    onMouseEnter={() => onHover?.(iso)}
                    onMouseLeave={() => onHover?.(null)}
                  >
                    {p?.d}
                  </button>
                </div>
              );
            })}
          </div>
        ))}
      </div>
    </div>
  );
}
