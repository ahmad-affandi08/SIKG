<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Src\Integration\Application\SubmitEncounters;
use Src\Shared\Application\TenantContext;

/**
 * P2-01: mengirim antrean kunjungan ke SATUSEHAT tiap klinik. Dijadwalkan tiap 5 menit; setiap putaran dibatasi agar pendek
 * (M21) dan pekerjaan yang tersisa diteruskan putaran berikutnya. Percobaan ulang mengikuti jeda bertahap, bukan jadwal cron.
 */
final class SubmitEncountersCommand extends Command
{
    protected $signature = 'sikg:satusehat:send';

    protected $description = 'Kirim kunjungan yang sudah ditutup ke SATUSEHAT';

    public function handle(SubmitEncounters $submit, TenantContext $tenant): int
    {
        $sent = 0;
        $problems = 0;
        foreach (DB::table('clinics')->pluck('id') as $clinicId) {
            $result = $tenant->runAs((string) $clinicId, null, fn () => $submit->run());
            $sent += $result['sent'];
            $problems += $result['failed'] + $result['blocked'];
        }
        $this->info("Terkirim: {$sent}, perlu perhatian: {$problems}");

        return self::SUCCESS;
    }
}
