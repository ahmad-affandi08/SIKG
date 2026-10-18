<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Shared\Application\Master\Import\MasterImporter;
use Src\Shared\Application\Master\Import\MasterImportInvalid;
use Src\Shared\Application\Master\ImportableMaster;
use Src\Shared\Application\Master\MasterEntityRegistry;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * MST-09: impor master data dari Excel dengan pratinjau. Route dibuat per entitas yang boleh diimpor, di balik izin kelola
 * entitas itu (routes/web.php).
 */
final class MasterImportController
{
    public function __construct(private readonly MasterEntityRegistry $registry, private readonly MasterImporter $importer) {}

    public function create(Request $request): Response
    {
        return $this->page($this->master($request), null);
    }

    public function template(Request $request): HttpResponse
    {
        $master = $this->master($request);

        return response($this->importer->template($master), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="templat-impor-'.$master->key().'.xlsx"',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $master = $this->master($request);
        $request->validate(['file' => ['required', 'file', 'max:2048']], [
            'file.required' => 'Pilih berkas Excel (.xlsx) atau CSV.',
            'file.file' => 'Pilih berkas Excel (.xlsx) atau CSV.',
            'file.max' => 'Berkas maksimal 2 MB.',
        ]);
        $file = $request->file('file');
        abort_if($file === null || is_array($file), 422);

        try {
            $preview = $this->importer->preview($this->actorId($request), $master, $file->getContent());
        } catch (MasterImportInvalid $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return to_route('master.'.$master->key().'.import.show', ['token' => $preview->token]);
    }

    public function show(Request $request, string $token): Response
    {
        $master = $this->master($request);
        $preview = $this->importer->summary($this->actorId($request), $master, $token);
        abort_if($preview === null, 404);

        return $this->page($master, $preview);
    }

    public function commit(Request $request, string $token): RedirectResponse
    {
        $master = $this->master($request);
        try {
            $result = $this->importer->commit($this->actorId($request), $master, $token);
        } catch (MasterImportInvalid $e) {
            return to_route('master.'.$master->key().'.import')->with('error', $e->getMessage());
        }

        $message = "{$result['created']} baris diimpor".($result['skipped'] > 0 ? ", {$result['skipped']} dilewati" : '').'.';

        return redirect('/settings/master/'.$master->key())->with('success', $message);
    }

    /**
     * @param  array<string, mixed>|null  $preview
     */
    private function page(ImportableMaster $master, ?array $preview): Response
    {
        return Inertia::render('Settings/Master/Import', [
            'entity' => [
                'key' => $master->key(),
                'title' => $master->title(),
                'import_key' => $master->importKey(),
                'fields' => array_map(fn ($f) => $f->toArray(), $master->fields()),
            ],
            'preview' => $preview,
        ]);
    }

    private function master(Request $request): ImportableMaster
    {
        $key = $request->route('entity');
        abort_unless(is_string($key) && $this->registry->has($key), 404);
        $master = $this->registry->get($key);
        abort_unless($master instanceof ImportableMaster, 404);

        return $master;
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}
