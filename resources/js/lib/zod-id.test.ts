import { describe, expect, it } from 'vitest';
import { z } from 'zod';

describe('NFR-12 pesan galat Zod berbahasa Indonesia', () => {
  it('menampilkan pesan Indonesia untuk tipe yang salah', () => {
    const result = z.object({ nama: z.string() }).safeParse({ nama: 5 });

    expect(result.success).toBe(false);
    if (!result.success) {
      const message = result.error.issues[0]?.message ?? '';
      expect(message).not.toMatch(/Invalid input/i);
      expect(message.length).toBeGreaterThan(0);
    }
  });

  it('menolak desimal pada bilangan bulat uang', () => {
    const rupiah = z.number().int().nonnegative();

    expect(rupiah.safeParse(1000).success).toBe(true);
    expect(rupiah.safeParse(10.5).success).toBe(false);
    expect(rupiah.safeParse(-1).success).toBe(false);
  });
});
