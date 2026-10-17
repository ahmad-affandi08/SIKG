import { useId, useRef, useState } from 'react';

import { FieldLabel } from '@/components/ui/FieldLabel';
import { PickerToggle } from '@/components/ui/PickerToggle';
import { useDismiss } from '@/components/ui/useDismiss';
import { maskTime, parseTime, toTime } from '@/lib/date';

interface TimePickerProps {
  label: string;
  /** HH:MM 24 jam, atau kosong. */
  value: string;
  onChange: (value: string) => void;
  error?: string | undefined;
  /** Selang menit pada pilihan di panel (mengetik tetap boleh menit berapa pun). */
  step?: number;
  required?: boolean;
  disabled?: boolean;
  hint?: string;
}

const HOURS = Array.from({ length: 24 }, (_, h) => h);

/** Pemilih jam 24 jam: ketik HH:MM (titik dua otomatis) atau pilih jam lalu menit dari panel. */
export function TimePicker({
  label,
  value,
  onChange,
  error,
  step = 5,
  required = false,
  disabled = false,
  hint,
}: TimePickerProps) {
  const id = useId();
  const messageId = `${id}-message`;
  const [text, setText] = useState(value);
  const [localError, setLocalError] = useState<string | undefined>(undefined);
  const [open, setOpen] = useState(false);
  const [synced, setSynced] = useState(value);
  const wrapRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const minutes = Array.from({ length: Math.ceil(60 / step) }, (_, i) => i * step);
  const current = parseTime(value);

  if (value !== synced) {
    setSynced(value);
    if ((parseTime(text) ? text : '') !== value) {
      setText(value);
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
    const masked = maskTime(raw);
    setText(masked);
    setLocalError(undefined);
    if (masked.length < 5) {
      emit('');
      return;
    }
    if (parseTime(masked)) {
      emit(masked);
    } else {
      emit('');
      setLocalError('Jam tidak valid (00:00 sampai 23:59).');
    }
  }

  function choose(h: number, m: number, close: boolean) {
    const next = toTime({ h, m });
    setText(next);
    setLocalError(undefined);
    emit(next);
    if (close) {
      setOpen(false);
      inputRef.current?.focus();
    }
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
          placeholder="JJ:MM"
          value={text}
          disabled={disabled}
          aria-required={required ? true : undefined}
          aria-invalid={message ? true : undefined}
          aria-describedby={message || hint ? messageId : undefined}
          onChange={(event) => {
            type(event.target.value);
          }}
          onBlur={() => {
            if (text !== '' && text.length < 5) {
              setLocalError('Isi jam dengan format JJ:MM.');
            }
          }}
          className="field w-full pr-12"
        />
        <PickerToggle
          icon="clock"
          label={`Pilih jam: ${label}`}
          expanded={open}
          disabled={disabled}
          onClick={() => {
            setOpen((v) => !v);
          }}
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
          aria-label={`Pilih jam: ${label}`}
          className="border-ink absolute top-full left-0 z-30 mt-1 w-max border bg-white p-3 shadow-[0_8px_24px_rgb(0_0_0/0.14)]"
        >
          <p className="eyebrow pb-1">Jam</p>
          <div role="group" aria-label="Jam" className="grid grid-cols-6">
            {HOURS.map((h) => (
              <button
                key={h}
                type="button"
                aria-pressed={current?.h === h}
                onClick={() => {
                  choose(h, current?.m ?? 0, false);
                }}
                className={`size-11 text-sm tabular-nums ${current?.h === h ? 'bg-ink font-semibold text-white' : 'hover:bg-brand-100'}`}
              >
                {String(h).padStart(2, '0')}
              </button>
            ))}
          </div>
          <p className="eyebrow pt-3 pb-1">Menit</p>
          <div role="group" aria-label="Menit" className="grid grid-cols-6">
            {minutes.map((m) => (
              <button
                key={m}
                type="button"
                aria-pressed={current?.m === m}
                onClick={() => {
                  choose(current?.h ?? 8, m, true);
                }}
                className={`size-11 text-sm tabular-nums ${current?.m === m ? 'bg-ink font-semibold text-white' : 'hover:bg-brand-100'}`}
              >
                {String(m).padStart(2, '0')}
              </button>
            ))}
          </div>
        </div>
      ) : null}
    </div>
  );
}
