#!/usr/bin/env python3
"""Pemeriksa aturan proyek SIKG (CLAUDE.md, docs/rules).

Pemakaian:
  python3 scripts/guard.py                 periksa seluruh proyek
  python3 scripts/guard.py --files a b     periksa file tertentu saja (pemeriksaan per-file)
  python3 scripts/guard.py --docs-lint     periksa dokumen kit sendiri (tanpa tanggal/estimasi)

Keluaran: path:baris: [KODE] pesan. Exit 1 bila ada pelanggaran.
Penanda `ALASAN:` pada baris yang sama atau baris sebelumnya hanya meredam aturan
yang ditandai (suppressible); setiap pemakaiannya dilaporkan agar dapat ditinjau.
"""
import argparse
import hashlib
import json
import os
import pathlib
import re
import subprocess
import sys

ROOT = pathlib.Path(os.environ.get("SIKG_ROOT") or pathlib.Path(__file__).resolve().parent.parent).resolve()

SKIP_DIRS = {"vendor", "node_modules", ".git", "storage", "bootstrap/cache", "public/build", "public/hot", ".idea", ".vscode"}
CONTEXTS = {"Shared", "IdentityAccess", "Clinic", "Patient", "Scheduling", "Clinical", "Pharmacy", "Billing", "Reporting",
            "Integration", "Insurance", "Lab", "Orthodontics"}
CRUD_CONTEXTS = {"Clinic", "IdentityAccess", "Reporting"}
LAYERS = {"Domain", "Application", "Infrastructure", "Presentation", "Contracts"}
AUTH_EXEMPT_CONTROLLER = re.compile(r"(Login|Logout|Password|Session|Health)\w*Controller\.php$")

violations = []
suppressed = []


def rel(p):
    try:
        return str(pathlib.Path(p).resolve().relative_to(ROOT)).replace(os.sep, "/")
    except ValueError:
        return str(p)


def add(code, path, line, msg):
    violations.append((code, rel(path), line, msg))


def read_lines(path):
    try:
        return pathlib.Path(path).read_text(encoding="utf-8", errors="replace").split("\n")
    except OSError:
        return []


def is_suppressed(lines, i):
    return "ALASAN:" in lines[i] or (i > 0 and "ALASAN:" in lines[i - 1])


def walk(*subdirs, exts=None):
    for sd in subdirs:
        base = ROOT / sd
        if base.is_file():
            yield base
            continue
        if not base.exists():
            continue
        for dp, dn, fn in os.walk(base):
            r = rel(dp)
            dn[:] = [d for d in dn if d not in {"vendor", "node_modules", ".git", "storage"} and f"{r}/{d}" not in SKIP_DIRS]
            for f in fn:
                if exts is None or f.endswith(exts):
                    yield pathlib.Path(dp) / f


def scan(files, rules):
    """rules: list of (code, regex, message, suppressible, only_if(path)->bool)"""
    for f in files:
        lines = read_lines(f)
        rp = rel(f)
        for code, rx, msg, supp, cond in rules:
            if cond and not cond(rp):
                continue
            for i, line in enumerate(lines):
                if rx.search(line):
                    if supp and is_suppressed(lines, i):
                        suppressed.append((rp, i + 1, code))
                        continue
                    add(code, f, i + 1, msg)


# ---------------------------------------------------------------- aturan berbasis regex
def ctx_of(rp):
    m = re.match(r"src/(\w+)/", rp)
    return m.group(1) if m else None


def in_layer(layer):
    return lambda rp: re.match(rf"src/\w+/{layer}/", rp) is not None


def php_rules():
    R = []
    dom = in_layer("Domain")
    R += [
        ("DOMAIN", re.compile(r"^\s*use\s+Illuminate\\"), "Domain tidak boleh memakai Illuminate (M2).", False, dom),
        ("DOMAIN", re.compile(r"\bEloquent\b"), "Domain tidak boleh memakai Eloquent (M2).", False, dom),
        ("DOMAIN", re.compile(r"\b(DB|Auth|Cache|Log|Storage|Request|Gate|Config|Session)::"), "Domain tidak boleh memakai facade (M2).", False, dom),
        ("DOMAIN", re.compile(r"(?<![\w>:$])(now|today|config|app|env|auth|request|resolve|date|time|logger|report|event)\("), "Domain tidak boleh memanggil fungsi global/waktu; pakai Clock atau parameter (M2).", False, dom),
        ("DOMAIN", re.compile(r"new\s+\\?DateTime(Immutable)?\(\s*\)"), "Domain tidak boleh membuat waktu sekarang; terima dari Clock (M2).", False, dom),
        ("DOMAIN", re.compile(r"\bSrc\\\w+\\(Application|Infrastructure|Presentation)\\"), "Domain tidak boleh bergantung pada lapisan luar (M2).", False, dom),
    ]
    app = lambda rp: in_layer("Application")(rp) and ctx_of(rp) not in CRUD_CONTEXTS
    R += [
        ("LAYER", re.compile(r"\bIlluminate\\(Http|Routing|View|Database\\Eloquent)\b|\bInertia\\"), "Application tidak boleh memakai HTTP/Eloquent/Inertia (M2).", False, app),
        ("LAYER", re.compile(r"\bSrc\\\w+\\(Infrastructure|Presentation)\\"), "Application hanya bergantung pada Domain dan Shared (M2).", False, in_layer("Application")),
    ]
    pres = in_layer("Presentation")
    R += [
        ("LAYER", re.compile(r"\bSrc\\\w+\\Infrastructure\\"), "Presentation hanya memanggil Application (M2).", False, pres),
        ("LAYER", re.compile(r"\bIlluminate\\Database\\Eloquent|\bDB::"), "Presentation tidak boleh memakai Eloquent atau DB langsung (M2).", False, pres),
    ]
    infra = in_layer("Infrastructure")
    R += [("LAYER", re.compile(r"\bSrc\\\w+\\Presentation\\"), "Infrastructure tidak boleh bergantung pada Presentation.", False, infra)]

    notests = lambda rp: not rp.startswith("tests/")
    appcode = lambda rp: (rp.startswith("src/") or rp.startswith("app/"))
    R += [
        ("MONEY", re.compile(r"\b(float|double)\s+\$?\w*(amount|price|total|fee|tarif|harga|biaya|discount|diskon|subtotal|paid)\w*", re.I), "Uang tidak boleh float/double; pakai Money (M4).", False, appcode),
        ("MONEY", re.compile(r"\$\w*(amount|price|total|fee|harga|biaya|subtotal)\w*\s*=\s*\(float\)"), "Uang tidak boleh di-cast ke float (M4).", False, appcode),
        ("MONEY", re.compile(r"(?<![\w>:$])(round|floatval|bcadd|bcmul|bcdiv)\(|\(float\)"), "Aritmetika desimal/pembulatan hanya di Money (M4).", False,
         lambda rp: (in_layer("Domain")(rp) or in_layer("Application")(rp)) and "Shared/Domain/Money" not in rp),
        ("MONEY", re.compile(r"->(decimal|float|double)\(\s*['\"]\w*(amount|price|total|fee|harga|biaya)\w*['\"]"), "Kolom uang harus BIGINT UNSIGNED (M4).", False, lambda rp: "Migrations" in rp or rp.startswith("database/")),
        ("MONEY", re.compile(r"->(?!unsignedBigInteger|bigInteger)\w+\(\s*['\"]\w+_amount['\"]"), "Kolom *_amount harus BIGINT UNSIGNED (M4).", False, lambda rp: "Migrations" in rp or rp.startswith("database/")),
        ("DESTRUCT", re.compile(r"forceDelete\(|->truncate\(|::truncate\(|DB::table\([^)]*\)->(delete|truncate)\(|DB::statement\(\s*['\"]\s*(DELETE|TRUNCATE)"), "Dilarang hapus permanen data klinis/keuangan (M5).", False, lambda rp: appcode(rp) and "Migrations" not in rp),
        ("DESTRUCT", re.compile(r"cascadeOnDelete\(|onDelete\(\s*['\"]cascade['\"]"), "Foreign key harus ON DELETE RESTRICT (M3).", True, lambda rp: rp.startswith("src/") or rp.startswith("database/")),
        ("MODEL", re.compile(r"\$guarded\s*=\s*\[\s*\]|::unguard\(|Model::unguarded\("), "Dilarang membuka mass assignment.", False, appcode),
        ("DEBUG", re.compile(r"(?<![\w>:$])(dd|dump|var_dump|print_r|ray|dumpe|var_export)\("), "Sisa debug tertinggal.", False, lambda rp: appcode(rp) or rp.startswith("routes/") or rp.startswith("resources/views/")),
        ("ENV", re.compile(r"(?<![\w>:$])env\("), "env() hanya boleh di config/ Laravel.", False, lambda rp: (appcode(rp) or rp.startswith("routes/") or rp.startswith("database/")) ),
        ("SQL", re.compile(r"(raw|Raw|DB::select|DB::statement|DB::unprepared|DB::insert|DB::update|DB::delete)\(\s*(\"[^\"]*(\$|\{\$)|'[^']*'\s*\.\s*\$)"), "SQL dengan interpolasi variabel; pakai binding (keamanan).", False, appcode),
        ("PHI", re.compile(r"\b(Log::\w+|logger|info|warning|error)\([^;]*(\bnik\b|phone|no_hp|\$\w*->name\b|birth_date|alamat|address)", re.I), "PHI tidak boleh masuk log (M11).", False, appcode),
        ("PHI", re.compile(r"\{(nik|phone|hp|name|nama|birth_date)\??\}"), "PHI tidak boleh ada di URL (M11).", False, lambda rp: rp.startswith("routes/") or rp.endswith("routes.php")),
        ("WEAK", re.compile(r"->skip\(|markTestSkipped|markTestIncomplete|->todo\(|\b(it|test|describe)\.(skip|todo|only)\b|->only\(\)"), "Tes dilewati/dimatikan (M16).", True, lambda rp: rp.startswith("tests/")),
        ("WEAK", re.compile(r"@phpstan-ignore|@psalm-suppress|phpcs:ignore|@codeCoverageIgnore|@phpstan-ignore-next-line"), "Pengabaian analisis statis (M16).", True, appcode),
        ("TESTDB", re.compile(r"""name=["']DB_CONNECTION["']\s+value=["']sqlite|^DB_CONNECTION=sqlite"""), "Tes dan contoh env harus memakai MySQL, bukan SQLite.", False, lambda rp: rp in ("phpunit.xml", "phpunit.xml.dist", ".env.example", ".env.testing.example")),
    ]
    # Kompatibilitas shared hosting (M21, docs/rules/hosting.md)
    R += [
        ("HOST", re.compile(r"(?<![\w>:$])(exec|shell_exec|system|passthru|proc_open|popen|pcntl_exec)\("), "Eksekusi perintah sistem dilarang; tidak tersedia di shared hosting (M21).", False, appcode),
        ("HOST", re.compile(r"=\s*`[^`\n]+`\s*;"), "Backtick (shell) dilarang (M21).", False, appcode),
        ("HOST", re.compile(r"Symfony\\Component\\Process|Illuminate\\Support\\Facades\\Process|Illuminate\\Process\\|\bProcess::(run|start|pipe|forever)\b"), "Process/Symfony Process dilarang (M21).", False, appcode),
        ("HOST", re.compile(r"\bRedis::|Illuminate\\Support\\Facades\\Redis|\bPredis\\|Laravel\\Horizon|\bHorizon::"), "Dilarang memanggil Redis/Horizon langsung; Redis hanya opsi konfigurasi (M21).", False, appcode),
        ("HOST", re.compile(r"(?<![\w>:$])symlink\(|storage:link|Artisan::call\(\s*['\"]storage:link"), "Symlink dan storage:link dilarang; sajikan berkas lewat controller (M21).", False, appcode),
        ("HOST", re.compile(r"ShouldBroadcast|Illuminate\\Broadcasting|\bBroadcast::|Laravel\\Reverb|Pusher\\"), "WebSocket/broadcast dilarang; pakai polling (M21, ADR-0002).", False, appcode),
        ("HOST", re.compile(r"Browsershot|wkhtmltopdf|Spatie\\LaravelPdf|chromium", re.I), "PDF lewat dompdf saja; Chromium/wkhtmltopdf tidak jalan di shared hosting (M21).", False, appcode),
        ("HOST", re.compile(r"CREATE\s+(DEFINER\s*=\s*\S+\s+)?(TRIGGER|PROCEDURE|FUNCTION|EVENT)\b|DROP\s+(TRIGGER|PROCEDURE|FUNCTION|EVENT)\b", re.I), "Trigger/stored procedure/event MySQL dilarang (M21).", False, lambda rp: rp.startswith(("src/", "database/", "app/"))),
        ("HOST", re.compile(r"queue:listen"), "queue:listen sebagai daemon dilarang; antrean digerakkan cron (M21).", False, lambda rp: appcode(rp) or rp.startswith("routes/")),
    ]
    return R


def js_rules():
    R = []
    js = lambda rp: rp.startswith("resources/js/")
    nontest = lambda rp: js(rp) and not re.search(r"\.(test|spec)\.tsx?$", rp) and "/__tests__/" not in rp
    R += [
        ("TS", re.compile(r"(:\s*any\b|<any>|\bas any\b|\bany\[\])"), "Dilarang tipe any; parse dengan Zod (docs/rules/frontend.md).", True, js),
        ("TS", re.compile(r"@ts-ignore|@ts-nocheck|@ts-expect-error|eslint-disable"), "Pengabaian tipe/lint (M16).", True, js),
        ("TS", re.compile(r"dangerouslySetInnerHTML"), "dangerouslySetInnerHTML dilarang tanpa sanitasi dan alasan.", True, js),
        ("PHI", re.compile(r"\b(localStorage|sessionStorage)\b"), "PHI tidak boleh di penyimpanan peramban (M11).", True, nontest),
        ("DEBUG", re.compile(r"console\.(log|debug|info|dir|table|trace)\("), "console dilarang (PHI/log).", False, nontest),
        ("MONEY", re.compile(r"parseFloat|\.toFixed\("), "Uang bilangan bulat; format lewat lib/format (M4).", True,
         lambda rp: nontest(rp) and not rp.startswith("resources/js/lib/format")),
        ("HOST", re.compile(r"new\s+WebSocket\(|new\s+EventSource\(|from\s+['\"](laravel-echo|pusher-js|socket\.io-client)['\"]"), "WebSocket/SSE dilarang; pakai polling TanStack Query (M21).", False, js),
        ("TS", re.compile(r"(?<![\w.])fetch\(|from\s+['\"]axios['\"]|require\(['\"]axios['\"]\)"), "Panggilan jaringan hanya di features/*/api.ts atau lib/ (docs/rules/frontend.md).", True,
         lambda rp: nontest(rp) and not re.match(r"resources/js/(features/\w+/api\.ts|lib/)", rp)),
    ]
    return R


def check_files(files):
    php = [f for f in files if str(f).endswith(".php")]
    js = [f for f in files if re.search(r"\.(tsx?|jsx?)$", str(f))]
    scan(php, php_rules())
    scan(js, js_rules())
    for f in php:
        check_cross_context(f)
        check_controller_authz(f)
    for f in js:
        check_feature_imports(f)
    check_secrets_and_ids(files)


XCTX_USE = re.compile(r"^\s*use\s+(?:function\s+)?Src\\(\w+)\\([\w\\]+)")


def check_cross_context(f):
    rp = rel(f)
    me = ctx_of(rp)
    if not me or me == "Shared":
        return
    for i, line in enumerate(read_lines(f)):
        m = XCTX_USE.match(line)
        if not m:
            continue
        other, rest = m.group(1), m.group(2)
        if other in (me, "Shared"):
            continue
        if rest.startswith("Contracts\\") or rest.startswith("Domain\\Events\\"):
            continue
        add("XCTX", f, i + 1, f"Konteks {me} memakai Src\\{other}\\{rest}; hanya Contracts dan Domain\\Events yang boleh (M2).")


def check_controller_authz(f):
    rp = rel(f)
    if not re.match(r"src/\w+/Presentation/Http/Controllers/.+Controller\.php$", rp):
        return
    if AUTH_EXEMPT_CONTROLLER.search(rp):
        return
    text = "\n".join(read_lines(f))
    if re.search(r"authorize\(|->can\(|Gate::|#\[Authorize|\$this->middleware|HasMiddleware|ALASAN:", text):
        return
    routes = ROOT / "src" / ctx_of(rp) / "Presentation" / "routes.php"
    if routes.exists() and re.search(r"can:|permission:|role_or_permission:|->can\(", routes.read_text(encoding="utf-8", errors="replace")):
        return
    add("AUTHZ", f, 1, "Controller tanpa pemeriksaan izin (authorize/can/permission) (M9).")


def check_feature_imports(f):
    rp = rel(f)
    m = re.match(r"resources/js/features/(\w+)/", rp)
    for i, line in enumerate(read_lines(f)):
        mm = re.search(r"from\s+['\"](?:@/|(?:\.\./)+)features/(\w+)", line)
        if not mm:
            continue
        if m and mm.group(1) != m.group(1):
            add("TS", f, i + 1, f"features/{m.group(1)} mengimpor features/{mm.group(1)}; gunakan komponen bersama.")
        if rp.startswith("resources/js/components/") or rp.startswith("resources/js/lib/"):
            add("TS", f, i + 1, "components/ dan lib/ tidak boleh mengimpor features/.")


NIK_RX = re.compile(r"(?<!\d)(?!9999)\d{16}(?!\d)")
PRIVKEY_RX = re.compile(r"BEGIN (RSA |EC |OPENSSH |DSA )?PRIVATE KEY")
APPKEY_RX = re.compile(r"APP_KEY=base64:[A-Za-z0-9+/=]{20,}")


def check_secrets_and_ids(files):
    for f in files:
        rp = rel(f)
        if rp.startswith("tooling/") or rp.startswith("scripts/") or rp.startswith("docs/"):
            continue
        if not re.search(r"\.(php|tsx?|jsx?|json|ya?ml|env|neon|sql|csv|txt|xml)$|\.env", rp):
            continue
        lines = read_lines(f)
        for i, line in enumerate(lines):
            if PRIVKEY_RX.search(line) or (APPKEY_RX.search(line) and not rp.endswith(".env.example")):
                add("SECRET", f, i + 1, "Rahasia/kunci di repositori (M13).")
            if (rp.startswith("tests/") or rp.startswith("database/") or re.search(r"\.(test|spec)\.tsx?$", rp) or "fixtures" in rp) and NIK_RX.search(line):
                if is_suppressed(lines, i):
                    suppressed.append((rp, i + 1, "M12"))
                else:
                    add("M12", f, i + 1, "Angka 16 digit selain awalan 9999 terlihat seperti NIK nyata; pakai NIK fiktif berawalan 9999 (M12).")


# ---------------------------------------------------------------- tanpa tanggal / estimasi (M15)
MONTHS = r"(Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember|January|February|March|May|June|July|August|October|December)"
DATE_RX = [
    (re.compile(r"\b20\d{2}-\d{2}-\d{2}\b"), "tanggal ISO"),
    (re.compile(r"\b\d{1,2}[/-]\d{1,2}[/-]20\d{2}\b"), "tanggal"),
    (re.compile(rf"\b{MONTHS}\s+20\d{{2}}\b", re.I), "bulan dan tahun"),
    (re.compile(rf"\b\d{{1,2}}\s+{MONTHS}\b", re.I), "tanggal"),
    (re.compile(r"\bQ[1-4]\s*20\d{2}\b"), "kuartal"),
    (re.compile(r"\b(minggu|pekan|week|sprint)\s*(ke-?\s*)?\d+\b", re.I), "nomor minggu/sprint"),
    (re.compile(r"\b(deadline|tenggat|due date|ETA|target selesai|target tanggal|jadwal rilis)\b", re.I), "tenggat"),
]
DUR_RX = re.compile(r"\b\d+(\s*[-–]\s*\d+)?\s*(hari|minggu|pekan|bulan|days?|weeks?|months?|sprints?|man-?days?|person-?days?|jam kerja)\b", re.I)
EST_WORD = re.compile(r"\b(estimasi|perkiraan|selesai dalam|butuh|memakan|durasi|diselesaikan|estimate|takes?|akan selesai)\b", re.I)

WRITABLE_DOCS = ["docs/STATUS.md", "docs/OPEN-QUESTIONS.md", "CHANGELOG.md"]


def nodate_line(line):
    for rx, what in DATE_RX:
        if rx.search(line):
            return what
    if DUR_RX.search(line) and EST_WORD.search(line):
        return "estimasi durasi"
    return None


def check_no_dates(files, comment_only=False):
    for f in files:
        rp = rel(f)
        lines = read_lines(f)
        for i, line in enumerate(lines):
            if comment_only and not re.match(r"\s*(//|#|\*|/\*|\{/\*)", line):
                continue
            what = nodate_line(line)
            if what:
                add("DATE", f, i + 1, f"Terdeteksi {what}; tanpa tanggal dan estimasi waktu (M15).")


def writable_doc_files():
    out = [ROOT / p for p in WRITABLE_DOCS if (ROOT / p).exists()]
    for sub in ("docs/adr", "docs/plans"):
        for f in walk(sub, exts=".md"):
            if f.name != "0000-template.md" and not is_sealed(rel(f)):
                out.append(f)
    return out


# ---------------------------------------------------------------- segel (checksum) file terlindungi
PROTECT_GLOBS = ["CLAUDE.md", "docs/rules", "docs/PRD", "docs/operations.md", "docs/BOOTSTRAP.md", "compose.yaml", "tests-templates", ".claude", "scripts", "tooling", ".github"]
SEAL = ROOT / "tooling" / "protected.sha256"


def protected_files():
    out = []
    for g in PROTECT_GLOBS:
        p = ROOT / g
        if p.is_file():
            out.append(p)
        else:
            out += [f for f in walk(g)]
    return sorted({rel(f) for f in out if rel(f) != "tooling/protected.sha256" and "__pycache__" not in rel(f) and not rel(f).endswith(".pyc")})


def sha(p):
    return hashlib.sha256((ROOT / p).read_bytes()).hexdigest()


def sealed_set():
    if not SEAL.exists():
        return set()
    return {l.split("  ", 1)[1].strip() for l in SEAL.read_text().splitlines() if "  " in l}


def is_sealed(rp):
    return rp in sealed_set()


def check_seal():
    if not SEAL.exists():
        add("PROTECT", SEAL, 1, "tooling/protected.sha256 tidak ada; jalankan scripts/seal.py (oleh manusia).")
        return
    manifest = {}
    for l in SEAL.read_text().splitlines():
        if "  " in l:
            h, p = l.split("  ", 1)
            manifest[p.strip()] = h
    for p, h in manifest.items():
        if not (ROOT / p).exists():
            add("PROTECT", ROOT / p, 1, "File terlindungi hilang (M16).")
        elif sha(p) != h:
            add("PROTECT", ROOT / p, 1, "File terlindungi berubah dari segel (M16). Hanya manusia yang boleh mengubahnya lalu menjalankan scripts/seal.py.")
    for p in protected_files():
        if p not in manifest:
            add("PROTECT", ROOT / p, 1, "File baru di area terlindungi tanpa segel (M16).")


# ---------------------------------------------------------------- struktur, dependensi, kebutuhan
def check_structure():
    src = ROOT / "src"
    if not src.exists():
        return
    for d in sorted(p for p in src.iterdir() if p.is_dir()):
        if d.name not in CONTEXTS:
            add("STRUCT", d, 1, f"Konteks '{d.name}' tidak dikenal; konteks baru butuh persetujuan (scripts/make-context.sh, ADR).")
            continue
        for sub in sorted(p for p in d.iterdir() if p.is_dir()):
            if sub.name not in LAYERS:
                add("STRUCT", sub, 1, f"Folder '{sub.name}' bukan lapisan sah (Domain, Application, Infrastructure, Presentation, Contracts).")
            if d.name == "Shared" and sub.name in ("Presentation",):
                add("STRUCT", sub, 1, "Shared tidak memiliki Presentation.")


def pattern_ok(name, allowed):
    for a in allowed:
        if a.endswith("*") and name.startswith(a[:-1]):
            return True
        if name == a:
            return True
    return False


def check_dependencies():
    appr_path = ROOT / "tooling" / "approved-dependencies.json"
    if not appr_path.exists():
        return
    appr = json.loads(appr_path.read_text())
    cj = ROOT / "composer.json"
    if cj.exists():
        data = json.loads(cj.read_text())
        allowed = appr["composer"]
        for sec in ("require", "require-dev"):
            for name in data.get(sec, {}):
                if name == "php" or name.startswith("ext-"):
                    continue
                if not pattern_ok(name, allowed):
                    add("DEP", cj, 1, f"Dependensi composer '{name}' belum disetujui (M1). Tulis alasan, minta pengguna menambahkannya ke tooling/approved-dependencies.json.")
        if "psr-4" in data.get("autoload", {}) and data["autoload"]["psr-4"].get("Src\\") != "src/":
            add("STRUCT", cj, 1, "composer.json: autoload psr-4 'Src\\\\' harus menunjuk ke 'src/'.")
        elif "Src\\" not in data.get("autoload", {}).get("psr-4", {}):
            add("STRUCT", cj, 1, "composer.json: autoload psr-4 'Src\\\\' => 'src/' belum ada.")
    pj = ROOT / "package.json"
    if pj.exists():
        data = json.loads(pj.read_text())
        allowed = appr["npm"]
        for sec in ("dependencies", "devDependencies"):
            for name in data.get(sec, {}):
                if not pattern_ok(name, allowed):
                    add("DEP", pj, 1, f"Dependensi npm '{name}' belum disetujui (M1). Tulis alasan, minta pengguna menambahkannya ke tooling/approved-dependencies.json.")
    for manifest, lock in (("composer.json", "composer.lock"), ("package.json", "package-lock.json")):
        if (ROOT / manifest).exists() and not (ROOT / lock).exists():
            add("DEP", ROOT / lock, 1, f"{lock} harus ada dan di-commit.")


def check_tsconfig():
    ts = ROOT / "tsconfig.json"
    if not ts.exists():
        return
    text = ts.read_text()
    if "tooling/tsconfig.base.json" in text:
        return
    if not re.search(r'"strict"\s*:\s*true', text):
        add("TS", ts, 1, "tsconfig.json harus strict: true.")
    if not re.search(r'"noUncheckedIndexedAccess"\s*:\s*true', text):
        add("TS", ts, 1, "tsconfig.json harus noUncheckedIndexedAccess: true.")


def check_tracked_secrets():
    try:
        out = subprocess.run(["git", "ls-files"], cwd=ROOT, capture_output=True, text=True, timeout=20)
    except Exception:
        return
    if out.returncode != 0:
        return
    for p in out.stdout.splitlines():
        base = os.path.basename(p)
        if (base == ".env" or base.startswith(".env.")) and base not in (".env.example", ".env.testing.example"):
            add("SECRET", ROOT / p, 1, "File .env ikut di-commit (M13).")
        if re.search(r"\.(pem|key|p12|pfx)$", base):
            add("SECRET", ROOT / p, 1, "Berkas kunci ikut di-commit (M13).")
        if re.search(r"\.(sql|dump|bak)$", base) and not p.startswith(("tests/", "tooling/")):
            add("SECRET", ROOT / p, 1, "Dump database tidak boleh di-commit (M12).")


def check_requirement_tests():
    status = ROOT / "docs" / "STATUS.md"
    if not status.exists():
        return
    done = re.findall(r"^- \[x\] ((?:AUTH|MST|PAS|JDW|RM|ODO|OBT|KSR|SYS|LAP|P[23])-\d+)", status.read_text(), re.M)
    if not done:
        return
    corpus = ""
    for f in walk("tests", "resources/js", exts=(".php", ".ts", ".tsx")):
        rp = rel(f)
        if rp.startswith("tests/") or re.search(r"\.(test|spec)\.tsx?$", rp):
            corpus += "\n".join(read_lines(f)) + "\n"
    for rid in done:
        if rid not in corpus:
            add("REQID", status, 1, f"{rid} ditandai selesai tetapi tidak ada tes yang memuat ID-nya (docs/rules/testing-dod.md).")


def check_hosting_defaults():
    """Bawaan env contoh harus jalan di shared hosting: sesi/antrean/cache lewat database (M21)."""
    for name in (".env.example", ".env.testing.example"):
        f = ROOT / name
        if not f.exists():
            continue
        for i, line in enumerate(read_lines(f)):
            m = re.match(r"\s*(SESSION_DRIVER|QUEUE_CONNECTION|CACHE_STORE)\s*=\s*(\S*)", line)
            if not m:
                continue
            key, val = m.group(1), m.group(2).strip("\"'")
            ok = {"SESSION_DRIVER": {"database", "file"}, "QUEUE_CONNECTION": {"database"}, "CACHE_STORE": {"database", "file"}}[key]
            if val and val not in ok:
                add("HOST", f, i + 1, f"{key}={val}: bawaan harus {'/'.join(sorted(ok))} agar jalan di shared hosting; Redis hanya lewat .env lingkungan (M21).")
        text = "\n".join(read_lines(f))
        if re.search(r"^\s*(BROADCAST_(DRIVER|CONNECTION)\s*=\s*(pusher|reverb|redis|ably))", text, re.M):
            add("HOST", f, 1, "Broadcast ke pusher/reverb/redis dilarang (M21).")


def check_routes_auth():
    for f in walk("src", exts="routes.php"):
        text = "\n".join(read_lines(f))
        if not re.search(r"middleware|auth", text):
            add("AUTHZ", f, 1, "Berkas route tanpa middleware auth (M9).")


def check_all():
    files = []
    for sub in ("src", "app", "routes", "database", "tests", "resources/js", "phpunit.xml", ".env.example"):
        files += list(walk(sub))
    files = [f for f in files if f.is_file()]
    check_files(files)
    check_no_dates(writable_doc_files())
    check_no_dates([f for f in files if re.search(r"\.(php|tsx?)$", str(f))], comment_only=True)
    check_seal()
    check_structure()
    check_dependencies()
    check_tsconfig()
    check_tracked_secrets()
    check_requirement_tests()
    check_routes_auth()
    check_hosting_defaults()


def docs_lint():
    """Periksa dokumen kit itu sendiri (dipakai saat menyusun kit dan di CI)."""
    targets = [ROOT / "CLAUDE.md"]
    for sub in ("docs/PRD", "docs/rules", "docs/adr"):
        targets += [f for f in walk(sub, exts=".md") if f.name != "0000-template.md"]
    for p in ("docs/operations.md", "docs/BOOTSTRAP.md", "docs/STATUS.md", "docs/OPEN-QUESTIONS.md", "README.md"):
        if (ROOT / p).exists():
            targets.append(ROOT / p)
    check_no_dates([t for t in targets if t.exists()])
    check_seal()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--files", nargs="*")
    ap.add_argument("--docs-lint", action="store_true")
    a = ap.parse_args()
    if a.docs_lint:
        docs_lint()
    elif a.files is not None:
        fs = [pathlib.Path(x) if os.path.isabs(x) else ROOT / x for x in a.files]
        fs = [f for f in fs if f.is_file()]
        check_files(fs)
        if any(rel(f) in WRITABLE_DOCS or rel(f).startswith(("docs/adr/", "docs/plans/")) for f in fs):
            check_no_dates([f for f in fs if rel(f) in WRITABLE_DOCS or rel(f).startswith(("docs/adr/", "docs/plans/"))])
        check_no_dates([f for f in fs if re.search(r"\.(php|tsx?)$", str(f))], comment_only=True)
    else:
        check_all()
    for code, path, line, msg in sorted(set(violations)):
        print(f"{path}:{line}: [{code}] {msg}")
    for rp, line, code in suppressed:
        print(f"{rp}:{line}: [ALASAN] aturan {code} diredam dengan 'ALASAN:'; tinjau pembenarannya.", file=sys.stderr)
    if violations:
        print(f"\nguard: {len(set(violations))} pelanggaran.", file=sys.stderr)
        sys.exit(1)
    print("guard: bersih.")


if __name__ == "__main__":
    main()
