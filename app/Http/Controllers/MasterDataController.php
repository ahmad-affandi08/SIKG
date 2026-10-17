<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Shared\Application\Master\MasterEntity;
use Src\Shared\Application\Master\MasterEntityRegistry;

/**
 * Halaman pengaturan generik untuk master data berbentuk daftar (kursi, tindakan, metode pembayaran, obat).
 * Route dibuat per entitas di routes/web.php dengan izin dari entitas itu sendiri; di sini hanya alurnya.
 */
final class MasterDataController
{
    public function __construct(private readonly MasterEntityRegistry $registry) {}

    public function index(Request $request): Response
    {
        $master = $this->master($request);

        return Inertia::render('Settings/Master/Index', [
            'entity' => [
                'key' => $master->key(),
                'title' => $master->title(),
                'fields' => array_map(fn ($f) => $f->toArray(), $master->fields()),
                'columns' => $master->columns(),
            ],
            'rows' => $master->list(),
            'canManage' => (bool) $request->user()?->can($master->managePermission()),
            'tabs' => $this->tabs($request),
        ]);
    }

    public function form(Request $request): Response
    {
        $master = $this->master($request);
        $id = $request->route('id');
        $row = null;
        if (is_string($id)) {
            $row = collect($master->list())->firstWhere('id', $id);
            abort_if($row === null, 404);
        }

        return Inertia::render('Settings/Master/Form', [
            'entity' => [
                'key' => $master->key(),
                'title' => $master->title(),
                'fields' => array_map(fn ($f) => $f->toArray(), $master->fields()),
            ],
            'row' => $row,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $master = $this->master($request);
        $data = $request->validate($master->rules(null));
        $master->create($this->actorId($request), $data);

        return redirect('/settings/master/'.$master->key())->with('success', 'Data disimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $master = $this->master($request);
        $data = $request->validate($master->rules($id));
        $master->update($this->actorId($request), $id, $data);

        return redirect('/settings/master/'.$master->key())->with('success', 'Perubahan disimpan.');
    }

    public function activate(Request $request, string $id): RedirectResponse
    {
        $this->master($request)->setActive($this->actorId($request), $id, true);

        return back()->with('success', 'Data diaktifkan.');
    }

    public function deactivate(Request $request, string $id): RedirectResponse
    {
        $this->master($request)->setActive($this->actorId($request), $id, false);

        return back()->with('success', 'Data dinonaktifkan. Riwayat tetap tersimpan.');
    }

    /**
     * Daftar tab antar-entitas yang boleh dilihat pengguna.
     *
     * @return list<array{key: string, title: string}>
     */
    private function tabs(Request $request): array
    {
        $tabs = [];
        foreach ($this->registry->all() as $master) {
            if ($this->visible($request, $master)) {
                $tabs[] = ['key' => $master->key(), 'title' => $master->title()];
            }
        }

        return $tabs;
    }

    private function visible(Request $request, MasterEntity $master): bool
    {
        return (bool) $request->user()?->can($master->viewPermission());
    }

    private function master(Request $request): MasterEntity
    {
        $key = $request->route('entity');
        abort_unless(is_string($key) && $this->registry->has($key), 404);

        return $this->registry->get($key);
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}
