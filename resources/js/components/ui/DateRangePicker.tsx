import { useId, useRef, useState } from 'react';

import { CalendarGrid } from '@/components/ui/CalendarGrid';
import type { MonthCursor } from '@/components/ui/CalendarGrid';
import { PickerToggle } from '@/components/ui/PickerToggle';
import { useDismiss } from '@/components/ui/useDismiss';
import {
  addDays,
  clampIso,
  compareIso,
  formatDisplay,
  inRange,
  maskDate,
  parseDisplay,
  parseIso,
  toIso,
  todayIso,
} from '@/lib/date';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

export interface DateRange {
  from: string;
  to: string;
}

interface DateRangePickerProps {
  label: string;
  value: DateRange;
  onChange: (value: DateRange) => void;
  error?: string | undefined;
  min?: string;
  max?: string;
  minYear?: number;
  maxYear?: number;
  required?: boolean;
  disabled?: boolean;
  /** Tombol pintasan (hari ini, 7 hari terakhir, bulan ini, dan seterusnya) di panel. Bawaan: ya. */
  presets?: boolean;
}

/**
 * Pemilih rentang tanggal: dua isian DD/MM/YYYY (Dari dan Sampai) dan satu kalender; klik pertama menentukan awal,
 * klik kedua menentukan akhir (otomatis ditukar bila lebih awal). Nilai ke luar YYYY-MM-DD.
 */
export function DateRangePicker({
  label,
  value,
  onChange,
  error,
  min,
  max,
  minYear,
  maxYear,
  required = false,
  disabled = false,
  presets = true,
}: DateRangePickerProps) {
  const id = useId();
  const messageId = `${id}-message`;
  const timezone = useClinicTimezone();
  const today = todayIso(timezone);
  const thisYear = parseIso(today)?.y ?? new Date().getFullYear();
  const [fromText, setFromText] = useState(formatDisplay(value.from));
  const [toText, setToText] = useState(formatDisplay(value.to));
  const [localError, setLocalError] = useState<string | undefined>(undefined);
  const [open, setOpen] = useState(false);
  const [hover, setHover] = useState<string | null>(null);
  const [cursor, setCursor] = useState<MonthCursor>({ y: thisYear, m: 1 });
  const [synced, setSynced] = useState(value);
  const wrapRef = useRef<HTMLFieldSetElement>(null);
  const fromRef = useRef<HTMLInputElement>(null);
  const lowYear = minYear ?? (min ? (parseIso(min)?.y ?? thisYear - 20) : thisYear - 20);
  const highYear = maxYear ?? (max ? (parseIso(max)?.y ?? thisYear + 5) : thisYear + 5);

  if (value.from !== synced.from || value.to !== synced.to) {
    setSynced(value);
    if ((parseDisplay(fromText) ?? '') !== value.from) {
      setFromText(formatDisplay(value.from));
    }
    if ((parseDisplay(toText) ?? '') !== value.to) {
      setToText(formatDisplay(value.to));
    }
    setLocalError(undefined);
  }

  useDismiss(wrapRef, open, () => {
    setOpen(false);
    setHover(null);
  });

  function emit(next: DateRange) {
    setSynced(next);
    onChange(next);
  }

  function validate(next: DateRange, typedFrom: string, typedTo: string) {
    if (
      (typedFrom.length === 10 && next.from === '') ||
      (typedTo.length === 10 && next.to === '')
    ) {
      setLocalError('Tanggal tidak valid atau di luar batas yang diizinkan.');
    } else if (next.from !== '' && next.to !== '' && compareIso(next.to, next.from) < 0) {
      setLocalError('Tanggal akhir tidak boleh sebelum tanggal awal.');
    } else {
      setLocalError(undefined);
    }
  }

  function parseField(text: string): string {
    const iso = text.length === 10 ? parseDisplay(text) : null;
    return iso !== null && inRange(iso, min, max) ? iso : '';
  }

  function typeFrom(raw: string) {
    const masked = maskDate(raw);
    setFromText(masked);
    const next = { from: parseField(masked), to: value.to };
    validate(next, masked, toText);
    emit(next);
  }

  function typeTo(raw: string) {
    const masked = maskDate(raw);
    setToText(masked);
    const next = { from: value.from, to: parseField(masked) };
    validate(next, fromText, masked);
    emit(next);
  }

  function apply(next: DateRange, close: boolean) {
    setFromText(formatDisplay(next.from));
    setToText(formatDisplay(next.to));
    setLocalError(undefined);
    emit(next);
    if (close) {
      setOpen(false);
      setHover(null);
      fromRef.current?.focus();
    }
  }

  function pick(iso: string) {
    if (value.from === '' || value.to !== '') {
      apply({ from: iso, to: '' }, false);
    } else if (compareIso(iso, value.from) < 0) {
      apply({ from: iso, to: value.from }, true);
    } else {
      apply({ from: value.from, to: iso }, true);
    }
  }

  function toggle() {
    if (!open) {
      const base = value.from !== '' ? value.from : clampIso(today, min, max);
      const p = parseIso(base);
      setCursor(
        p ? { y: Math.min(Math.max(p.y, lowYear), highYear), m: p.m } : { y: thisYear, m: 1 },
      );
    }
    setOpen((current) => !current);
  }

  const first = parseIso(today);
  const monthStart = first ? toIso({ ...first, d: 1 }) : today;
  const lastMonthEnd = addDays(monthStart, -1);
  const lastMonth = parseIso(lastMonthEnd);
  const presetList: { label: string; range: DateRange }[] = [
    { label: 'Hari ini', range: { from: today, to: today } },
    { label: '7 hari terakhir', range: { from: addDays(today, -6), to: today } },
    { label: '30 hari terakhir', range: { from: addDays(today, -29), to: today } },
    { label: 'Bulan ini', range: { from: monthStart, to: today } },
    {
      label: 'Bulan lalu',
      range: lastMonth
        ? { from: toIso({ ...lastMonth, d: 1 }), to: lastMonthEnd }
        : { from: today, to: today },
    },
  ];

  const message = error ?? localError;

  return (
    <fieldset ref={wrapRef} className="relative flex min-w-0 flex-col gap-1">
      <legend className="mb-1 text-sm font-semibold">
        {label}
        {required ? (
          <span aria-hidden="true" className="text-signal-700 ml-0.5">
            *
          </span>
        ) : null}
      </legend>
      <div className="flex flex-wrap items-end gap-3">
        <div className="flex flex-col gap-1">
          <label htmlFor={`${id}-from`} className="text-muted text-xs">
            Dari
          </label>
          <input
            ref={fromRef}
            id={`${id}-from`}
            type="text"
            inputMode="numeric"
            autoComplete="off"
            placeholder="DD/MM/YYYY"
            value={fromText}
            disabled={disabled}
            aria-required={required ? true : undefined}
            aria-invalid={message ? true : undefined}
            aria-describedby={message ? messageId : undefined}
            onChange={(event) => {
              typeFrom(event.target.value);
            }}
            className="field w-40"
          />
        </div>
        <div className="flex flex-col gap-1">
          <label htmlFor={`${id}-to`} className="text-muted text-xs">
            Sampai
          </label>
          <input
            id={`${id}-to`}
            type="text"
            inputMode="numeric"
            autoComplete="off"
            placeholder="DD/MM/YYYY"
            value={toText}
            disabled={disabled}
            aria-required={required ? true : undefined}
            aria-invalid={message ? true : undefined}
            aria-describedby={message ? messageId : undefined}
            onChange={(event) => {
              typeTo(event.target.value);
            }}
            className="field w-40"
          />
        </div>
        <div className="relative size-11">
          <PickerToggle
            icon="calendar"
            label={`Pilih rentang tanggal: ${label}`}
            expanded={open}
            disabled={disabled}
            onClick={toggle}
          />
        </div>
      </div>
      {message ? (
        <p id={messageId} role="alert" className="text-danger-600 text-sm">
          {message}
        </p>
      ) : null}

      {open ? (
        <div
          role="dialog"
          aria-label={`Kalender rentang: ${label}`}
          className="border-ink absolute top-full left-0 z-30 mt-1 flex w-max max-w-[calc(100vw-3rem)] flex-wrap gap-4 border bg-white p-3 shadow-[0_8px_24px_rgb(0_0_0/0.14)]"
        >
          {presets ? (
            <div role="group" aria-label="Pintasan rentang" className="flex flex-col">
              {presetList.map((preset) => (
                <button
                  key={preset.label}
                  type="button"
                  disabled={
                    !inRange(preset.range.from, min, max) || !inRange(preset.range.to, min, max)
                  }
                  onClick={() => {
                    apply(preset.range, true);
                  }}
                  className="min-h-touch hover:bg-brand-100 px-3 text-left text-sm font-medium disabled:cursor-not-allowed disabled:text-[#b0b0b0]"
                >
                  {preset.label}
                </button>
              ))}
            </div>
          ) : null}
          <div>
            <CalendarGrid
              month={cursor}
              onMonthChange={setCursor}
              today={today}
              range={value}
              hover={hover}
              onHover={setHover}
              min={min}
              max={max}
              minYear={lowYear}
              maxYear={highYear}
              onPick={pick}
              focusOnMount
            />
            <p className="text-muted mt-2 text-xs">
              {value.from !== '' && value.to === ''
                ? 'Pilih tanggal akhir.'
                : 'Pilih tanggal awal, lalu tanggal akhir.'}
            </p>
          </div>
        </div>
      ) : null}
    </fieldset>
  );
}
