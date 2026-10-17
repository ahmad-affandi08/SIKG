<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Src\Billing\Application\InvoiceListing;
use Src\Billing\Contracts\InvoiceLookup;
use Src\Billing\Contracts\InvoiceSummary;
use Src\Billing\Domain\InvoiceStatus;
use Src\Billing\Infrastructure\Eloquent\InvoiceModel;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Patient\Contracts\PatientLookup;

final readonly class EloquentInvoiceListing implements InvoiceListing, InvoiceLookup
{
    public function __construct(private PatientLookup $patients, private ClinicCalendar $calendar) {}

    public function list(array $filters, int $page, int $perPage): array
    {
        $query = InvoiceModel::query();
        if (($filters['status'] ?? null) !== null && InvoiceStatus::tryFrom((string) $filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }
        if (($filters['number'] ?? null) !== null && trim((string) $filters['number']) !== '') {
            $query->where('number', 'like', addcslashes(trim((string) $filters['number']), '\\%_').'%');
        }
        if (($filters['patient_id'] ?? null) !== null) {
            $query->where('patient_id', $filters['patient_id']);
        }
        $timezone = new DateTimeZone($this->calendar->timezone());
        if (($filters['from'] ?? null) !== null) {
            $query->where('created_at', '>=', (new DateTimeImmutable($filters['from'].' 00:00:00', $timezone))->setTimezone(new DateTimeZone('UTC')));
        }
        if (($filters['to'] ?? null) !== null) {
            $query->where('created_at', '<', (new DateTimeImmutable($filters['to'].' 00:00:00', $timezone))->modify('+1 day')->setTimezone(new DateTimeZone('UTC')));
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('created_at')->orderByDesc('id')->forPage($page, $perPage)->get();
        $ids = [];
        foreach ($rows as $row) {
            $ids[$row->patient_id] = $row->patient_id;
        }
        $patients = $this->patients->summaries(array_values($ids));

        return [
            'rows' => array_values($rows->map(function (InvoiceModel $i) use ($patients): array {
                $status = InvoiceStatus::from($i->status);

                return [
                    'id' => $i->id,
                    'number' => $i->number,
                    'patient_id' => $i->patient_id,
                    'patient_name' => ($patients[$i->patient_id] ?? null)?->name,
                    'medical_record_no' => ($patients[$i->patient_id] ?? null)?->medicalRecordNo,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'total_amount' => $i->total_amount,
                    'paid_amount' => $i->paid_amount,
                    'balance_amount' => max(0, $i->total_amount - $i->paid_amount),
                    'created_at' => $i->created_at->format('c'),
                    'issued_at' => $i->issued_at?->format('c'),
                ];
            })->all()),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function forEncounter(string $encounterId): ?InvoiceSummary
    {
        $row = InvoiceModel::query()->where('encounter_id', $encounterId)->first();
        if ($row === null) {
            return null;
        }
        $status = InvoiceStatus::from($row->status);

        return new InvoiceSummary($row->id, $row->number, $status->value, $status->label(), $row->total_amount, $row->paid_amount);
    }
}
