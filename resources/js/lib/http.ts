import { z } from 'zod';

/**
 * Satu-satunya pemanggil `fetch` di aplikasi (docs/rules/frontend.md). Dipakai oleh features/<konteks>/api.ts.
 * Memakai cookie sesi dan CSRF yang sama dengan Inertia; tidak ada token publik.
 */

const MESSAGES: Record<number, string> = {
  401: 'Sesi berakhir. Silakan masuk kembali.',
  403: 'Anda tidak memiliki izin untuk aksi ini.',
  404: 'Data tidak ditemukan.',
  409: 'Data sudah diubah pengguna lain. Muat ulang untuk melihat versi terbaru.',
  419: 'Halaman sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.',
  422: 'Data yang dikirim tidak valid.',
  429: 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.',
};
const SERVER_ERROR_MESSAGE = 'Terjadi kesalahan pada server. Coba lagi beberapa saat.';
const NETWORK_ERROR_MESSAGE = 'Tidak dapat terhubung ke server. Periksa koneksi internet klinik.';
const CONTRACT_ERROR_MESSAGE = 'Respons server tidak sesuai kontrak.';

export class HttpError extends Error {
  readonly status: number;
  /** Kode galat bisnis yang stabil, contoh `appointment.conflict`. */
  readonly code: string | null;
  /** Galat validasi 422 per bidang. */
  readonly errors: Record<string, string[]>;

  constructor(
    status: number,
    message: string,
    code: string | null = null,
    errors: Record<string, string[]> = {},
  ) {
    super(message);
    this.name = 'HttpError';
    this.status = status;
    this.code = code;
    this.errors = errors;
  }

  get isConflict(): boolean {
    return this.status === 409;
  }

  get isValidation(): boolean {
    return this.status === 422;
  }
}

const errorBodySchema = z.object({
  message: z.string().optional(),
  code: z.string().optional(),
  errors: z.record(z.string(), z.array(z.string())).optional(),
});

const metaSchema = z.record(z.string(), z.unknown());

let onUnauthorized: () => void = () => {
  window.location.assign('/login');
};

/** Untuk tes dan pengaturan khusus; bawaannya mengarahkan ke halaman masuk. */
export function setUnauthorizedHandler(handler: () => void): void {
  onUnauthorized = handler;
}

function xsrfToken(): string | null {
  const match = /(?:^|;\s*)XSRF-TOKEN=([^;]+)/.exec(document.cookie);
  const raw = match?.[1];
  return raw ? decodeURIComponent(raw) : null;
}

type Query = Record<string, string | number | boolean | null | undefined>;

export interface RequestOptions {
  query?: Query;
  body?: unknown;
  signal?: AbortSignal;
  /** Wajib untuk pembayaran agar klik ganda tidak membuat dua transaksi (M7). */
  idempotencyKey?: string;
}

export interface ApiResult<T> {
  data: T;
  meta: Record<string, unknown>;
}

function buildUrl(path: string, query?: Query): string {
  if (!query) {
    return path;
  }
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (value !== null && value !== undefined && value !== '') {
      params.set(key, String(value));
    }
  }
  const qs = params.toString();
  return qs ? `${path}?${qs}` : path;
}

async function toError(response: Response): Promise<HttpError> {
  let parsed: z.infer<typeof errorBodySchema> = {};
  try {
    const result = errorBodySchema.safeParse(await response.json());
    if (result.success) {
      parsed = result.data;
    }
  } catch {
    // Badan bukan JSON; pakai pesan bawaan.
  }
  const fallback =
    response.status >= 500
      ? SERVER_ERROR_MESSAGE
      : (MESSAGES[response.status] ?? SERVER_ERROR_MESSAGE);
  const message = response.status === 422 || parsed.code ? (parsed.message ?? fallback) : fallback;
  return new HttpError(response.status, message, parsed.code ?? null, parsed.errors ?? {});
}

export async function request<S extends z.ZodType>(
  method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE',
  path: string,
  schema: S,
  options: RequestOptions = {},
): Promise<ApiResult<z.output<S>>> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  };
  const token = xsrfToken();
  if (token) {
    headers['X-XSRF-TOKEN'] = token;
  }
  if (options.idempotencyKey) {
    headers['Idempotency-Key'] = options.idempotencyKey;
  }
  const init: RequestInit = { method, headers, credentials: 'same-origin' };
  if (options.signal) {
    init.signal = options.signal;
  }
  if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json';
    init.body = JSON.stringify(options.body);
  }

  let response: Response;
  try {
    response = await fetch(buildUrl(path, options.query), init);
  } catch (cause) {
    if (cause instanceof DOMException && cause.name === 'AbortError') {
      throw cause;
    }
    throw new HttpError(0, NETWORK_ERROR_MESSAGE, 'network.unreachable');
  }

  if (!response.ok) {
    if (response.status === 401) {
      onUnauthorized();
    }
    throw await toError(response);
  }

  const envelope = z
    .object({ data: z.unknown(), meta: metaSchema.optional() })
    .safeParse(await response.json());
  const parsed = envelope.success ? schema.safeParse(envelope.data.data) : null;
  if (!envelope.success || !parsed?.success) {
    throw new HttpError(response.status, CONTRACT_ERROR_MESSAGE, 'response.invalid');
  }
  return { data: parsed.data, meta: envelope.data.meta ?? {} };
}

export const api = {
  get: <S extends z.ZodType>(path: string, schema: S, options?: RequestOptions) =>
    request('GET', path, schema, options),
  post: <S extends z.ZodType>(path: string, schema: S, options?: RequestOptions) =>
    request('POST', path, schema, options),
  put: <S extends z.ZodType>(path: string, schema: S, options?: RequestOptions) =>
    request('PUT', path, schema, options),
  patch: <S extends z.ZodType>(path: string, schema: S, options?: RequestOptions) =>
    request('PATCH', path, schema, options),
  delete: <S extends z.ZodType>(path: string, schema: S, options?: RequestOptions) =>
    request('DELETE', path, schema, options),
};
