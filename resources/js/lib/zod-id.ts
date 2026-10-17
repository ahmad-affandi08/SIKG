import { z } from 'zod';

let installed = false;

/** Peta galat Zod global berbahasa Indonesia (docs/rules/frontend.md bagian 4). Aman dipanggil berulang. */
export function installZodIndonesian(): void {
  if (installed) {
    return;
  }
  z.config(z.locales.id());
  installed = true;
}
