#!/usr/bin/env python3
"""SessionStart: ingatkan aturan inti dan tampilkan kemajuan."""
import os
import subprocess
import sys

sys.path.insert(0, os.path.dirname(__file__))
from _common import ROOT  # noqa: E402

print("SIKG: baca CLAUDE.md dan docs/rules sebelum mengubah kode. Tanpa tanggal dan tanpa estimasi waktu (M15). Bila ragu, berhenti dan tanya.")
try:
    r = subprocess.run([sys.executable, str(ROOT / "scripts" / "status.py"), "--brief"], cwd=ROOT, capture_output=True, text=True, env={**os.environ, "SIKG_ROOT": str(ROOT)})
    print(r.stdout.strip())
except Exception:
    pass
sys.exit(0)
