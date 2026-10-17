<?php

declare(strict_types=1);

namespace Src\Clinic\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Clinic\Application\Doctors\DoctorAdministration;
use Src\Clinic\Contracts\BranchDirectory;
use Src\Clinic\Domain\FeeMode;
use Src\Clinic\Presentation\Http\Requests\DoctorRequest;
use Src\IdentityAccess\Contracts\UserLookup;
use Src\Shared\Application\RequiredFields;

/**
 * MST-02: dokter, jadwal mingguan, skema jasa. Semua route di balik can:setting.manage.
 */
final class DoctorController
{
    public function __construct(
        private readonly DoctorAdministration $doctors,
        private readonly BranchDirectory $branches,
        private readonly UserLookup $users,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Settings/Doctors/Index', ['doctors' => $this->doctors->list()]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Settings/Doctors/Form', ['required' => RequiredFields::from(DoctorRequest::createFrom($request)->rules()), 'doctor' => null, ...$this->options()]);
    }

    public function edit(Request $request, string $doctor): Response
    {
        return Inertia::render('Settings/Doctors/Form', ['required' => RequiredFields::from(DoctorRequest::createFrom($request)->rules()), 'doctor' => $this->doctors->find($doctor), ...$this->options()]);
    }

    public function store(DoctorRequest $request): RedirectResponse
    {
        $this->doctors->create($this->actorId($request), $request->toData());

        return to_route('settings.doctors')->with('success', 'Dokter ditambahkan.');
    }

    public function update(DoctorRequest $request, string $doctor): RedirectResponse
    {
        $this->doctors->update($this->actorId($request), $doctor, $request->toData());

        return to_route('settings.doctors')->with('success', 'Data dokter disimpan.');
    }

    public function activate(Request $request, string $doctor): RedirectResponse
    {
        $this->doctors->setActive($this->actorId($request), $doctor, true);

        return back()->with('success', 'Dokter diaktifkan.');
    }

    public function deactivate(Request $request, string $doctor): RedirectResponse
    {
        $this->doctors->setActive($this->actorId($request), $doctor, false);

        return back()->with('success', 'Dokter dinonaktifkan. Riwayat tetap tersimpan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'branches' => array_map(fn ($b) => ['value' => $b->id, 'label' => $b->name], $this->branches->all()),
            'users' => array_map(fn ($u) => ['value' => $u->id, 'label' => $u->name], $this->users->activeDoctors()),
            'feeModes' => array_map(fn (FeeMode $m) => ['value' => $m->value, 'label' => $m->label()], FeeMode::cases()),
        ];
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}
