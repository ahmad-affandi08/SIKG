import { describe, expect, it } from 'vitest';

import {
  cashClosingSchema,
  discountSchema,
  invoiceItemSchema,
  paymentSchema,
  percentText,
  refundSchema,
} from '@/features/billing/schemas';

describe('KSR uang bilangan bulat rupiah (M4)', () => {
  it('KSR-01 harga item menolak desimal dan pemisah, menerima nol dan bilangan bulat', () => {
    const base = { description: 'Biaya administrasi', quantity: '1' };
    expect(invoiceItemSchema.safeParse({ ...base, unit_price_amount: '20000' }).success).toBe(true);
    expect(invoiceItemSchema.safeParse({ ...base, unit_price_amount: '0' }).success).toBe(true);
    expect(invoiceItemSchema.safeParse({ ...base, unit_price_amount: '1500.50' }).success).toBe(
      false,
    );
    expect(invoiceItemSchema.safeParse({ ...base, unit_price_amount: '1.500' }).success).toBe(
      false,
    );
    expect(invoiceItemSchema.safeParse({ ...base, unit_price_amount: '' }).success).toBe(false);
  });

  it('KSR-01 jumlah item 1 sampai 9999 dan uraian wajib', () => {
    const ok = { description: 'Item', unit_price_amount: '1000' };
    expect(invoiceItemSchema.safeParse({ ...ok, quantity: '0' }).success).toBe(false);
    expect(invoiceItemSchema.safeParse({ ...ok, quantity: '10000' }).success).toBe(false);
    expect(invoiceItemSchema.safeParse({ ...ok, quantity: '9999' }).success).toBe(true);
    expect(invoiceItemSchema.safeParse({ ...ok, description: ' ', quantity: '1' }).success).toBe(
      false,
    );
  });

  it('KSR-03 pembayaran wajib metode dan jumlah lebih dari nol', () => {
    const ok = { payment_method_id: 'M1', type: 'pay', amount: '1000', reference_no: '' };
    expect(paymentSchema.safeParse(ok).success).toBe(true);
    expect(paymentSchema.safeParse({ ...ok, amount: '0' }).success).toBe(false);
    expect(paymentSchema.safeParse({ ...ok, payment_method_id: '' }).success).toBe(false);
    expect(paymentSchema.safeParse({ ...ok, type: 'refund' }).success).toBe(false);
  });

  it('KSR-07 refund wajib beralasan', () => {
    const ok = { payment_method_id: 'M1', amount: '5000', reason: 'Tindakan dibatalkan' };
    expect(refundSchema.safeParse(ok).success).toBe(true);
    expect(refundSchema.safeParse({ ...ok, reason: ' ' }).success).toBe(false);
  });

  it('KSR-08 uang fisik boleh nol tetapi tidak desimal', () => {
    expect(cashClosingSchema.safeParse({ counted_amount: '0', note: '' }).success).toBe(true);
    expect(cashClosingSchema.safeParse({ counted_amount: '10.5', note: '' }).success).toBe(false);
  });
});

describe('KSR-02 diskon', () => {
  it('persen berformat 10 atau 12,5 dan alasan wajib', () => {
    expect(
      discountSchema.safeParse({ kind: 'percent', value: '12,5', reason: 'Promo' }).success,
    ).toBe(true);
    expect(
      discountSchema.safeParse({ kind: 'percent', value: '12,555', reason: 'Promo' }).success,
    ).toBe(false);
    expect(discountSchema.safeParse({ kind: 'percent', value: '10', reason: '' }).success).toBe(
      false,
    );
  });

  it('nominal bilangan bulat dan hapus diskon tanpa nilai atau alasan', () => {
    expect(
      discountSchema.safeParse({ kind: 'amount', value: '5000', reason: 'Promo' }).success,
    ).toBe(true);
    expect(
      discountSchema.safeParse({ kind: 'amount', value: '50.5', reason: 'Promo' }).success,
    ).toBe(false);
    expect(discountSchema.safeParse({ kind: 'none', value: '', reason: '' }).success).toBe(true);
  });

  it('percentText menampilkan basis poin sebagai persen', () => {
    expect(percentText(1000)).toBe('10%');
    expect(percentText(1250)).toBe('12,5%');
    expect(percentText(1205)).toBe('12,05%');
  });
});
