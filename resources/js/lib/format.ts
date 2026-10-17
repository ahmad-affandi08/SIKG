const DEFAULT_TIMEZONE = 'Asia/Jakarta';
const DATE_ONLY = /^(\d{4})-(\d{2})-(\d{2})$/;

/** Uang selalu bilangan bulat rupiah (M4). Contoh: 1250000 menjadi "Rp 1.250.000". */
export function formatRupiah(amount: number): string {
  if (!Number.isSafeInteger(amount)) {
    throw new RangeError('Jumlah uang harus bilangan bulat rupiah.');
  }
  const digits = Math.abs(amount)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return `${amount < 0 ? '-' : ''}Rp ${digits}`;
}

function parts(
  value: Date,
  timeZone: string,
  options: Intl.DateTimeFormatOptions,
): Record<string, string> {
  const formatter = new Intl.DateTimeFormat('en-GB', { timeZone, hourCycle: 'h23', ...options });
  return Object.fromEntries(formatter.formatToParts(value).map((part) => [part.type, part.value]));
}

function toDate(value: string | Date): Date {
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) {
    throw new RangeError('Tanggal tidak valid.');
  }
  return date;
}

/** DD/MM/YYYY. Tanggal murni (YYYY-MM-DD, mis. tanggal lahir) tidak digeser zona waktu. */
export function formatDate(value: string | Date, timeZone: string = DEFAULT_TIMEZONE): string {
  if (typeof value === 'string') {
    const match = DATE_ONLY.exec(value);
    if (match) {
      return `${match[3]}/${match[2]}/${match[1]}`;
    }
  }
  const p = parts(toDate(value), timeZone, { day: '2-digit', month: '2-digit', year: 'numeric' });
  return `${p.day}/${p.month}/${p.year}`;
}

/** HH:mm, 24 jam, di zona waktu klinik. */
export function formatTime(value: string | Date, timeZone: string = DEFAULT_TIMEZONE): string {
  const p = parts(toDate(value), timeZone, { hour: '2-digit', minute: '2-digit' });
  return `${p.hour}:${p.minute}`;
}

/** DD/MM/YYYY HH:mm di zona waktu klinik. */
export function formatDateTime(value: string | Date, timeZone: string = DEFAULT_TIMEZONE): string {
  return `${formatDate(value, timeZone)} ${formatTime(value, timeZone)}`;
}
