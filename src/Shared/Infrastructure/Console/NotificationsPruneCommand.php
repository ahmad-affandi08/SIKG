<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Src\Shared\Application\Clock;

/**
 * SYS-05: membuang pemberitahuan dalam aplikasi yang lebih tua dari masa simpan (bawaan 90 hari). Pemberitahuan bukan data
 * klinis atau keuangan dan tanpa PHI; audit log tetap menjadi catatan resmi. Aman dijalankan berulang dan tahan cron terlewat.
 */
final class NotificationsPruneCommand extends Command
{
    public const RETENTION_DAYS = 90;

    protected $signature = 'sikg:notifications:prune';

    protected $description = 'Buang pemberitahuan dalam aplikasi yang lebih tua dari 90 hari';

    public function handle(Clock $clock): int
    {
        $cutoff = $clock->now()->modify('-'.self::RETENTION_DAYS.' days');
        $ids = DB::table('notifications')->where('created_at', '<', $cutoff)->pluck('id')->all();
        foreach (array_chunk($ids, 500) as $chunk) {
            DB::table('notification_reads')->whereIn('notification_id', $chunk)->delete();
            DB::table('notifications')->whereIn('id', $chunk)->delete();
        }
        $this->info(count($ids).' pemberitahuan lama dibuang.');

        return self::SUCCESS;
    }
}
