<?php

declare(strict_types=1);

namespace Src\Scheduling\Infrastructure;

use DateTimeImmutable;
use Src\Scheduling\Domain\ReminderLog;
use Src\Scheduling\Infrastructure\Eloquent\AppointmentReminderModel;

final class EloquentReminderLog implements ReminderLog
{
    public function state(string $appointmentId, string $kind, string $channel): ?array
    {
        $row = $this->find($appointmentId, $kind, $channel);

        return $row === null ? null : ['status' => $row->status, 'attempts' => $row->attempts];
    }

    public function record(string $appointmentId, string $kind, string $channel, bool $sent, DateTimeImmutable $at): void
    {
        $row = $this->find($appointmentId, $kind, $channel)
            ?? (new AppointmentReminderModel)->forceFill(['appointment_id' => $appointmentId, 'kind' => $kind, 'channel' => $channel, 'attempts' => 0]);

        $row->forceFill(['status' => $sent ? 'sent' : 'failed', 'attempts' => $row->attempts + 1, 'last_attempt_at' => $at])->save();
    }

    private function find(string $appointmentId, string $kind, string $channel): ?AppointmentReminderModel
    {
        return AppointmentReminderModel::query()->where('appointment_id', $appointmentId)->where('kind', $kind)->where('channel', $channel)->first();
    }
}
