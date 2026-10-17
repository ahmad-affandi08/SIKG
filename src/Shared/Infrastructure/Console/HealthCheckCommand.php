<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Src\Shared\Application\HealthMonitor;
use Src\Shared\Application\HealthSnapshot;
use Src\Shared\Infrastructure\Mail\HealthAlertMail;

/**
 * SYS-07: dijalankan scheduler tiap beberapa menit. Mencatat peringatan (tanpa PHI) dan mengirim surel ke
 * penanggung jawab teknis hanya saat keadaan berubah, agar tidak membanjiri kotak masuk. Bila cron mati total,
 * pemantau eksternal ke /health yang mendeteksinya (docs/operations.md).
 */
final class HealthCheckCommand extends Command
{
    protected $signature = 'sikg:health-check';

    protected $description = 'Periksa denyut cron dan antrean; kirim peringatan saat memburuk';

    public function handle(HealthMonitor $monitor): int
    {
        $snapshot = $monitor->snapshot();
        $previous = Cache::get('sikg.health.status');
        Cache::forever('sikg.health.status', $snapshot->status);

        if ($snapshot->status === HealthSnapshot::OK) {
            $this->info('Sehat.');

            return self::SUCCESS;
        }

        Log::warning('Kesehatan sistem menurun', $snapshot->toArray());
        $this->warn('Menurun: '.json_encode($snapshot->toArray()));

        $email = config('sikg.alert_email');
        if ($previous !== HealthSnapshot::DEGRADED && is_string($email) && $email !== '') {
            Mail::to($email)->send(new HealthAlertMail($snapshot));
        }

        return self::SUCCESS;
    }
}
