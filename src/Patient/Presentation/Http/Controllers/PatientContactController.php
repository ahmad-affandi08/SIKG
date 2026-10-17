<?php

declare(strict_types=1);

namespace Src\Patient\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Patient\Application\Contact\ManagePatientContact;
use Src\Patient\Application\ViewPatient;
use Src\Patient\Presentation\Http\Requests\PatientContactRequest;

/**
 * JDW-07: kontak pengingat pasien (email, Telegram) dan persetujuan menerima pengingat. Izin patient.update dipasang di route.
 */
final class PatientContactController
{
    public function show(Request $request, string $patient, ViewPatient $view, ManagePatientContact $contact): Response
    {
        $record = $view($patient, 'patients.contact')['patient'];

        return Inertia::render('Patients/Contact', [
            'patient' => ['id' => $record->id, 'name' => $record->profile->name, 'medical_record_no' => $record->medicalRecordNo],
            'contact' => $contact->view($patient),
            // Tautan sekali tampil: hanya ada di permintaan tepat setelah dibuat.
            'telegramLink' => $request->session()->get('telegram_link'),
        ]);
    }

    public function update(PatientContactRequest $request, string $patient, ManagePatientContact $contact): RedirectResponse
    {
        $email = $request->input('email');
        $contact->save($this->actorId($request), $patient, is_string($email) ? $email : null, $request->boolean('reminders_opt_in'));

        return to_route('patients.contact', ['patient' => $patient])->with('success', 'Kontak pengingat disimpan.');
    }

    public function startLink(Request $request, string $patient, ManagePatientContact $contact): RedirectResponse
    {
        $link = $contact->startTelegramLink($this->actorId($request), $patient);

        return to_route('patients.contact', ['patient' => $patient])->with('telegram_link', $link);
    }

    public function unlink(Request $request, string $patient, ManagePatientContact $contact): RedirectResponse
    {
        $contact->unlinkTelegram($this->actorId($request), $patient);

        return to_route('patients.contact', ['patient' => $patient])->with('success', 'Akun Telegram dilepas.');
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}
