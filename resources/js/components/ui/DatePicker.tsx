import { useId, useRef, useState } from 'react';

import { CalendarGrid } from '@/components/ui/CalendarGrid';
import type { MonthCursor } from '@/components/ui/CalendarGrid';
import { FieldLabel } from '@/components/ui/FieldLabel';
import { PickerToggle } from '@/components/ui/PickerToggle';
import { useDismiss } from '@/components/ui/useDismiss';
import {
  clampIso,
  formatDisplay,
  inRange,
  maskDate,
  parseDisplay,
  parseIso,
  todayIso,
} from '@/lib/date';
import { useClinicTimezone } from '@/lib/useClinicTimezone';

interface DatePickerProps {
  label: string;
  /** YYYY-MM-DD, atau kosong. */
  value: string;
  onChange: (value: string) => void;
  error?: string | undefined;
  /** Batas tanggal yang boleh dipilih (YYYY-MM-DD). */
  min?: string;
  max?: string;
  /** Rentang tahun pada pilihan tahun; bawaannya 120 tahun ke belakang dan 20 tahun ke depan. */
  minYear?: number;
  maxYear?: number;
  required?: boolean;
  disabled?: boolean;
  /** Tampilkan tombol Hapus di panel (bawaan ya; matikan bila isian wajib). */
  clearable?: boolean;
  hint?: string;
}

/**
 * Pemilih tanggal: ketik DD/MM/YYYY (garis miring otomatis) atau pilih dari kalender. Nilai ke luar selalu
 * YYYY-MM-DD, atau kosong selama isian belum berupa tanggal sah dalam batas min dan max.
 */
export function DatePicker({
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
  clearable = true,
  hint,
}: DatePickerProps) {
  const id = useId();
  const messageId = `${id}-message`;
  const timezone = useClinicTimezone();
  const today = todayIso(timezone);
  const thisYear = parseIso(today)?.y ?? new Date().getFullYear();
  const [text, setText] = useState(formatDisplay(value));
  const [localError, setLocalError] = useState<string | undefined>(undefined);
  const [open, setOpen] = useState(false);
  const [cursor, setCursor] = useState<MonthCursor>({ y: thisYear, m: 1 });
  const wrapRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const [synced, setSynced] = useState(value);
  const lowYear = minYear ?? (min ? (parseIso(min)?.y ?? thisYear - 120) : thisYear - 120);
  const highYear = maxYear ?? (max ? (parseIso(max)?.y ?? thisYear + 20) : thisYear + 20);

  // Nilai dari luar (mis. form di-reset) menimpa teks hanya bila berbeda dari yang sedang diketik.
  if (value !== synced) {
    setSynced(value);
    if ((parseDisplay(text) ?? '') !== value) {
      setText(formatDisplay(value));
      setLocalError(undefined);
    }
  }

  useDismiss(wrapRef, open, () => {
    setOpen(false);
  });

  function emit(next: string) {
    setSynced(next);
    onChange(next);
  }

  function type(raw: string) {
    const masked = maskDate(raw);
    setText(masked);
    setLocalError(undefined);
    if (masked.length < 10) {
      emit('');
      return;
    }
    const iso = parseDisplay(masked);
    if (iso === null) {
      emit('');
      setLocalError('Tanggal tidak valid.');
    } else if (!inRange(iso, min, max)) {
      emit('');
      setLocalError('Tanggal di luar batas yang diizinkan.');
    } else {
      emit(iso);
    }
  }

  function toggle() {
    if (!open) {
      const base = value !== '' ? value : clampIso(today, min, max);
      const p = parseIso(base);
      setCursor(
        p ? { y: Math.min(Math.max(p.y, lowYear), highYear), m: p.m } : { y: thisYear, m: 1 },
      );
    }
    setOpen((current) => !current);
  }

  function pick(iso: string) {
    setText(formatDisplay(iso));
    setLocalError(undefined);
    emit(iso);
    setOpen(false);
    inputRef.current?.focus();
  }

  const message = error ?? localError;

  return (
    <div ref={wrapRef} className="relative flex flex-col gap-1">
      <FieldLabel htmlFor={id} required={required}>
        {label}
      </FieldLabel>
      <div className="relative">
        <input
          ref={inputRef}
          id={id}
          type="text"
          inputMode="numeric"
          autoComplete="off"
          placeholder="DD/MM/YYYY"
          value={text}
          disabled={disabled}
          aria-required={required ? true : undefined}
          aria-invalid={message ? true : undefined}
          aria-describedby={message || hint ? messageId : undefined}
          onChange={(event) => {
            type(event.target.value);
          }}
          onBlur={() => {
            if (text !== '' && text.length < 10) {
              setLocalError('Isi tanggal dengan format DD/MM/YYYY.');
            }
          }}
          className="field w-full pr-12"
        />
        <PickerToggle
          icon="calendar"
          label={`Pilih tanggal: ${label}`}
          expanded={open}
          disabled={disabled}
          onClick={toggle}
        />
      </div>
      {message ? (
        <p id={messageId} role="alert" className="text-danger-600 text-sm">
          {message}
        </p>
      ) : hint ? (
        <p id={messageId} className="text-muted text-sm">
          {hint}
        </p>
      ) : null}

      {open ? (
        <div
          role="dialog"
          aria-label={`Kalender: ${label}`}
          className="border-ink absolute top-full left-0 z-30 mt-1 w-max border bg-white p-3 shadow-[0_8px_24px_rgb(0_0_0/0.14)]"
        >
          <CalendarGrid
            month={cursor}
            onMonthChange={setCursor}
            today={today}
            selected={value}
            min={min}
            max={max}
            minYear={lowYear}
            maxYear={highYear}
            onPick={pick}
            focusOnMount
          />
          <div className="mt-2 flex justify-between border-t border-[#ddd] pt-2">
            <button
              type="button"
              className="btn btn-secondary"
              disabled={!inRange(today, min, max)}
              onClick={() => {
                pick(today);
              }}
            >
              Hari ini
            </button>
            {clearable ? (
              <button
                type="button"
                className="btn btn-secondary"
                onClick={() => {
                  setText('');
                  setLocalError(undefined);
                  emit('');
                  setOpen(false);
                  inputRef.current?.focus();
                }}
              >
                Hapus
              </button>
            ) : null}
          </div>
        </div>
      ) : null}
    </div>
  );
}
