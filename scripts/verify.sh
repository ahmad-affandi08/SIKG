#!/usr/bin/env bash
# Gerbang verifikasi SIKG. Semua langkah dijalankan; satu saja gagal berarti gagal.
# Pemakaian: scripts/verify.sh [--quick] [--e2e]
#   --quick  hanya guard, analisis statis, dan tes cepat (tanpa audit jaringan)
#   --e2e    tambahkan Playwright
#   --shared jalankan juga suite fitur dengan konfigurasi shared hosting (M21)
#   --shared-only hanya suite shared hosting (tanpa Redis, driver database, fungsi shell dimatikan)
set -uo pipefail
cd "$(dirname "$0")/.."

QUICK=0; E2E=0; SHARED=0; SHARED_ONLY=0
for a in "$@"; do
  case "$a" in
    --quick) QUICK=1 ;;
    --e2e) E2E=1 ;;
    --shared) SHARED=1 ;;
    --shared-only) SHARED=1; SHARED_ONLY=1 ;;
    *) echo "Argumen tidak dikenal: $a"; exit 2 ;;
  esac
done

FAILED=()
step() {
  local name="$1"; shift
  echo ""
  echo "==> $name"
  if "$@"; then echo "    OK: $name"; else echo "    GAGAL: $name"; FAILED+=("$name"); fi
}

# Suite kompatibilitas shared hosting: driver database, tanpa Redis, dan fungsi eksekusi perintah dimatikan.
run_shared() {
  env CACHE_STORE=database SESSION_DRIVER=database QUEUE_CONNECTION=database SIKG_HOSTING_PROFILE=shared \
    php -d disable_functions=exec,shell_exec,system,passthru,proc_open,popen \
    vendor/bin/pest --compact
}

if [ "$SHARED_ONLY" -eq 1 ]; then
  if [ -x vendor/bin/pest ]; then
    step "pest (profil shared hosting)" run_shared
  else
    FAILED+=("pest tidak terpasang")
  fi
else
step "guard (aturan proyek)" python3 scripts/guard.py

if [ ! -f composer.json ]; then
  echo ""
  echo "Belum ada proyek Laravel (composer.json tidak ditemukan)."
  echo "Ikuti docs/BOOTSTRAP.md untuk Fase 0, lalu jalankan ulang."
else
  [ -x vendor/bin/pint ]     && step "pint (format PHP)"        vendor/bin/pint --test --config tooling/pint.json || { echo "vendor/bin/pint tidak ada"; FAILED+=("pint tidak terpasang"); }
  [ -x vendor/bin/phpstan ]  && step "larastan (analisis statis)" vendor/bin/phpstan analyse -c tooling/phpstan.neon --memory-limit=1G --no-progress || { echo "phpstan tidak ada"; FAILED+=("phpstan tidak terpasang"); }
  [ -x vendor/bin/deptrac ]  && step "deptrac (batas lapisan)"   vendor/bin/deptrac analyse --config-file=tooling/deptrac.yaml --no-progress || { echo "deptrac tidak ada"; FAILED+=("deptrac tidak terpasang"); }
  if [ -x vendor/bin/pest ]; then
    step "pest (tes backend)" vendor/bin/pest --compact
  else
    FAILED+=("pest tidak terpasang")
  fi
  [ "$QUICK" -eq 0 ] && step "composer audit" composer audit
  [ "$SHARED" -eq 1 ] && [ -x vendor/bin/pest ] && step "pest (profil shared hosting)" run_shared
fi

if [ -f package.json ]; then
  step "tsc (tipe)"          npx --no-install tsc --noEmit
  step "eslint"              npx --no-install eslint -c tooling/eslint.config.js resources/js --max-warnings=0
  step "prettier"            npx --no-install prettier --config tooling/prettierrc.json --check resources/js
  step "vitest (tes frontend)" npx --no-install vitest run
  [ "$QUICK" -eq 0 ] && step "npm audit" npm audit --audit-level=high
  [ "$E2E" -eq 1 ] && step "playwright (E2E)" npx --no-install playwright test
fi
fi

echo ""
if [ "${#FAILED[@]}" -gt 0 ]; then
  echo "VERIFIKASI GAGAL:"
  printf ' - %s\n' "${FAILED[@]}"
  echo "Perbaiki penyebabnya. Dilarang melemahkan tes, lint, atau pengaman (CLAUDE.md M16)."
  exit 1
fi
echo "VERIFIKASI LULUS."
