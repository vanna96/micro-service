<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Repositories\FileManagerRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use League\Flysystem\FilesystemException;

class FileManagerController extends Controller
{
    public function __construct(protected FileManagerRepository $files)
    {
        $this->middleware('admin.permission:file_manager.view')->only(['index']);
        $this->middleware('admin.permission:file_manager.create')->only(['store', 'storeFolder']);
        $this->middleware('admin.permission:file_manager.edit')->only(['renameFile', 'replaceFromMyFiles']);
        $this->middleware('admin.permission:file_manager.delete')->only(['destroy', 'destroyMany', 'replaceFromMyFiles']);
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

        try {
            $result = $this->files->uploadFiles(
                $validated['files'],
                (string) ($validated['current_directory'] ?? '')
            );
        } catch (FilesystemException $exception) {
            return $this->storageUnavailableRedirect(
                (string) ($validated['current_directory'] ?? ''),
                $exception
            );
        }

        return $this->redirectToDirectory(
            (string) ($validated['current_directory'] ?? ''),
            $result['restored_records'] > 0
                ? "Files uploaded and {$result['restored_records']} linked image record(s) restored."
                : 'Files uploaded successfully.'
        );
    }

    public function storeFolder(Request $request): RedirectResponse
    {
        $this->requiredTenant();

        $validated = $request->validate([
            'current_directory' => ['nullable', 'string'],
            'folder_name' => ['required', 'string', 'max:100', 'regex:/^[^\/\\\\]+$/'],
        ]);

        try {
            $this->files->createFolder(
                (string) $validated['folder_name'],
                (string) ($validated['current_directory'] ?? '')
            );
        } catch (FilesystemException $exception) {
            return $this->storageUnavailableRedirect(
                (string) ($validated['current_directory'] ?? ''),
                $exception
            );
        }

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

        try {
            if ($validated['entry_type'] === 'directory') {
                $this->files->deleteDirectory((string) $validated['path']);
            } else {
                $this->files->deleteFile((string) $validated['path']);
            }
        } catch (FilesystemException $exception) {
            return $this->storageUnavailableRedirect(
                (string) ($validated['current_directory'] ?? ''),
                $exception
            );
        }

        return $this->redirectToDirectory(
            (string) ($validated['current_directory'] ?? ''),
            $validated['entry_type'] === 'directory'
                ? 'Folder deleted successfully.'
                : 'File deleted successfully.'
        );
    }

    public function renameFile(Request $request): RedirectResponse
    {
        $this->requiredTenant();

        $validated = $request->validate([
            'current_directory' => ['nullable', 'string'],
            'path' => ['required', 'string'],
            'new_name' => ['required', 'string', 'max:201', 'regex:/^[^\/\\\\]+$/'],
        ]);

        try {
            $restoredRecords = $this->files->renameFile(
                (string) $validated['path'],
                (string) $validated['new_name']
            );
        } catch (FilesystemException $exception) {
            return $this->storageUnavailableRedirect(
                (string) ($validated['current_directory'] ?? ''),
                $exception
            );
        }

        return $this->redirectToDirectory(
            (string) ($validated['current_directory'] ?? ''),
            $restoredRecords > 0
                ? "File renamed and {$restoredRecords} linked image record(s) restored."
                : 'File renamed successfully.'
        );
    }

    public function replaceFromMyFiles(Request $request): RedirectResponse
    {
        $this->requiredTenant();

        $validated = $request->validate([
            'current_directory' => ['nullable', 'string'],
            'target_path' => ['required', 'string'],
            'source_path' => ['required', 'string'],
        ]);

        try {
            $this->files->replaceGalleryAssetFromUploads(
                (string) $validated['target_path'],
                (string) $validated['source_path']
            );
        } catch (FilesystemException $exception) {
            return $this->storageUnavailableRedirect(
                (string) ($validated['current_directory'] ?? ''),
                $exception
            );
        }

        return $this->redirectToDirectory(
            (string) ($validated['current_directory'] ?? ''),
            'Linked image replaced successfully. The My Files staging copy was removed.'
        );
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $this->requiredTenant();

        $validated = $request->validate([
            'current_directory' => ['nullable', 'string'],
            'paths' => ['required', 'array', 'min:1', 'max:500'],
            'paths.*' => ['required', 'string', 'distinct'],
        ]);

        try {
            $deletedCount = $this->files->deleteFiles($validated['paths']);
        } catch (FilesystemException $exception) {
            return $this->storageUnavailableRedirect(
                (string) ($validated['current_directory'] ?? ''),
                $exception
            );
        }

        return $this->redirectToDirectory(
            (string) ($validated['current_directory'] ?? ''),
            $deletedCount === 1
                ? '1 file deleted successfully.'
                : "{$deletedCount} files deleted successfully."
        );
    }

    protected function redirectToDirectory(string $directory, string $status): RedirectResponse
    {
        $normalizedDirectory = $this->files->normalizeDirectory($directory);

        return redirect()
            ->route('admin.file-manager.index', $normalizedDirectory === '' ? [] : ['directory' => $normalizedDirectory])
            ->with('status', $status);
    }

    protected function storageUnavailableRedirect(string $directory, FilesystemException $exception): RedirectResponse
    {
        Log::warning('A file manager operation failed because storage is unavailable.', [
            'exception' => get_class($exception),
        ]);

        $normalizedDirectory = $this->files->normalizeDirectory($directory);

        return redirect()
            ->route('admin.file-manager.index', $normalizedDirectory === '' ? [] : ['directory' => $normalizedDirectory])
            ->with('error', 'File storage is temporarily unavailable. Please try again shortly.');
    }

    protected function requiredTenant(): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        return $tenant;
    }
}
