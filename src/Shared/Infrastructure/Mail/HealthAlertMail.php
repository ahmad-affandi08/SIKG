<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Src\Shared\Application\HealthSnapshot;

/**
 * Peringatan kesehatan sistem untuk penanggung jawab teknis. Hanya angka teknis, tanpa PHI.
 */
final class HealthAlertMail extends Mailable
{
    public function __construct(public readonly HealthSnapshot $snapshot) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[SIKG] Peringatan kesehatan sistem');
    }

    public function content(): Content
    {
        $s = $this->snapshot;
        $text = "SIKG: kesehatan sistem menurun.\n"
            .'Denyut scheduler: '.($s->schedulerAgeSeconds ?? 'belum pernah')." dtk lalu.\n"
            .'Denyut antrean: '.($s->queueAgeSeconds ?? 'belum pernah')." dtk lalu.\n"
            ."Job tertunda: {$s->pendingJobs}, gagal: {$s->failedJobs}.\n";

        return new Content(text: 'mail.plain', with: ['body' => $text]);
    }
}
