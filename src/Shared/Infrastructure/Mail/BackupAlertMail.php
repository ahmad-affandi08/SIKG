<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Peringatan cadangan gagal atau terlambat untuk penanggung jawab teknis. Hanya alasan teknis, tanpa PHI.
 */
final class BackupAlertMail extends Mailable
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(public readonly array $reasons) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[SIKG] Peringatan cadangan data');
    }

    public function content(): Content
    {
        $text = "SIKG: cadangan data bermasalah.\n- ".implode("\n- ", $this->reasons)."\n";

        return new Content(text: 'mail.plain', with: ['body' => $text]);
    }
}
