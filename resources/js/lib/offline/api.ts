import type { QueuedRequest, SendOutcome } from '@/lib/offline/queue';

function xsrfToken(): string | null {
  const match = /(?:^|;\s*)XSRF-TOKEN=([^;]+)/.exec(document.cookie);
  const raw = match?.[1];
  return raw ? decodeURIComponent(raw) : null;
}

/**
 * Mengirim ulang satu permintaan antrean. Hasil: terkirim (2xx atau pengalihan), konflik (409), ditolak server (403, 404, 422), atau
 * coba lagi (tanpa jaringan, sesi berakhir, atau galat server). Kunci idempotensi membuat pengiriman ulang tidak ganda.
 */
export async function sendQueued(item: QueuedRequest, body: unknown): Promise<SendOutcome> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-Offline-Key': item.key,
  };
  const token = xsrfToken();
  if (token) {
    headers['X-XSRF-TOKEN'] = token;
  }
  let response: Response;
  try {
    response = await fetch(item.url, {
      method: item.method,
      headers,
      body: JSON.stringify(body),
      credentials: 'same-origin',
      redirect: 'manual',
    });
  } catch {
    return { kind: 'retry' };
  }
  if (
    response.ok ||
    response.type === 'opaqueredirect' ||
    (response.status >= 300 && response.status < 400)
  ) {
    return { kind: 'sent' };
  }
  if (response.status === 409) {
    return {
      kind: 'conflict',
      message: 'Data kunjungan sudah berubah di server. Periksa lalu kirim ulang atau buang.',
    };
  }
  if (
    response.status === 401 ||
    response.status === 419 ||
    response.status >= 500 ||
    response.status === 429
  ) {
    return { kind: 'retry' };
  }
  let message = 'Ditolak server.';
  try {
    const json = (await response.json()) as { message?: string };
    if (typeof json.message === 'string' && json.message !== '') {
      message = json.message;
    }
  } catch {
    // Badan bukan JSON; pakai pesan bawaan.
  }
  return { kind: 'rejected', message };
}
