<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Notifications;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Src\Shared\Application\Notifications\EmailSender;
use Throwable;

/**
 * Email lewat konfigurasi surel Laravel. Pengiriman nyata butuh MAIL_MAILER selain `log`/`array`; dengan `log` pesan hanya
 * ditulis ke log lokal sehingga dianggap belum dikonfigurasi dan tidak dihitung terkirim.
 */
final class LaravelEmailSender implements EmailSender
{
    public function configured(): bool
    {
        return ! in_array((string) config('mail.default'), ['log', 'array', ''], true);
    }

    public function send(string $to, string $subject, string $body): bool
    {
        if (! $this->configured() || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        try {
            Mail::raw($body, static function ($message) use ($to, $subject): void {
                $message->to($to)->subject($subject);
            });

            return true;
        } catch (Throwable $e) {
            Log::warning('Pengiriman email gagal.', ['exception' => $e::class]);

            return false;
        }
    }
}
