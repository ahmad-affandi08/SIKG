#!/usr/bin/env python3
"""PreToolUse (Bash): tolak perintah berbahaya dan pelanggaran aturan proses."""
import os
import re
import shlex
import subprocess
import sys

sys.path.insert(0, os.path.dirname(__file__))
from _common import ROOT, block, guard_module, load_input  # noqa: E402

data = load_input()
cmd = (data.get("tool_input") or {}).get("command", "") or ""
if not cmd.strip():
    sys.exit(0)

PROTECTED_RX = r"(CLAUDE\.md|docs/rules|docs/PRD|docs/operations\.md|docs/BOOTSTRAP\.md|compose\.yaml|tests-templates/|\.claude/|tooling/|scripts/|\.github/)"
ALLOW_PROTECTED = os.environ.get("SIKG_ALLOW_PROTECTED") == "1"

rules = [
    (r"\bdb:wipe\b|\bmigrate:reset\b", "db:wipe/migrate:reset dilarang (M17)."),
    (r"git\s+push\b.*(--force\b|--force-with-lease|\s-f\b)", "git push --force dilarang (M17)."),
    (r"git\s+reset\s+--hard", "git reset --hard dilarang (M17)."),
    (r"git\s+clean\s+-\w*f", "git clean -f dilarang."),
    (r"git\s+(checkout|restore)\s+(--\s+)?\.(\s|$)", "Membuang seluruh perubahan kerja dilarang."),
    (r"git\s+(commit|push|merge|rebase)\b.*--no-verify", "--no-verify melewati pengaman (M16)."),
    (r"\bcurl\b[^|;]*\|\s*(sudo\s+)?(ba)?sh\b|\bwget\b[^|;]*\|\s*(sudo\s+)?(ba)?sh\b", "Mengeksekusi skrip unduhan dilarang."),
    (r"\b(ssh|scp|sftp)\b", "Akses server jarak jauh/produksi dilarang (M17); rilis dilakukan manusia."),
    (r"\bphp\s+artisan\s+(down|up)\b.*--env=production|APP_ENV=production", "Perintah terhadap produksi dilarang (M17)."),
    (r"\b(printenv|env)\s*($|[|;&])", "Membaca seluruh environment dilarang (M13)."),
    (r"\bscripts/seal\.py\b|\bpython3?\s+\S*seal\.py", "seal.py hanya dijalankan manusia (M16)."),
    (r"\bchmod\b[^|;]*\b(scripts|\.claude|tooling)/", "Mengubah izin file pengaman dilarang (M16)."),
]
for rx, msg in rules:
    if re.search(rx, cmd):
        block(msg)

# migrate:fresh / refresh hanya untuk lokal/tes
if re.search(r"\bmigrate:(fresh|refresh)\b", cmd) and not re.search(r"--env=(testing|local)|APP_ENV=(testing|local)", cmd):
    block("migrate:fresh/refresh hanya boleh di lingkungan testing/local (M17). Tambahkan --env=testing.")

# membaca .env / kunci
if re.search(r"\.env(?!\.example|\.testing\.example)(\.\w+)?\b", cmd) and re.search(r"\b(cat|less|more|head|tail|grep|rg|sed|awk|bat|source|cp|base64|xxd|od|strings|python3?|node|php)\b", cmd):
    block("Membaca .env dilarang (M13). Gunakan .env.example.")
if re.search(r"\b(cat|less|head|tail|cp|base64)\b[^|;&]*(\.pem|id_rsa|id_ed25519|\.p12|\.pfx)\b", cmd):
    block("Membaca berkas kunci dilarang (M13).")

# rm -rf hanya pada target aman
if re.search(r"\brm\b", cmd):
    try:
        toks = shlex.split(cmd, posix=True)
    except ValueError:
        toks = cmd.split()
    for i, t in enumerate(toks):
        if t == "rm":
            flags = [x for x in toks[i + 1:] if x.startswith("-")]
            recursive = any(("r" in f or "R" in f) and not f.startswith("--") or f == "--recursive" for f in flags)
            targets = [x for x in toks[i + 1:] if not x.startswith("-") and x not in ("&&", "||", ";", "|")]
            if recursive:
                safe = ("/tmp/", "node_modules", "vendor", "public/build", "bootstrap/cache", "storage/framework", ".phpunit.cache", "coverage", "dist", "build", "./node_modules", "./vendor", ".pest", "storage/logs")
                for tg in targets:
                    tg_clean = tg.rstrip("/")
                    if not any(tg_clean == s.rstrip("/") or tg_clean.startswith(s) for s in safe) or tg_clean in ("/", "~", ".", "..", "*"):
                        block(f"rm -r pada '{tg}' dilarang di luar folder sementara (M17).")
            break

# menulis ke area terlindungi lewat shell
if not ALLOW_PROTECTED:
    writers = r"(sed\s+-i|\btee\b|\bcp\b|\bmv\b|\brm\b|\btruncate\b|perl\s+-i|\bgit\s+(checkout|restore|apply)\b|\bpatch\b|install\s+-m)"
    redirect = r">>?\s*['\"]?[^\s|;&]*" + PROTECTED_RX
    if (re.search(writers, cmd) and re.search(PROTECTED_RX, cmd)) or re.search(redirect, cmd):
        block("Perintah ini menulis ke area terlindungi (CLAUDE.md, docs/rules, docs/PRD, .claude, scripts, tooling, .github). Hanya manusia yang boleh mengubahnya (M16).")
    if re.search(r"python3?\s+-c|node\s+-e|php\s+-r|ruby\s+-e", cmd) and re.search(PROTECTED_RX, cmd) and re.search(r"open\(|writeFile|file_put_contents|write", cmd):
        block("Menulis ke area terlindungi lewat skrip inline dilarang (M16).")

# commit: pesan harus berformat dan tanpa tanggal/estimasi; guard harus bersih
if re.search(r"\bgit\s+commit\b", cmd):
    msg = None
    m = re.search(r"<<-?\s*['\"]?(\w+)['\"]?\s*\n(.*?)\n\s*\1\b", cmd, re.S)
    if m:
        msg = m.group(2)
    else:
        mm = re.findall(r"""-m\s+(?:"((?:[^"\\]|\\.)*)"|'([^']*)')""", cmd)
        if mm:
            msg = "\n".join(a or b for a, b in mm)
    if msg:
        first = msg.strip().splitlines()[0] if msg.strip() else ""
        if not re.match(r"^(feat|fix|test|refactor|docs|chore)(\([A-Za-z0-9_,.\- ]+\))?!?: \S", first):
            block("Pesan commit harus berformat '<tipe>(<ID>): ringkasan' dengan tipe feat|fix|test|refactor|docs|chore (docs/rules/workflow.md).")
        g = guard_module()
        for line in msg.splitlines():
            if line.startswith(("Co-Authored-By:", "Claude-Session:")):
                continue
            what = g.nodate_line(line)
            if what:
                block(f"Pesan commit memuat {what}; tanpa tanggal dan estimasi waktu (M15).")
    r = subprocess.run([sys.executable, str(ROOT / "scripts" / "guard.py")], cwd=ROOT, capture_output=True, text=True, env={**os.environ, "SIKG_ROOT": str(ROOT)})
    if r.returncode != 0:
        sys.stderr.write(r.stdout[-3000:] + r.stderr[-1000:])
        block("scripts/guard.py melaporkan pelanggaran; perbaiki sebelum commit.")
sys.exit(0)
