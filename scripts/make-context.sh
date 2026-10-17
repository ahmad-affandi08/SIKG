#!/usr/bin/env bash
# Membuat kerangka bounded context baru di src/<Nama>.
# Pemakaian: scripts/make-context.sh Patient
set -euo pipefail
cd "$(dirname "$0")/.."

NAME="${1:-}"
ALLOWED=" Shared IdentityAccess Clinic Patient Scheduling Clinical Pharmacy Billing Reporting Integration Insurance Lab Orthodontics "
if [ -z "$NAME" ] || [[ "$ALLOWED" != *" $NAME "* ]]; then
  echo "Konteks tidak dikenal: '${NAME}'. Sah:${ALLOWED}"
  echo "Konteks baru di luar daftar butuh persetujuan pengguna dan ADR (CLAUDE.md M2)."
  exit 2
fi
case "$NAME" in
  Integration|Insurance|Lab|Orthodontics)
    echo "Peringatan: konteks Fase 2 ($NAME) hanya dibuat saat fasenya dikerjakan (CLAUDE.md M14)." ;;
esac

BASE="src/$NAME"
if [ -d "$BASE" ]; then echo "Sudah ada: $BASE"; exit 0; fi

if [ "$NAME" = "Shared" ]; then LAYERS="Domain Application Infrastructure"; else LAYERS="Domain Application Infrastructure Presentation Contracts"; fi
for l in $LAYERS; do mkdir -p "$BASE/$l"; touch "$BASE/$l/.gitkeep"; done
[ "$NAME" != "Shared" ] && mkdir -p "$BASE/Domain/Events" "$BASE/Infrastructure/Migrations" "$BASE/Presentation/Http/Controllers" "$BASE/Presentation/Http/Requests" "$BASE/Presentation/Http/Resources"

if [ "$NAME" != "Shared" ]; then
cat > "$BASE/${NAME}ServiceProvider.php" <<PHP
<?php

declare(strict_types=1);

namespace Src\\${NAME};

use Illuminate\\Support\\Facades\\Route;
use Illuminate\\Support\\ServiceProvider;

final class ${NAME}ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Ikat interface Domain ke implementasi Infrastructure di sini.
    }

    public function boot(): void
    {
        \$this->loadMigrationsFrom(__DIR__ . '/Infrastructure/Migrations');

        Route::middleware(['web', 'auth'])
            ->group(__DIR__ . '/Presentation/routes.php');
    }
}
PHP
cat > "$BASE/Presentation/routes.php" <<PHP
<?php

declare(strict_types=1);

use Illuminate\\Support\\Facades\\Route;

// Semua route di balik 'auth' (dipasang oleh ServiceProvider).
// Setiap route wajib punya pemeriksaan izin: ->middleware('can:<permission>') atau authorize() di controller.
PHP
fi

# daftarkan provider (idempoten)
if [ "$NAME" != "Shared" ] && [ -f bootstrap/providers.php ]; then
  python3 - "$NAME" <<'PY'
import re, sys
name = sys.argv[1]
p = "bootstrap/providers.php"
t = open(p).read()
line = f"    Src\\{name}\\{name}ServiceProvider::class,"
if line.strip() not in t:
    t = re.sub(r"\n\];", lambda m: f"\n{line}\n];", t, count=1)
    open(p, "w").write(t)
    print("Terdaftar di bootstrap/providers.php")
PY
elif [ "$NAME" != "Shared" ]; then
  echo "Daftarkan Src\\${NAME}\\${NAME}ServiceProvider::class di bootstrap/providers.php setelah proyek ada."
fi
echo "Konteks $NAME dibuat di $BASE"
