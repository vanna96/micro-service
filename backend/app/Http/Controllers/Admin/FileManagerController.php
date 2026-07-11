<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Repositories\FileManagerRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FileManagerController extends Controller
{
    public function __construct(protected FileManagerRepository $files)
    {
        $this->middleware('admin.permission:file_manager.view')->only(['index']);
        $this->middleware('admin.permission:file_manager.manage')->except(['index']);
    }

    public function index(Request $request): View
    {
        $selectedTenant = $this->requiredTenant();
        $directory = (string) $request->query('directory', '');

        return view('admin.file-manager.index', [
            'selectedTenant' => $selectedTenant,
            'listing' => $this->files->getListing($directory),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant();

        $validated = $request->validate([
            'current_directory' => ['nullable', 'string'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:10240'],
        ]);

        $this->files->uploadFiles(
            $validated['files'],
            (string) ($validated['current_directory'] ?? '')
        );

        return $this->redirectToDirectory(
            (string) ($validated['current_directory'] ?? ''),
            'Files uploaded successfully.'
        );
    }

    public function storeFolder(Request $request): RedirectResponse
    {
        $this->requiredTenant();

        $validated = $request->validate([
            'current_directory' => ['nullable', 'string'],
            'folder_name' => ['required', 'string', 'max:100', 'regex:/^[^\/\\\\]+$/'],
        ]);

        $this->files->createFolder(
            (string) $validated['folder_name'],
            (string) ($validated['current_directory'] ?? '')
        );

        return $this->redirectToDirectory(
            (string) ($validated['current_directory'] ?? ''),
            'Folder created successfully.'
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->requiredTenant();

        $validated = $request->validate([
            'current_directory' => ['nullable', 'string'],
            'path' => ['required', 'string'],
            'entry_type' => ['required', Rule::in(['file', 'directory'])],
        ]);

        if ($validated['entry_type'] === 'directory') {
            $this->files->deleteDirectory((string) $validated['path']);
        } else {
            $this->files->deleteFile((string) $validated['path']);
        }

        return $this->redirectToDirectory(
            (string) ($validated['current_directory'] ?? ''),
            $validated['entry_type'] === 'directory'
                ? 'Folder deleted successfully.'
                : 'File deleted successfully.'
        );
    }

    protected function redirectToDirectory(string $directory, string $status): RedirectResponse
    {
        $normalizedDirectory = $this->files->normalizeDirectory($directory);

        return redirect()
            ->route('admin.file-manager.index', $normalizedDirectory === '' ? [] : ['directory' => $normalizedDirectory])
            ->with('status', $status);
    }

    protected function requiredTenant(): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        return $tenant;
    }
}
