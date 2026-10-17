#!/usr/bin/env python3
"""PreToolUse (Edit/Write/MultiEdit/NotebookEdit): lindungi dokumen aturan, pengaman, rahasia, migrasi lama."""
import os
import re
import subprocess
import sys

sys.path.insert(0, os.path.dirname(__file__))
from _common import ROOT, block, load_input, rel_path  # noqa: E402

data = load_input()
ti = data.get("tool_input") or {}
path = ti.get("file_path") or ti.get("notebook_path") or ""
if not path:
    sys.exit(0)
rp = rel_path(path)
if rp is None:
    block(f"Menulis di luar proyek ({path}) dilarang.")

ALLOW = os.environ.get("SIKG_ALLOW_PROTECTED") == "1"
protected = [r"^CLAUDE\.md$", r"^docs/rules/", r"^docs/PRD/", r"^docs/operations\.md$", r"^docs/BOOTSTRAP\.md$", r"^compose\.yaml$", r"^tests-templates/", r"^\.claude/", r"^scripts/", r"^tooling/", r"^\.github/"]
if not ALLOW:
    for rx in protected:
        if re.search(rx, rp):
            block(f"{rp} adalah file terlindungi. Hanya pengguna yang boleh mengubahnya (CLAUDE.md M16). Catat usulan di docs/OPEN-QUESTIONS.md.")

base = os.path.basename(rp)
if re.match(r"^\.env(\..+)?$", base) and base not in (".env.example", ".env.testing.example"):
    block("Menulis .env dilarang (M13). Ubah .env.example saja.")
if rp in ("composer.lock", "package-lock.json"):
    block("Lockfile diubah lewat composer/npm, bukan diedit tangan.")
if re.match(r"^(storage|public/build|vendor|node_modules|bootstrap/cache)/", rp):
    block(f"{rp} adalah artefak; jangan diedit.")

# ADR yang sudah ada tidak diedit (ADR baru boleh)
if re.match(r"^docs/adr/\d{4}-.*\.md$", rp) and (ROOT / rp).exists():
    block("ADR yang sudah ada tidak diedit. Buat ADR baru yang menggantikan.")

# migrasi yang sudah di-commit tidak diedit
if re.search(r"(Infrastructure/Migrations|database/migrations)/.*\.php$", rp) and (ROOT / rp).exists():
    r = subprocess.run(["git", "ls-files", "--error-unmatch", rp], cwd=ROOT, capture_output=True, text=True)
    if r.returncode == 0:
        block("Migrasi yang sudah di-commit tidak boleh diedit; buat migrasi baru (docs/rules/database.md).")

# jangan menulis rahasia/NIK nyata lewat konten
content = ti.get("content") or ti.get("new_string") or ""
if re.search(r"BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY|APP_KEY=base64:[A-Za-z0-9+/=]{20,}", content) and not rp.endswith(".env.example"):
    block("Konten memuat rahasia/kunci (M13).")
sys.exit(0)
