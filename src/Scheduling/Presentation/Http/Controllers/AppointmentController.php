<?php

declare(strict_types=1);

namespace Src\Scheduling\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Clinic\Contracts\ChairDirectory;
use Src\Clinic\Contracts\DoctorDirectory;
use Src\Scheduling\Application\AppointmentScheduler;
use Src\Scheduling\Application\AppointmentWorkflow;
use Src\Scheduling\Application\ClinicTime;
use Src\Scheduling\Application\ScheduleQueries;
use Src\Scheduling\Presentation\Http\Requests\AppointmentRequest;

/**
 * JDW-01 sampai JDW-08. Izin dipasang per route: appointment.view untuk membaca, appointment.manage untuk mengubah.
 */
final class AppointmentController
{
    public function __construct(
        private readonly AppointmentScheduler $scheduler,
        private readonly AppointmentWorkflow $workflow,
        private readonly ScheduleQueries $queries,
        private readonly DoctorDirectory $doctors,
        private readonly ChairDirectory $chairs,
        private readonly ClinicTime $time,
    ) {}

    public function schedule(Request $request): Response
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'view' => ['nullable', 'in:day,week'],
            'doctor' => ['nullable', 'string', 'size:26'],
            'chair' => ['nullable', 'string', 'size:26'],
        ]);
        $today = $this->time->today();
        $view = ($data['view'] ?? 'day') === 'week' ? 'week' : 'day';
        $date = $data['date'] ?? $today;
        $doctor = $data['doctor'] ?? null;
        $chair = $data['chair'] ?? null;

        return Inertia::render('Schedule/Index', [
            'filters' => ['date' => $date, 'view' => $view, 'doctor' => $doctor, 'chair' => $chair],
            'today' => $today,
            'calendar' => $this->queries->calendar($date, $view, $doctor, $chair),
            'doctors' => array_map(fn ($d) => ['value' => $d->id, 'label' => $d->name], $this->doctors->active()),
            'chairs' => array_map(fn ($c) => ['value' => $c->id, 'label' => $c->name], $this->chairs->active()),
            'canManage' => (bool) $request->user()?->can('appointment.manage'),
        ]);
    }

    public function queue(Request $request): Response
    {
        return Inertia::render('Queue/Index', [
            'doctors' => array_map(fn ($d) => ['value' => $d->id, 'label' => $d->name], $this->doctors->active()),
            'chairs' => array_map(fn ($c) => ['value' => $c->id, 'label' => $c->name], $this->chairs->active()),
            'canManage' => (bool) $request->user()?->can('appointment.manage'),
        ]);
    }

    public function queueData(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $items = $this->queries->queue($data['date'] ?? null);

        return response()->json(['data' => $items, 'meta' => ['count' => count($items)]]);
    }

    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'doctor' => ['required', 'string', 'size:26'],
            'date' => ['required', 'date_format:Y-m-d'],
            'minutes' => ['nullable', 'integer', 'between:10,480'],
            'chair' => ['nullable', 'string', 'size:26'],
        ]);

        $slots = $this->queries->availability($data['doctor'], $data['date'], (int) ($data['minutes'] ?? 30), $data['chair'] ?? null);

        return response()->json(['data' => $slots, 'meta' => ['count' => count($slots)]]);
    }

    public function store(AppointmentRequest $request): RedirectResponse
    {
        $this->scheduler->book(
            $this->actorId($request),
            $request->string('patient_id')->toString(),
            $request->string('doctor_id')->toString(),
            $request->chairId(),
            $request->string('date')->toString(),
            $request->string('time')->toString(),
            $request->integer('minutes'),
            $request->input('complaint'),
        );

        return back()->with('success', 'Janji dibuat.');
    }

    public function update(AppointmentRequest $request, string $appointment): RedirectResponse
    {
        $this->scheduler->reschedule(
            $this->actorId($request),
            $appointment,
            $request->string('doctor_id')->toString(),
            $request->chairId(),
            $request->string('date')->toString(),
            $request->string('time')->toString(),
            $request->integer('minutes'),
            $request->string('reason')->toString(),
        );

        return back()->with('success', 'Janji dijadwalkan ulang.');
    }

    public function walkIn(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'string', 'size:26'],
            'doctor_id' => ['required', 'string', 'size:26'],
            'chair_id' => ['nullable', 'string', 'size:26'],
            'complaint' => ['nullable', 'string', 'max:500'],
        ], ['patient_id.required' => 'Pasien wajib dipilih.', 'doctor_id.required' => 'Dokter wajib dipilih.']);

        $this->scheduler->walkIn(
            $this->actorId($request),
            $data['patient_id'],
            $data['doctor_id'],
            ($data['chair_id'] ?? '') !== '' ? $data['chair_id'] : null,
            $data['complaint'] ?? null,
        );

        return back()->with('success', 'Pasien masuk antrean.');
    }

    public function confirm(Request $request, string $appointment): RedirectResponse
    {
        $this->workflow->confirm($this->actorId($request), $appointment);

        return back()->with('success', 'Janji dikonfirmasi.');
    }

    public function checkIn(Request $request, string $appointment): RedirectResponse
    {
        $this->workflow->checkIn($this->actorId($request), $appointment);

        return back()->with('success', 'Pasien check-in dan masuk antrean.');
    }

    public function cancel(Request $request, string $appointment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Alasan pembatalan wajib diisi.']);
        $this->workflow->cancel($this->actorId($request), $appointment, $data['reason']);

        return back()->with('success', 'Janji dibatalkan.');
    }

    public function noShow(Request $request, string $appointment): RedirectResponse
    {
        $this->workflow->noShow($this->actorId($request), $appointment);

        return back()->with('success', 'Pasien dicatat tidak datang.');
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}
