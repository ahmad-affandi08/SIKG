<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Src\Patient\Application\Contact\LinkTelegramFromBot;
use Src\Shared\Application\Notifications\TelegramInbox;
use Src\Shared\Application\Notifications\TelegramSender;

/**
 * JDW-07: membaca pesan masuk bot Telegram lewat polling (cron, tanpa webhook; M21) dan menautkan pasien yang membuka tautan
 * `t.me/<bot>?start=<token>`. Offset disimpan di cache sehingga pesan tidak diproses dua kali; token yang salah, kedaluwarsa,
 * atau sudah dipakai dijawab dengan pesan netral. Isi pesan dan ID obrolan tidak dicatat ke log.
 */
final class TelegramPollCommand extends Command
{
    private const OFFSET_KEY = 'sikg.telegram.offset';

    protected $signature = 'sikg:telegram:poll';

    protected $description = 'Baca pesan masuk bot Telegram dan tautkan pasien yang mengikuti tautan penautan';

    public function handle(TelegramInbox $inbox, TelegramSender $telegram, LinkTelegramFromBot $link): int
    {
        if (! $telegram->configured()) {
            $this->info('Bot Telegram belum diatur.');

            return self::SUCCESS;
        }

        $offset = (int) Cache::get(self::OFFSET_KEY, 0);
        $linked = 0;
        foreach ($inbox->updates($offset) as $update) {
            $offset = max($offset, $update['update_id'] + 1);

            if (preg_match('/^\/start(?:@\w+)?\s+([A-Za-z0-9_-]{16,64})$/', trim($update['text']), $match) === 1) {
                $ok = $link($match[1], $update['chat_id']);
                $linked += $ok ? 1 : 0;
                $telegram->send($update['chat_id'], $ok
                    ? 'Terima kasih. Akun Telegram Anda sudah tertaut dan akan menerima pengingat jadwal kunjungan dari klinik.'
                    : 'Tautan tidak berlaku atau sudah kedaluwarsa. Mintalah tautan baru kepada petugas klinik.');
            } elseif (trim($update['text']) !== '' && $update['chat_id'] !== '') {
                $telegram->send($update['chat_id'], 'Bot ini hanya mengirim pengingat jadwal kunjungan. Untuk menautkan akun, ikuti tautan dari petugas klinik.');
            }
        }
        Cache::forever(self::OFFSET_KEY, $offset);
        $this->info("Akun tertaut: {$linked}");

        return self::SUCCESS;
    }
}
