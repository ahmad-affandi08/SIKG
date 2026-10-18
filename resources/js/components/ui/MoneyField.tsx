import type { ChangeEvent, ComponentProps } from 'react';
import { useLayoutEffect, useRef } from 'react';

import { TextField } from '@/components/ui/TextField';
import { formatRupiah } from '@/lib/format';

/** Batas 15 angka menjaga nilai tetap bilangan bulat aman (M4). */
const MAX_DIGITS = 15;
const PREFIX = 'Rp ';

type TextFieldProps = ComponentProps<typeof TextField>;

interface MoneyFieldProps extends Omit<
  TextFieldProps,
  'value' | 'onChange' | 'inputMode' | 'type' | 'defaultValue'
> {
  /** Hanya angka (bilangan bulat rupiah), mis. "1250000"; kosong berarti belum diisi. */
  value: string;
  onValueChange: (digits: string) => void;
}

/** "Rp 1.250.000" untuk "1250000"; kosong tetap kosong. */
export function displayRupiah(digits: string): string {
  return digits === '' ? '' : formatRupiah(Number(digits));
}

/** Ambil angka saja, buang nol di depan, dan batasi panjangnya. */
export function digitsOnly(text: string): string {
  const raw = text.replace(/\D/g, '').slice(0, MAX_DIGITS);
  const trimmed = raw.replace(/^0+(?=\d)/, '');
  return trimmed;
}

/** Posisi kursor di teks terformat setelah `count` angka. */
function caretAfterDigits(formatted: string, count: number): number {
  if (formatted === '') {
    return 0;
  }
  if (count <= 0) {
    return PREFIX.length;
  }
  let seen = 0;
  for (let i = PREFIX.length; i < formatted.length; i += 1) {
    if (/\d/.test(formatted.charAt(i))) {
      seen += 1;
      if (seen === count) {
        return i + 1;
      }
    }
  }
  return formatted.length;
}

/**
 * Isian uang: tampil langsung sebagai "Rp 1.250.000" selama diketik, sementara nilai yang dilaporkan ke formulir
 * tetap berupa angka bulat polos ("1250000"), sehingga skema Zod dan pengiriman ke server tidak berubah.
 */
export function MoneyField({ value, onValueChange, placeholder, ...rest }: MoneyFieldProps) {
  const pending = useRef<{ input: HTMLInputElement; position: number } | null>(null);

  useLayoutEffect(() => {
    const next = pending.current;
    if (next) {
      pending.current = null;
      next.input.setSelectionRange(next.position, next.position);
    }
  });

  function handleChange(event: ChangeEvent<HTMLInputElement>) {
    const input = event.target;
    const caret = input.selectionStart ?? input.value.length;
    const digitsBefore = digitsOnly(input.value.slice(0, caret)).length;
    const digits = digitsOnly(input.value);
    pending.current = {
      input,
      position: caretAfterDigits(displayRupiah(digits), Math.min(digitsBefore, digits.length)),
    };
    onValueChange(digits);
  }

  return (
    <TextField
      {...rest}
      type="text"
      inputMode="numeric"
      autoComplete="off"
      placeholder={placeholder ?? 'Rp 0'}
      value={displayRupiah(value === '' ? '' : digitsOnly(value))}
      onChange={handleChange}
    />
  );
}
