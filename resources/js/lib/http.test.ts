import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { z } from 'zod';

import { api, HttpError, setUnauthorizedHandler } from '@/lib/http';

const patientSchema = z.object({ id: z.string(), name: z.string() });

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

describe('lib/http (kontrak /internal-api)', () => {
  const fetchMock = vi.fn<typeof fetch>();
  const unauthorized = vi.fn();

  beforeEach(() => {
    vi.stubGlobal('fetch', fetchMock);
    setUnauthorizedHandler(unauthorized);
    document.cookie = 'XSRF-TOKEN=token%2Brahasia; path=/';
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    fetchMock.mockReset();
    unauthorized.mockReset();
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
  });

  it('memparse respons {data, meta} dengan skema Zod', async () => {
    fetchMock.mockResolvedValue(
      jsonResponse({ data: { id: '01J', name: 'Pasien Uji' }, meta: { total: 1 } }),
    );

    const result = await api.get('/internal-api/patients/01J', patientSchema);

    expect(result.data).toEqual({ id: '01J', name: 'Pasien Uji' });
    expect(result.meta).toEqual({ total: 1 });
  });

  it('menerima meta kosong yang dikirim PHP sebagai array kosong', async () => {
    fetchMock.mockResolvedValue(
      jsonResponse({ data: { id: '01J', name: 'Pasien Uji' }, meta: [] }),
    );

    const result = await api.get('/internal-api/patients/01J', patientSchema);

    expect(result.meta).toEqual({});
  });

  it('mengirim cookie sesi, CSRF, dan Idempotency-Key tanpa PHI di URL', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ data: { id: '1', name: 'x' } }));

    await api.post('/internal-api/payments', patientSchema, {
      body: { amount: 1000 },
      idempotencyKey: 'kunci-1',
    });

    const [url, init] = fetchMock.mock.calls[0] ?? [];
    expect(url).toBe('/internal-api/payments');
    expect(init?.method).toBe('POST');
    expect(init?.credentials).toBe('same-origin');
    const headers = init?.headers as Record<string, string>;
    expect(headers['X-XSRF-TOKEN']).toBe('token+rahasia');
    expect(headers['Idempotency-Key']).toBe('kunci-1');
    expect(init?.body).toBe(JSON.stringify({ amount: 1000 }));
  });

  it('membangun query dan membuang nilai kosong', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ data: { id: '1', name: 'x' } }));

    await api.get('/internal-api/patients/search', patientSchema, {
      query: { q: 'budi', page: 2, kosong: '', tidak: undefined },
    });

    expect(fetchMock.mock.calls[0]?.[0]).toBe('/internal-api/patients/search?q=budi&page=2');
  });

  it('401 mengarahkan ke login dan melempar galat berbahasa Indonesia', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ message: 'Unauthenticated.' }, 401));

    await expect(api.get('/internal-api/queue', patientSchema)).rejects.toMatchObject({
      status: 401,
      message: 'Sesi berakhir. Silakan masuk kembali.',
    });
    expect(unauthorized).toHaveBeenCalledOnce();
  });

  it('403 dan 419 memakai pesan Indonesia, bukan pesan mentah server', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ message: 'This action is unauthorized.' }, 403));
    fetchMock.mockResolvedValueOnce(jsonResponse({ message: 'CSRF token mismatch.' }, 419));

    await expect(api.get('/x', patientSchema)).rejects.toMatchObject({
      status: 403,
      message: 'Anda tidak memiliki izin untuk aksi ini.',
    });
    await expect(api.get('/x', patientSchema)).rejects.toMatchObject({
      status: 419,
      message: 'Halaman sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.',
    });
  });

  it('409 ditandai konflik versi dengan kode bisnis stabil', async () => {
    fetchMock.mockResolvedValue(
      jsonResponse({ message: 'Version conflict', code: 'appointment.conflict' }, 409),
    );

    const error = await api.put('/internal-api/x', patientSchema).catch((e: unknown) => e);

    expect(error).toBeInstanceOf(HttpError);
    expect(error).toMatchObject({ status: 409, code: 'appointment.conflict', isConflict: true });
  });

  it('422 membawa galat per bidang', async () => {
    fetchMock.mockResolvedValue(
      jsonResponse({ message: 'Data tidak valid.', errors: { nik: ['NIK wajib diisi.'] } }, 422),
    );

    const error = await api.post('/internal-api/x', patientSchema).catch((e: unknown) => e);

    expect(error).toMatchObject({
      status: 422,
      isValidation: true,
      errors: { nik: ['NIK wajib diisi.'] },
    });
  });

  it('respons yang tidak cocok skema dilaporkan sebagai galat, bukan diabaikan', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ data: { id: 123 } }));

    await expect(api.get('/x', patientSchema)).rejects.toMatchObject({ code: 'response.invalid' });
  });

  it('jaringan putus menghasilkan pesan Indonesia', async () => {
    fetchMock.mockRejectedValue(new TypeError('Failed to fetch'));

    await expect(api.get('/x', patientSchema)).rejects.toMatchObject({
      status: 0,
      code: 'network.unreachable',
    });
  });
});
