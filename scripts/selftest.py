#!/usr/bin/env python3
"""Uji mandiri pengaman kit: pastikan guard dan hook benar-benar menangkap pelanggaran.

  python3 scripts/selftest.py
"""
import json
import os
import pathlib
import shutil
import subprocess
import sys
import tempfile

KIT = pathlib.Path(__file__).resolve().parent.parent
PY = sys.executable
fails = []


def check(name, cond, detail=""):
    print(("  OK   " if cond else "  GAGAL ") + name + (f"  [{detail}]" if (detail and not cond) else ""))
    if not cond:
        fails.append(name)


def write(root, rel, text):
    p = root / rel
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text(text)


def run_guard(root, *args):
    env = {**os.environ, "SIKG_ROOT": str(root)}
    r = subprocess.run([PY, str(KIT / "scripts" / "guard.py"), *args], capture_output=True, text=True, env=env)
    return r.returncode, r.stdout


def run_hook(name, payload, root):
    env = {**os.environ, "CLAUDE_PROJECT_DIR": str(root), "SIKG_ROOT": str(root)}
    env.pop("SIKG_ALLOW_PROTECTED", None)
    r = subprocess.run([PY, str(KIT / "scripts" / "hooks" / name)], input=json.dumps(payload), capture_output=True, text=True, env=env)
    return r.returncode, r.stderr


def make_project():
    root = pathlib.Path(tempfile.mkdtemp(prefix="sikg-selftest-"))
    for d in ("scripts", ".claude", "tooling", "docs"):
        shutil.copytree(KIT / d, root / d, ignore=shutil.ignore_patterns("__pycache__", "*.pyc"))
    shutil.copy(KIT / "CLAUDE.md", root / "CLAUDE.md")
    return root


def seal(root):
    env = {**os.environ, "SIKG_ROOT": str(root)}
    subprocess.run([PY, str(root / "scripts" / "seal.py")], check=True, capture_output=True, env=env)


print("1. Proyek bersih lolos")
root = make_project()
seal(root)
code, out = run_guard(root)
check("kit bersih tanpa pelanggaran", code == 0, out)

print("2. Pelanggaran terdeteksi")
bad = {
    "src/Billing/Domain/Invoice.php": "<?php\nnamespace Src\\Billing\\Domain;\nuse Illuminate\\Support\\Facades\\DB;\nfinal class Invoice { public float $totalAmount; public function t(){ return now(); } }\n",
    "src/Billing/Application/Pay.php": "<?php\nnamespace Src\\Billing\\Application;\nuse Illuminate\\Http\\Request;\nuse Src\\Billing\\Infrastructure\\Repo;\n",
    "src/Billing/Infrastructure/Repo.php": "<?php\nnamespace Src\\Billing\\Infrastructure;\nuse Src\\Patient\\Domain\\Patient;\nclass Repo { function x(){ $m->forceDelete(); dd($x); DB::raw(\"a $b\"); env('X'); } }\n",
    "src/Billing/Presentation/Http/Controllers/PayController.php": "<?php\nclass PayController { public function store(){ } }\n",
    "src/Billing/Infrastructure/Migrations/2026_01_01_000000_x.php": "<?php\n$t->decimal('total_amount', 10, 2); $t->foreignId('a')->cascadeOnDelete();\n",
    "src/Rogue/Domain/X.php": "<?php\n",
    "tests/Feature/FooTest.php": "<?php\nit('x')->skip();\n$nik = '3201234567890001';\n",
    "resources/js/features/billing/api.ts": "const x: any = 1; // @ts-ignore\n",
    "resources/js/features/patient/Foo.tsx": "import a from '@/features/billing/api';\nconsole.log('x');\nlocalStorage.setItem('a','b');\nfetch('/x');\nconst p = parseFloat('1');\n",
    "docs/STATUS.md": "# s\n- [x] PAS-01 | M | x\nTarget selesai 2026-12-01.\n",
    "routes/web.php": "<?php\nRoute::get('/p/{nik}', fn()=>1);\n",
    "src/Billing/Infrastructure/Bad.php": "<?php\nclass Bad { function x(){ exec('ls'); shell_exec('x'); Redis::get('k'); symlink('a','b'); $o = `ls`; $p = new \\Symfony\\Component\\Process\\Process(['ls']); } }\n",
    "src/Billing/Infrastructure/Migrations/2026_01_01_000001_trigger.php": "<?php\nDB::unprepared('CREATE TRIGGER t BEFORE INSERT ON a FOR EACH ROW SET NEW.x = 1');\n",
    "resources/js/features/billing/Live.tsx": "const s = new WebSocket('wss://x');\n",
    ".env.example": "SESSION_DRIVER=redis\nQUEUE_CONNECTION=redis\nCACHE_STORE=redis\n",
    "composer.json": json.dumps({"require": {"laravel/framework": "^13", "evil/pkg": "1"}, "autoload": {"psr-4": {"Src\\": "src/"}}}),
}
for rel, text in bad.items():
    write(root, rel, text)
code, out = run_guard(root)
check("guard gagal pada proyek bermasalah", code == 1)
expect = {
    "DOMAIN": "Domain memakai Illuminate/now",
    "MONEY": "float dan decimal untuk uang",
    "LAYER": "Application memakai Http dan Infrastructure",
    "XCTX": "lintas konteks tanpa Contracts",
    "DESTRUCT": "forceDelete dan cascade",
    "DEBUG": "dd() dan console.log",
    "SQL": "raw dengan interpolasi",
    "ENV": "env() di src",
    "AUTHZ": "controller tanpa izin",
    "STRUCT": "konteks tak dikenal",
    "WEAK": "tes di-skip",
    "M12": "NIK nyata",
    "TS": "any, ts-ignore, import silang, fetch",
    "PHI": "PHI di URL/localStorage",
    "DATE": "tanggal di STATUS",
    "REQID": "ID selesai tanpa tes",
    "DEP": "dependensi tak disetujui",
    "HOST": "exec/Redis/symlink/trigger/WebSocket/env Redis",
}
for code_, label in expect.items():
    check(f"deteksi [{code_}] {label}", f"[{code_}]" in out, "")

hostout = [l for l in out.splitlines() if "[HOST]" in l]
check("HOST menangkap exec, Redis, symlink, backtick, Process", sum("Bad.php" in l for l in hostout) >= 5, "\n".join(hostout))
check("HOST menangkap trigger MySQL", any("trigger.php" in l for l in hostout))
check("HOST menangkap WebSocket di frontend", any("Live.tsx" in l for l in hostout))
check("HOST menangkap driver Redis di .env.example", sum(".env.example" in l for l in hostout) >= 3, "\n".join(hostout))
write(root, "src/Billing/Infrastructure/Reason.php", "<?php\n// ALASAN: coba\nexec('x');\n")
_, out_r = run_guard(root, "--files", "src/Billing/Infrastructure/Reason.php")
check("HOST tidak diredam oleh ALASAN", "[HOST]" in out_r, out_r)

print("3. Penanda ALASAN: hanya meredam aturan yang boleh diredam")
write(root, "tests/Feature/BarTest.php", "<?php\n// ALASAN: menunggu jawaban Q-1\nit('y')->skip();\n")
write(root, "src/Billing/Domain/Clean.php", "<?php\n// ALASAN: coba\nuse Illuminate\\Support\\Str;\n")
code, out = run_guard(root, "--files", "tests/Feature/BarTest.php", "src/Billing/Domain/Clean.php")
check("WEAK diredam dengan ALASAN", "BarTest.php" not in out)
check("DOMAIN tidak bisa diredam", "Clean.php" in out and "[DOMAIN]" in out)

print("4. Segel file terlindungi")
root2 = make_project()
seal(root2)
write(root2, "docs/rules/workflow.md", (root2 / "docs/rules/workflow.md").read_text() + "\nubahan diam-diam\n")
code, out = run_guard(root2)
check("perubahan docs/rules terdeteksi [PROTECT]", "[PROTECT]" in out and "docs/rules/workflow.md" in out)

print("5. Hook pre_bash")
cases = [
    ("git push --force origin main", True),
    ("git reset --hard HEAD~1", True),
    ("php artisan migrate:fresh --seed", True),
    ("php artisan migrate:fresh --env=testing", False),
    ("cat .env", True),
    ("cat .env.example", False),
    ("rm -rf src", True),
    ("rm -rf node_modules", False),
    ("rm -rf /tmp/x", False),
    ("ssh prod", True),
    ("python3 scripts/seal.py", True),
    ("sed -i 's/a/b/' docs/PRD/02-fungsional-fase1.md", True),
    ("echo x > CLAUDE.md", True),
    ("python3 scripts/guard.py 2>&1", False),
    ("php artisan test --compact", False),
    ("git commit -m 'bebas saja'", True),
    ("git commit -m 'feat(PAS-02): cari pasien, estimasi 3 hari'", True),
    ("git commit --no-verify -m 'feat(PAS-02): x'", True),
]
for cmd, should_block in cases:
    rc, err = run_hook("pre_bash.py", {"tool_input": {"command": cmd}}, root2)
    check(f"bash {'blokir' if should_block else 'izinkan'}: {cmd[:60]}", (rc == 2) == should_block, err[-200:])

print("6. Hook pre_edit")
ecases = [
    ("CLAUDE.md", True),
    ("docs/PRD/00-ringkasan-lingkup-persona.md", True),
    ("scripts/guard.py", True),
    (".env", True),
    ("docs/STATUS.md", False),
    ("docs/adr/0001-modular-monolith-ddd.md", True),
    ("docs/adr/0099-baru.md", False),
    ("src/Patient/Domain/Patient.php", False),
]
for rel, should_block in ecases:
    rc, err = run_hook("pre_edit.py", {"tool_input": {"file_path": str(root2 / rel)}}, root2)
    check(f"edit {'blokir' if should_block else 'izinkan'}: {rel}", (rc == 2) == should_block, err[-200:])
rc, _ = run_hook("pre_edit.py", {"tool_input": {"file_path": "/etc/passwd"}}, root2)
check("edit di luar proyek diblokir", rc == 2)

print("7. Hook stop")
rc, _ = run_hook("stop.py", {"stop_hook_active": False}, root)
check("stop diblokir saat ada pelanggaran", rc == 2)
rc, _ = run_hook("stop.py", {"stop_hook_active": True}, root)
check("stop tidak berulang tanpa akhir", rc == 0)

print("8. Dokumen kit tanpa tanggal/estimasi")
code, out = run_guard(KIT, "--docs-lint")
check("docs-lint kit lulus", code == 0, out[-1500:])

for r in (root, root2):
    shutil.rmtree(r, ignore_errors=True)
print()
if fails:
    print(f"SELFTEST GAGAL: {len(fails)} kasus")
    sys.exit(1)
print("SELFTEST LULUS")
