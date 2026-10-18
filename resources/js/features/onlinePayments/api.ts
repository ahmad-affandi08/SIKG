import { intentStatusSchema } from '@/features/onlinePayments/schemas';
import { api } from '@/lib/http';

export async function fetchIntentStatus(invoiceId: string, intentId: string, signal?: AbortSignal) {
  const options = signal ? { signal } : {};
  const result = await api.get(
    `/internal-api/invoices/${invoiceId}/online-payments/${intentId}/status`,
    intentStatusSchema,
    options,
  );
  return result.data;
}
