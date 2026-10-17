<?php

declare(strict_types=1);

namespace Src\Scheduling\Application;

use DateTimeZone;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Contracts\DoctorDirectory;
use Src\Scheduling\Domain\AppointmentRepository;
use Src\Scheduling\Domain\Events\AppointmentBooked;
use Src\Shared\Application\Notifications\Notification;
use Src\Shared\Application\Notifications\StaffAlerts;

/**
 * SYS-05: janji baru muncul sebagai pemberitahuan untuk pemegang izin appointment.view. Isinya tanpa nama pasien karena dapat
 * diteruskan ke email atau grup Telegram klinik.
 */
final readonly class NotifyAppointmentBooked
{
    public function __construct(
        private AppointmentRepository $appointments,
        private DoctorDirectory $doctors,
        private ClinicCalendar $calendar,
        private StaffAlerts $alerts,
    ) {}

    public function handle(AppointmentBooked $event): void
    {
        $appointment = $this->appointments->find($event->appointmentId);
        if ($appointment === null) {
            return;
        }

        $start = $appointment->startsAt->setTimezone(new DateTimeZone($this->calendar->timezone()));
        $doctor = $this->doctors->byIds([$appointment->doctorId])[$appointment->doctorId]->name ?? 'dokter';

        $this->alerts->raise(new Notification(
            'appointment.booked',
            'Janji baru',
            'Janji baru pada '.$start->format('d/m/Y H:i').' dengan '.$doctor.'.',
            '/schedule?date='.$start->format('Y-m-d'),
            'appointment.view',
            'appointment.booked:'.$appointment->id,
        ));
    }
}
