<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure;

use Src\Patient\Contracts\PatientContact;
use Src\Patient\Contracts\PatientContactLookup;
use Src\Patient\Domain\PatientContactRepository;

final readonly class EloquentPatientContactLookup implements PatientContactLookup
{
    public function __construct(private PatientContactRepository $contacts) {}

    public function reminderContacts(array $patientIds): array
    {
        $list = [];
        foreach ($this->contacts->forPatients($patientIds) as $patientId => $contact) {
            if ($contact->remindersOptIn && $contact->hasChannel()) {
                $list[$patientId] = new PatientContact($patientId, $contact->email, $contact->telegramChatId);
            }
        }

        return $list;
    }
}
