#!/usr/bin/env python3
"""PostToolUse (Edit/Write/MultiEdit): jalankan guard pada file yang baru diubah."""
import os
import subprocess
import sys

sys.path.insert(0, os.path.dirname(__file__))
from _common import ROOT, load_input, rel_path  # noqa: E402

data = load_input()
ti = data.get("tool_input") or {}
path = ti.get("file_path") or ""
rp = rel_path(path) if path else None
if not rp:
    sys.exit(0)
r = subprocess.run([sys.executable, str(ROOT / "scripts" / "guard.py"), "--files", rp], cwd=ROOT, capture_output=True, text=True, env={**os.environ, "SIKG_ROOT": str(ROOT)})
if r.returncode != 0:
    sys.stderr.write("Pelanggaran aturan SIKG pada file yang baru diubah. Perbaiki penyebabnya, jangan melemahkan pengaman:\n" + r.stdout[-3000:])
    sys.exit(2)
sys.exit(0)
