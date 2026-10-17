#!/usr/bin/env python3
"""Stop: jangan biarkan sesi berakhir bila guard melaporkan pelanggaran."""
import os
import subprocess
import sys

sys.path.insert(0, os.path.dirname(__file__))
from _common import ROOT, load_input  # noqa: E402

data = load_input()
if data.get("stop_hook_active"):
    sys.exit(0)
r = subprocess.run([sys.executable, str(ROOT / "scripts" / "guard.py")], cwd=ROOT, capture_output=True, text=True, env={**os.environ, "SIKG_ROOT": str(ROOT)})
if r.returncode != 0:
    sys.stderr.write("Belum boleh selesai: scripts/guard.py melaporkan pelanggaran aturan SIKG.\n" + r.stdout[-3500:] +
                     "\nPerbaiki penyebabnya (jangan melemahkan pengaman). Bila pelanggaran berasal dari file terlindungi yang diubah pengguna, laporkan ke pengguna dan minta mereka menjalankan scripts/seal.py.\n")
    sys.exit(2)
sys.exit(0)
