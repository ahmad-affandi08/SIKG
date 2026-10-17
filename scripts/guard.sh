#!/usr/bin/env bash
# Pemeriksa aturan proyek SIKG. Semua logika ada di guard.py.
set -euo pipefail
cd "$(dirname "$0")/.."
exec python3 scripts/guard.py "$@"
