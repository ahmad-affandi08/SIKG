<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Preflight;

use Illuminate\Support\Facades\DB;
use Src\Shared\Application\EnvironmentInspector;
use Src\Shared\Application\HealthMonitor;
use Src\Shared\Application\PreflightCheck;
use Throwable;

final readonly class PhpEnvironmentInspector implements EnvironmentInspector
{
    private const EXTENSIONS = ['bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'gd', 'intl', 'json', 'mbstring', 'openssl', 'pdo_mysql', 'tokenizer', 'xml', 'xmlwriter', 'simplexml', 'zip'];

    public function __construct(private HealthMonitor $health) {}

    public function inspect(): array
    {
        return [
            $this->phpVersion(),
            $this->extensions(),
            $this->writable(),
            $this->limit('upload_max_filesize', 'Batas unggah berkas (upload_max_filesize)', 12 * 1048576, 'RM-08 mengunggah sampai 10 MB'),
            $this->limit('post_max_size', 'Batas ukuran permintaan (post_max_size)', 16 * 1048576, 'harus lebih besar dari batas unggah'),
            $this->memory(),
            $this->executionTime(),
            $this->drivers(),
            $this->debug(),
            $this->secrets(),
            $this->webExposure(),
            $this->hostingProfile(),
            $this->transport(),
            $this->database(),
            $this->heartbeat(),
            $this->disk(),
        ];
    }

    private function phpVersion(): PreflightCheck
    {
        $ok = version_compare(PHP_VERSION, '8.3.0', '>=');

        return new PreflightCheck('php_version', 'Versi PHP', $ok ? PreflightCheck::PASS : PreflightCheck::FAIL, 'PHP '.PHP_VERSION.($ok ? '' : ' (minimal 8.3)'));
    }

    private function extensions(): PreflightCheck
    {
        $missing = array_values(array_filter(self::EXTENSIONS, fn (string $e) => ! extension_loaded($e)));

        return new PreflightCheck(
            'extensions',
            'Ekstensi PHP wajib',
            $missing === [] ? PreflightCheck::PASS : PreflightCheck::FAIL,
            $missing === [] ? 'Semua ekstensi tersedia.' : 'Belum aktif: '.implode(', ', $missing),
        );
    }

    private function writable(): PreflightCheck
    {
        $paths = [storage_path(), storage_path('logs'), storage_path('framework'), storage_path('app/private'), base_path('bootstrap/cache')];
        $bad = array_values(array_filter($paths, fn (string $p) => ! is_dir($p) || ! is_writable($p)));

        return new PreflightCheck(
            'writable',
            'Izin tulis storage dan bootstrap/cache',
            $bad === [] ? PreflightCheck::PASS : PreflightCheck::FAIL,
            $bad === [] ? 'Dapat ditulis.' : 'Tidak dapat ditulis: '.implode(', ', array_map(fn (string $p) => str_replace(base_path().'/', '', $p), $bad)),
        );
    }

    /**
     * @param  non-empty-string  $directive
     */
    private function limit(string $directive, string $label, int $minimumBytes, string $reason): PreflightCheck
    {
        $value = (string) ini_get($directive);
        $bytes = $this->bytes($value);
        $ok = $bytes === -1 || $bytes >= $minimumBytes;

        return new PreflightCheck($directive, $label, $ok ? PreflightCheck::PASS : PreflightCheck::FAIL, $value.($ok ? '' : ' (minimal '.($minimumBytes / 1048576)." MB: {$reason})"));
    }

    private function memory(): PreflightCheck
    {
        $value = (string) ini_get('memory_limit');
        $bytes = $this->bytes($value);
        $ok = $bytes === -1 || $bytes >= 256 * 1048576;

        return new PreflightCheck('memory_limit', 'Batas memori (memory_limit)', $ok ? PreflightCheck::PASS : PreflightCheck::WARN, $value.($ok ? '' : ' (disarankan minimal 256 MB untuk impor dan ekspor Excel; diukur pada proses ini)'));
    }

    private function executionTime(): PreflightCheck
    {
        $seconds = (int) ini_get('max_execution_time');
        $ok = $seconds === 0 || $seconds >= 60;

        return new PreflightCheck('max_execution_time', 'Batas waktu eksekusi', $ok ? PreflightCheck::PASS : PreflightCheck::WARN, $seconds === 0 ? 'tanpa batas (CLI)' : "{$seconds} detik".($ok ? '' : ' (disarankan minimal 60 detik)'));
    }

    private function drivers(): PreflightCheck
    {
        $cache = (string) config('cache.default');
        $session = (string) config('session.driver');
        $queue = (string) config('queue.default');
        $profile = (string) config('sikg.hosting_profile');

        $bad = [];
        foreach (['cache' => $cache, 'sesi' => $session, 'antrean' => $queue] as $name => $driver) {
            if (in_array($driver, ['array', 'sync', 'null'], true) && app()->isProduction()) {
                $bad[] = "{$name}={$driver} tidak cocok untuk produksi";
            }
            if ($driver === 'redis' && $profile === 'shared') {
                $bad[] = "{$name}=redis tidak tersedia di shared hosting";
            }
        }

        return new PreflightCheck(
            'drivers',
            'Driver cache, sesi, dan antrean',
            $bad === [] ? PreflightCheck::PASS : PreflightCheck::FAIL,
            "cache={$cache}, sesi={$session}, antrean={$queue}".($bad === [] ? '' : '. '.implode('; ', $bad)),
        );
    }

    private function debug(): PreflightCheck
    {
        $debug = (bool) config('app.debug');
        $production = app()->isProduction();
        $status = ! $debug ? PreflightCheck::PASS : ($production ? PreflightCheck::FAIL : PreflightCheck::WARN);

        return new PreflightCheck('app_debug', 'Mode debug (APP_DEBUG)', $status, $debug ? 'Aktif'.($production ? ' di produksi: matikan.' : ' (lingkungan non-produksi).') : 'Mati');
    }

    private function secrets(): PreflightCheck
    {
        $missing = [];
        if (! is_string(config('app.key')) || config('app.key') === '') {
            $missing[] = 'APP_KEY';
        }
        if (! is_string(config('sikg.pii_hash_key')) || config('sikg.pii_hash_key') === '') {
            $missing[] = 'PII_HASH_KEY';
        }

        return new PreflightCheck(
            'secrets',
            'Kunci aplikasi dan kunci blind index',
            $missing === [] ? PreflightCheck::PASS : PreflightCheck::FAIL,
            $missing === [] ? 'Terisi (nilai tidak ditampilkan).' : 'Belum diisi: '.implode(', ', $missing),
        );
    }

    private function webExposure(): PreflightCheck
    {
        $public = public_path();
        $found = [];
        foreach (['.env', 'storage', 'vendor', 'composer.json', 'artisan', '.git'] as $name) {
            if (file_exists($public.DIRECTORY_SEPARATOR.$name) || is_link($public.DIRECTORY_SEPARATOR.$name)) {
                $found[] = $name;
            }
        }
        $docrootIsApp = realpath($public) === realpath(base_path());

        $ok = $found === [] && ! $docrootIsApp;

        return new PreflightCheck(
            'web_exposure',
            'Berkas konfigurasi, vendor, dan storage tidak terjangkau web',
            $ok ? PreflightCheck::PASS : PreflightCheck::FAIL,
            $ok ? 'Folder publik hanya memuat berkas web.' : ($docrootIsApp ? 'Akar dokumen web menunjuk ke folder aplikasi; arahkan ke folder public.' : 'Ditemukan di folder publik: '.implode(', ', $found)),
        );
    }

    private function hostingProfile(): PreflightCheck
    {
        $profile = (string) config('sikg.hosting_profile');
        $ok = in_array($profile, ['shared', 'server'], true);

        return new PreflightCheck('hosting_profile', 'Profil hosting', $ok ? PreflightCheck::PASS : PreflightCheck::FAIL, $ok ? $profile : "Nilai tidak dikenal: {$profile} (harus shared atau server)");
    }

    private function transport(): PreflightCheck
    {
        $https = str_starts_with((string) config('app.url'), 'https://');
        $secureCookie = (bool) config('session.secure');
        $production = app()->isProduction();
        $ok = $secureCookie && ($https || ! $production);

        return new PreflightCheck(
            'transport',
            'HTTPS dan cookie aman',
            $ok ? PreflightCheck::PASS : ($production ? PreflightCheck::FAIL : PreflightCheck::WARN),
            'APP_URL '.($https ? 'https' : 'bukan https').', cookie sesi '.($secureCookie ? 'Secure' : 'tidak Secure'),
        );
    }

    private function database(): PreflightCheck
    {
        try {
            $version = (string) DB::selectOne('select version() as v')->v;
        } catch (Throwable) {
            return new PreflightCheck('database', 'Koneksi database', PreflightCheck::FAIL, 'Tidak dapat terhubung ke database.');
        }

        $mariadb = stripos($version, 'mariadb') !== false;
        $ok = ! $mariadb && version_compare($version, '8.0.0', '>=');

        return new PreflightCheck('database', 'Koneksi dan versi database', $ok ? PreflightCheck::PASS : PreflightCheck::WARN, "Terhubung, versi {$version}".($ok ? '' : ' (diharapkan MySQL 8.0+; MariaDB belum diverifikasi)'));
    }

    private function heartbeat(): PreflightCheck
    {
        try {
            $snapshot = $this->health->snapshot();
        } catch (Throwable) {
            return new PreflightCheck('heartbeat', 'Denyut cron dan antrean', PreflightCheck::FAIL, 'Tabel denyut tidak dapat dibaca; jalankan migrasi.');
        }

        $detail = 'scheduler '.($snapshot->schedulerAgeSeconds === null ? 'belum pernah berdenyut' : "{$snapshot->schedulerAgeSeconds} dtk lalu")
            .', antrean '.($snapshot->queueAgeSeconds === null ? 'belum pernah berdenyut' : "{$snapshot->queueAgeSeconds} dtk lalu");

        return new PreflightCheck('heartbeat', 'Denyut cron dan antrean', $snapshot->status === 'ok' ? PreflightCheck::PASS : PreflightCheck::WARN, $detail.'. Pasang cron schedule:run dan queue:work agar berdenyut.');
    }

    private function disk(): PreflightCheck
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());
        if ($free === false || $total === false || $total <= 0) {
            return new PreflightCheck('disk', 'Ruang disk', PreflightCheck::WARN, 'Tidak dapat dibaca di lingkungan ini.');
        }

        $freePercent = (int) round($free / $total * 100);

        return new PreflightCheck('disk', 'Ruang disk', $freePercent >= 20 ? PreflightCheck::PASS : PreflightCheck::WARN, "{$freePercent}% kosong".($freePercent >= 20 ? '' : ' (peringatan di bawah 20%)'));
    }

    private function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '-1') {
            return -1;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
