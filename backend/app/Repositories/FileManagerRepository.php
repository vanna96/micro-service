<?php

namespace App\Repositories;

use App\Models\Category;
use App\Models\Gallery;
use App\Models\Item;
use App\Models\Promotion;
use App\Models\Slider;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;

class FileManagerRepository extends RepositoryBase
{
    protected string $disk = 'file_manager';

    public function getListing(string $directory = ''): array
    {
        $normalizedDirectory = $this->normalizeDirectory($directory);
        $location = $this->resolveLocation($normalizedDirectory);

        try {
            $currentFiles = $this->filesForLocation($location['source'], $location['path']);
            $allFiles = $normalizedDirectory === ''
                ? $this->allRootFiles()
                : $this->allFilesForLocation($location['source'], $location['path']);

            $directories = $this->directoriesForLocation($location['source'], $location['path'], $normalizedDirectory);
            $replacementSources = $this->allFilesForLocation('uploads', '')
                ->where('is_image', true)
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();
            $storageError = null;
        } catch (FilesystemException $exception) {
            Log::warning('File manager storage is unavailable.', [
                'disk' => $this->sourceDefinition($location['source'])['disk'],
                'exception' => get_class($exception),
            ]);

            $currentFiles = collect();
            $allFiles = collect();
            $directories = collect();
            $replacementSources = collect();
            $storageError = 'File storage is temporarily unavailable. Please try again shortly.';
        }

        return [
            'current_directory' => $normalizedDirectory,
            'current_source' => $location['source'],
            'current_source_label' => $this->sourceDefinition($location['source'])['label'],
            'current_source_writable' => $this->sourceDefinition($location['source'])['writable'],
            'current_source_uploadable' => $this->sourceDefinition($location['source'])['uploadable'] ?? false,
            'current_source_supports_folders' => $this->sourceDefinition($location['source'])['supports_folders'] ?? false,
            'breadcrumbs' => $this->breadcrumbsFor($normalizedDirectory),
            'directories' => $directories,
            'files' => $currentFiles,
            'replacement_sources' => $replacementSources,
            'all_files' => $allFiles
                ->sortByDesc('updated_timestamp')
                ->values(),
            'summary_cards' => $this->summaryCards($allFiles),
            'activity_chart' => $this->activityChart($allFiles),
            'recent_types' => $this->recentTypes($allFiles),
            'total_files_count' => $allFiles->count(),
            'total_storage_label' => $this->humanFileSize((int) $allFiles->sum('size_bytes')),
            'parent_directory' => $this->parentDirectory($normalizedDirectory),
            'storage_error' => $storageError,
        ];
    }

    public function uploadFiles(array $files, string $directory = ''): array
    {
        $normalizedDirectory = $this->normalizeDirectory($directory);
        $location = $this->resolveLocation($normalizedDirectory);
        $definition = $this->sourceDefinition($location['source']);

        abort_if(! ($definition['uploadable'] ?? false), 403);

        $uploadedCount = 0;
        $restoredRecords = 0;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $fileName = $this->originalUploadName($file, 'file');
            $storedPath = $location['path'] === '' ? $fileName : $location['path'] . '/' . $fileName;

            $this->disk($location['source'])->putFileAs($this->qualifyPath($location['source'], $location['path']), $file, $fileName);
            $uploadedCount++;

            if ($location['source'] === 'uploads') {
                $restoredRecords += $this->restoreMatchingGalleryAssets($storedPath);
            }
        }

        return [
            'uploaded_count' => $uploadedCount,
            'restored_records' => $restoredRecords,
        ];
    }

    public function createFolder(string $folderName, string $directory = ''): void
    {
        $folderName = trim($folderName);
        abort_if($folderName === '', 422);

        $normalizedDirectory = $this->normalizeDirectory($directory);
        $location = $this->resolveLocation($normalizedDirectory);
        abort_if(! ($this->sourceDefinition($location['source'])['supports_folders'] ?? false), 403);
        $folderPath = $location['path'] === '' ? $folderName : $location['path'] . '/' . $folderName;

        $this->disk($location['source'])->makeDirectory($this->qualifyPath($location['source'], $folderPath));
    }

    public function deleteFile(string $path): void
    {
        $normalizedPath = $this->normalizeDirectory($path);

        if ($normalizedPath === '') {
            return;
        }

        $location = $this->resolveLocation($normalizedPath, true);
        $definition = $this->sourceDefinition($location['source']);

        if ($definition['writable']) {
            $this->disk($location['source'])->delete($this->qualifyPath($location['source'], $location['path']));

            return;
        }

        abort_if(! ($definition['deletable'] ?? false), 403);

        $this->deleteGalleryBackedFile($location['source'], $location['path']);
    }

    public function deleteDirectory(string $path): void
    {
        $normalizedPath = $this->normalizeDirectory($path);

        if ($normalizedPath === '') {
            return;
        }

        $location = $this->resolveLocation($normalizedPath);
        abort_if(! $this->sourceDefinition($location['source'])['writable'], 403);

        $this->disk($location['source'])->deleteDirectory($this->qualifyPath($location['source'], $location['path']));
    }

    public function deleteFiles(array $paths): int
    {
        $normalizedPaths = collect($paths)
            ->map(function ($path): string {
                abort_unless(is_string($path), 422);

                $normalizedPath = $this->normalizeDirectory($path);
                abort_if($normalizedPath === '', 422);

                $location = $this->resolveLocation($normalizedPath, true);
                $definition = $this->sourceDefinition($location['source']);
                abort_if(! ($definition['writable'] || ($definition['deletable'] ?? false)), 403);

                return $normalizedPath;
            })
            ->unique()
            ->values();

        foreach ($normalizedPaths as $normalizedPath) {
            $this->deleteFile($normalizedPath);
        }

        return $normalizedPaths->count();
    }

    public function renameFile(string $path, string $newName): int
    {
        $normalizedPath = $this->normalizeDirectory($path);
        abort_if($normalizedPath === '', 404);

        $location = $this->resolveLocation($normalizedPath, true);
        $definition = $this->sourceDefinition($location['source']);
        abort_if(! ($definition['writable'] || ($definition['deletable'] ?? false)), 403);

        $sourcePath = $location['path'];
        $currentName = basename($sourcePath);
        $renamedFile = $this->validatedRenamedFileName($newName, $currentName);
        $parentPath = dirname($sourcePath);
        $destinationPath = $parentPath === '.' ? $renamedFile : $parentPath . '/' . $renamedFile;

        if ($sourcePath === $destinationPath) {
            return $location['source'] === 'uploads'
                ? $this->restoreMatchingGalleryAssets($destinationPath)
                : 0;
        }

        $disk = $this->disk($location['source']);
        $qualifiedSource = $this->qualifyPath($location['source'], $sourcePath);
        $qualifiedDestination = $this->qualifyPath($location['source'], $destinationPath);

        abort_unless($disk->exists($qualifiedSource), 404);

        if ($disk->exists($qualifiedDestination)) {
            throw ValidationException::withMessages([
                'new_name' => 'A file with this name already exists in the same location.',
            ]);
        }

        if (($definition['gallery_model'] ?? null) !== null) {
            $this->renameGalleryBackedFile(
                $location['source'],
                $sourcePath,
                $destinationPath,
                $qualifiedSource,
                $qualifiedDestination
            );

            return 0;
        }

        if (! $disk->move($qualifiedSource, $qualifiedDestination)) {
            throw ValidationException::withMessages([
                'new_name' => 'The file could not be renamed. Please try again.',
            ]);
        }

        return $location['source'] === 'uploads'
            ? $this->restoreMatchingGalleryAssets($destinationPath)
            : 0;
    }

    public function replaceGalleryAssetFromUploads(string $targetPath, string $sourcePath): int
    {
        $normalizedTarget = $this->normalizeDirectory($targetPath);
        $normalizedSource = $this->normalizeDirectory($sourcePath);
        abort_if($normalizedTarget === '' || $normalizedSource === '', 404);

        $targetLocation = $this->resolveLocation($normalizedTarget, true);
        $targetDefinition = $this->sourceDefinition($targetLocation['source']);
        abort_if($targetLocation['source'] === 'uploads' || ! isset($targetDefinition['gallery_model']), 403);

        $sourceLocation = $this->resolveLocation($normalizedSource, true);
        abort_if($sourceLocation['source'] !== 'uploads', 422);

        if (! $this->attachedGalleryForPath($targetLocation['source'], $targetLocation['path'])) {
            throw ValidationException::withMessages([
                'target_path' => 'This library image is not attached to a database record.',
            ]);
        }

        $this->assertMatchingFileExtensions($sourceLocation['path'], $targetLocation['path']);
        $this->copyUploadToGallerySource(
            $sourceLocation['path'],
            $targetLocation['source'],
            $targetLocation['path']
        );
        $this->touchGalleryRecords($targetLocation['source'], $targetLocation['path']);

        if (! $this->disk('uploads')->delete($this->qualifyPath('uploads', $sourceLocation['path']))) {
            throw ValidationException::withMessages([
                'source_path' => 'The item image was restored, but the My Files staging copy could not be removed.',
            ]);
        }

        return 1;
    }

    public function normalizeDirectory(string $directory): string
    {
        $normalized = trim(str_replace('\\', '/', $directory), '/');

        if ($normalized === '') {
            return '';
        }

        $segments = collect(explode('/', $normalized))
            ->filter(fn (string $segment) => $segment !== '' && $segment !== '.')
            ->values();

        abort_if($segments->contains(fn (string $segment) => $segment === '..'), 404);

        return $segments->implode('/');
    }

    protected function breadcrumbsFor(string $directory): Collection
    {
        $breadcrumbs = collect([[
            'label' => 'Root',
            'path' => '',
            'active' => $directory === '',
        ]]);

        $current = '';

        foreach (array_values(array_filter(explode('/', $directory))) as $index => $segment) {
            $current = $current === '' ? $segment : $current . '/' . $segment;
            $label = $index === 0 && $this->sourceDefinitions()->has($segment)
                ? $this->sourceDefinition($segment)['label']
                : $segment;

            $breadcrumbs->push([
                'label' => $label,
                'path' => $current,
                'active' => $current === $directory,
            ]);
        }

        return $breadcrumbs;
    }

    protected function parentDirectory(string $directory): ?string
    {
        if ($directory === '' || ! str_contains($directory, '/')) {
            return $directory === '' ? null : '';
        }

        return Str::beforeLast($directory, '/');
    }

    protected function mapStorageFile(string $source, string $path): array
    {
        $relativePath = $this->stripSourcePrefix($source, $path);
        $disk = $this->disk($source);
        $mimeType = $this->mimeTypeFor($path);
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        $isImage = str_starts_with($mimeType, 'image/');
        $sizeBytes = (int) $disk->size($path);
        $updatedTimestamp = $disk->lastModified($path);

        return [
            'name' => basename($relativePath),
            'path' => $source === 'uploads'
                ? $relativePath
                : trim($source . '/' . $relativePath, '/'),
            'url' => $disk->url($path),
            'mime_type' => $mimeType !== '' ? $mimeType : ($extension !== '' ? strtoupper($extension) . ' file' : 'File'),
            'extension' => $extension !== '' ? strtoupper($extension) : '-',
            'category' => $this->categoryFor($mimeType, $extension),
            'size_bytes' => $sizeBytes,
            'size_label' => $this->humanFileSize($sizeBytes),
            'updated_timestamp' => $updatedTimestamp,
            'updated_label' => Carbon::createFromTimestamp($updatedTimestamp)->format('d M Y, h:i A'),
            'updated_relative' => Carbon::createFromTimestamp($updatedTimestamp)->diffForHumans(),
            'is_image' => $isImage,
            'source' => $source,
            'source_label' => $this->sourceDefinition($source)['label'],
            'deletable' => $this->sourceDefinition($source)['writable'] || ($this->sourceDefinition($source)['deletable'] ?? false),
            'renameable' => $this->sourceDefinition($source)['writable'] || ($this->sourceDefinition($source)['deletable'] ?? false),
        ];
    }

    protected function mapUploadDirectory(string $path): array
    {
        $allFiles = collect($this->disk('uploads')->allFiles($path));
        $latestTimestamp = $allFiles
            ->map(fn (string $filePath) => $this->disk('uploads')->lastModified($filePath))
            ->max();

        return [
            'name' => basename($path),
            'path' => $this->stripSourcePrefix('uploads', $path),
            'file_count' => $allFiles->count(),
            'updated_label' => $latestTimestamp
                ? Carbon::createFromTimestamp($latestTimestamp)->diffForHumans()
                : 'Empty folder',
            'source' => 'uploads',
            'writable' => true,
        ];
    }

    protected function mapSourceDirectory(string $source, Collection $files): array
    {
        $latestTimestamp = $files->max('updated_timestamp');

        return [
            'name' => $this->sourceDefinition($source)['label'],
            'path' => $source,
            'file_count' => $files->count(),
            'updated_label' => $latestTimestamp
                ? Carbon::createFromTimestamp($latestTimestamp)->diffForHumans()
                : 'No files yet',
            'source' => $source,
            'writable' => $this->sourceDefinition($source)['writable'],
        ];
    }

    protected function summaryCards(Collection $files): Collection
    {
        $definitions = collect([
            ['key' => 'images', 'label' => 'Images', 'icon' => 'uil-image', 'accent' => 'primary'],
            ['key' => 'documents', 'label' => 'Documents', 'icon' => 'uil-file-alt', 'accent' => 'success'],
            ['key' => 'other', 'label' => 'Other Files', 'icon' => 'uil-folder-open', 'accent' => 'warning'],
        ]);

        $totalBytes = max((int) $files->sum('size_bytes'), 1);

        return $definitions->map(function (array $definition) use ($files, $totalBytes) {
            $matchedFiles = $files->filter(fn (array $file) => $file['category'] === $definition['key']);
            $sizeBytes = (int) $matchedFiles->sum('size_bytes');

            return [
                'label' => $definition['label'],
                'icon' => $definition['icon'],
                'accent' => $definition['accent'],
                'file_count' => $matchedFiles->count(),
                'storage_label' => $this->humanFileSize($sizeBytes),
                'progress' => $sizeBytes > 0 ? max(8, min(100, (int) round(($sizeBytes / $totalBytes) * 100))) : 0,
            ];
        });
    }

    protected function activityChart(Collection $files): Collection
    {
        $definitions = collect([
            ['key' => 'images', 'label' => 'Images', 'accent' => 'success'],
            ['key' => 'documents', 'label' => 'Docs', 'accent' => 'danger'],
            ['key' => 'media', 'label' => 'Media', 'accent' => 'info'],
            ['key' => 'archives', 'label' => 'Zip', 'accent' => 'primary'],
            ['key' => 'other', 'label' => 'Other', 'accent' => 'warning'],
        ]);

        $counts = $definitions->map(function (array $definition) use ($files) {
            return $files->where('category', $definition['key'])->count();
        });

        $maxCount = max($counts->max() ?: 1, 1);

        return $definitions->map(function (array $definition) use ($files, $maxCount) {
            $count = $files->where('category', $definition['key'])->count();

            return [
                'label' => $definition['label'],
                'accent' => $definition['accent'],
                'count' => $count,
                'height' => $count > 0 ? max(16, (int) round(($count / $maxCount) * 100)) : 10,
            ];
        });
    }

    protected function recentTypes(Collection $files): Collection
    {
        $definitions = collect([
            ['key' => 'images', 'label' => 'Images', 'icon' => 'uil-image-upload', 'accent' => 'success'],
            ['key' => 'documents', 'label' => 'Document', 'icon' => 'uil-file-alt', 'accent' => 'primary'],
            ['key' => 'media', 'label' => 'Media', 'icon' => 'uil-play-circle', 'accent' => 'danger'],
            ['key' => 'archives', 'label' => 'Archives', 'icon' => 'uil-archive', 'accent' => 'warning'],
            ['key' => 'other', 'label' => 'Others', 'icon' => 'uil-file-question-alt', 'accent' => 'secondary'],
        ]);

        return $definitions->map(function (array $definition) use ($files) {
            $matchedFiles = $files->where('category', $definition['key']);

            return [
                'label' => $definition['label'],
                'icon' => $definition['icon'],
                'accent' => $definition['accent'],
                'count' => $matchedFiles->count(),
                'size_label' => $this->humanFileSize((int) $matchedFiles->sum('size_bytes')),
            ];
        })->filter(fn (array $type) => $type['count'] > 0)->values();
    }

    protected function categoryFor(string $mimeType, string $extension): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'images';
        }

        if (str_starts_with($mimeType, 'video/') || str_starts_with($mimeType, 'audio/')) {
            return 'media';
        }

        if (in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'ppt', 'pptx'], true)
            || str_contains($mimeType, 'document')
            || str_contains($mimeType, 'text')
            || str_contains($mimeType, 'sheet')
            || str_contains($mimeType, 'pdf')) {
            return 'documents';
        }

        if (in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz'], true)
            || str_contains($mimeType, 'zip')
            || str_contains($mimeType, 'compressed')
            || str_contains($mimeType, 'archive')) {
            return 'archives';
        }

        return 'other';
    }

    protected function mimeTypeFor(string $path): string
    {
        try {
            return (string) ($this->disk('uploads')->mimeType($path) ?? '');
        } catch (\Throwable $exception) {
            return '';
        }
    }

    protected function humanFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $size = $bytes / 1024;

        foreach ($units as $unit) {
            if ($size < 1024 || $unit === 'TB') {
                return number_format($size, $size < 10 ? 1 : 0) . ' ' . $unit;
            }

            $size /= 1024;
        }

        return $bytes . ' B';
    }

    protected function qualifyPath(string $source, string $path = ''): string
    {
        $definition = $this->sourceDefinition($source);
        $basePath = $definition['tenant_scoped'] ? $this->tenantPrefix() : '';

        return trim($basePath . '/' . ltrim($path, '/'), '/');
    }

    protected function stripSourcePrefix(string $source, string $path): string
    {
        $definition = $this->sourceDefinition($source);

        if (! $definition['tenant_scoped']) {
            return ltrim($path, '/');
        }

        $tenantPrefix = $this->tenantPrefix();

        return ltrim(Str::after($path, $tenantPrefix), '/');
    }

    protected function tenantPrefix(): string
    {
        $tenant = tenant() ?: admin_current_tenant();

        abort_if(! $tenant, 404);

        return trim((string) $tenant->getTenantKey(), '/');
    }

    protected function sourceDefinitions(): Collection
    {
        return collect([
            'uploads' => [
                'label' => 'My Files',
                'disk' => 'file_manager',
                'tenant_scoped' => true,
                'writable' => true,
                'uploadable' => true,
                'supports_folders' => true,
                'deletable' => true,
            ],
            'items' => [
                'label' => 'Item Images',
                'disk' => 'item',
                'tenant_scoped' => false,
                'writable' => false,
                'uploadable' => false,
                'supports_folders' => false,
                'gallery_model' => Item::class,
                'owner_key' => 'image_id',
                'deletable' => true,
            ],
            'categories' => [
                'label' => 'Category Images',
                'disk' => 'category',
                'tenant_scoped' => false,
                'writable' => false,
                'uploadable' => false,
                'supports_folders' => false,
                'gallery_model' => Category::class,
                'owner_key' => 'image_id',
                'deletable' => true,
            ],
            'sliders' => [
                'label' => 'Slider Images',
                'disk' => 'slider',
                'tenant_scoped' => false,
                'writable' => false,
                'uploadable' => false,
                'supports_folders' => false,
                'gallery_model' => Slider::class,
                'owner_key' => 'image_id',
                'deletable' => true,
            ],
            'promotions' => [
                'label' => 'Promotion Images',
                'disk' => 'promotion',
                'tenant_scoped' => false,
                'writable' => false,
                'uploadable' => false,
                'supports_folders' => false,
                'gallery_model' => Promotion::class,
                'owner_key' => 'image_id',
                'deletable' => true,
            ],
            'users' => [
                'label' => 'User Images',
                'disk' => 'user',
                'tenant_scoped' => false,
                'writable' => false,
                'uploadable' => false,
                'supports_folders' => false,
                'gallery_model' => User::class,
                'owner_key' => 'profile_id',
                'deletable' => true,
            ],
        ]);
    }

    protected function sourceDefinition(string $source): array
    {
        $definition = $this->sourceDefinitions()->get($source);

        abort_if(! is_array($definition), 404);

        return $definition;
    }

    protected function resolveLocation(string $directory, bool $allowSourceFilePath = false): array
    {
        if ($directory === '') {
            return [
                'source' => 'uploads',
                'path' => '',
            ];
        }

        $segments = explode('/', $directory);
        $source = $segments[0];

        if (! $this->sourceDefinitions()->has($source)) {
            return [
                'source' => 'uploads',
                'path' => $directory,
            ];
        }

        $path = trim(implode('/', array_slice($segments, 1)), '/');
        $definition = $this->sourceDefinition($source);

        if (! ($definition['supports_folders'] ?? false) && $path !== '' && ! $allowSourceFilePath) {
            abort(404);
        }

        return [
            'source' => $source,
            'path' => $path,
        ];
    }

    protected function directoriesForLocation(string $source, string $path, string $directory): Collection
    {
        if ($source !== 'uploads') {
            return collect();
        }

        $qualifiedDirectory = $this->qualifyPath($source, $path);
        $directories = collect($this->disk($source)->directories($qualifiedDirectory))
            ->map(fn (string $directoryPath) => $this->mapUploadDirectory($directoryPath));

        if ($directory === '') {
            $sourceDirectories = $this->sourceDefinitions()
                ->reject(fn (array $definition, string $key) => $key === 'uploads')
                ->map(fn (array $definition, string $key) => $this->mapSourceDirectory($key, $this->allFilesForLocation($key, '')));

            $directories = $directories->merge($sourceDirectories);
        }

        return $directories
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    protected function filesForLocation(string $source, string $path): Collection
    {
        if ($source === 'uploads') {
            $qualifiedDirectory = $this->qualifyPath($source, $path);

            return collect($this->disk($source)->files($qualifiedDirectory))
                ->map(fn (string $filePath) => $this->mapStorageFile($source, $filePath))
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();
        }

        return $this->galleryFilesForSource($source)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    protected function allFilesForLocation(string $source, string $path): Collection
    {
        if ($source === 'uploads') {
            return collect($this->disk($source)->allFiles($this->qualifyPath($source, $path)))
                ->map(fn (string $filePath) => $this->mapStorageFile($source, $filePath))
                ->values();
        }

        return $this->galleryFilesForSource($source)->values();
    }

    protected function allRootFiles(): Collection
    {
        return $this->sourceDefinitions()
            ->keys()
            ->flatMap(fn (string $source) => $this->allFilesForLocation($source, ''))
            ->values();
    }

    protected function galleryFilesForSource(string $source): Collection
    {
        $definition = $this->sourceDefinition($source);
        $connection = tenant() && filled(tenant()->database_connection_name)
            ? tenant()->database_connection_name
            : 'tenant';

        $galleries = (new Gallery())
            ->setConnection($connection)
            ->newQuery()
            ->where('gallarieable_type', $definition['gallery_model'])
            ->whereNotNull('name')
            ->orderByDesc('updated_at')
            ->get();

        $ownerModel = new $definition['gallery_model']();
        $ownerModel->setConnection($connection);
        $owners = $ownerModel->newQuery()
            ->whereKey($galleries->pluck('gallarieable_id')->filter()->unique()->values())
            ->get()
            ->keyBy(fn ($owner) => (string) $owner->getKey());

        $disk = $this->disk($source);

        return $galleries
            ->unique('name')
            ->map(function (Gallery $gallery) use ($source, $disk, $owners) {
                $fileName = (string) $gallery->name;
                $owner = $owners->get((string) $gallery->gallarieable_id);
                $mimeType = '';
                $sizeBytes = 0;
                $isImage = false;

                if ($disk->exists($fileName)) {
                    try {
                        $mimeType = (string) ($disk->mimeType($fileName) ?? '');
                    } catch (\Throwable $exception) {
                        $mimeType = '';
                    }

                    try {
                        $sizeBytes = (int) $disk->size($fileName);
                    } catch (\Throwable $exception) {
                        $sizeBytes = 0;
                    }

                    $isImage = str_starts_with($mimeType, 'image/');
                }

                $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $timestamp = optional($gallery->updated_at)->timestamp ?: optional($gallery->created_at)->timestamp ?: now()->timestamp;

                return [
                    'name' => basename($fileName),
                    'path' => trim($source . '/' . $fileName, '/'),
                    'url' => $disk->url($fileName),
                    'mime_type' => $mimeType !== '' ? $mimeType : ($extension !== '' ? strtoupper($extension) . ' file' : 'File'),
                    'extension' => $extension !== '' ? strtoupper($extension) : '-',
                    'category' => $this->categoryFor($mimeType, $extension),
                    'size_bytes' => $sizeBytes,
                    'size_label' => $this->humanFileSize($sizeBytes),
                    'updated_timestamp' => $timestamp,
                    'updated_label' => Carbon::createFromTimestamp($timestamp)->format('d M Y, h:i A'),
                    'updated_relative' => Carbon::createFromTimestamp($timestamp)->diffForHumans(),
                    'is_image' => $isImage || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true),
                    'source' => $source,
                    'source_label' => $this->sourceDefinition($source)['label'],
                    'deletable' => (bool) ($this->sourceDefinition($source)['deletable'] ?? false),
                    'renameable' => (bool) ($this->sourceDefinition($source)['deletable'] ?? false),
                    'attached' => $owner !== null,
                    'owner_label' => $owner ? $this->galleryOwnerLabel($owner) : null,
                    'replaceable' => $owner !== null,
                ];
            });
    }

    protected function restoreMatchingGalleryAssets(string $uploadPath): int
    {
        $fileName = basename($uploadPath);
        $matchesBySource = $this->sourceDefinitions()
            ->reject(fn (array $definition, string $source) => $source === 'uploads' || ! isset($definition['gallery_model']))
            ->mapWithKeys(fn (array $definition, string $source) => [
                $source => $this->attachedGalleriesForPath($source, $fileName),
            ])
            ->filter(fn (Collection $galleries) => $galleries->isNotEmpty());

        if ($matchesBySource->isEmpty()) {
            return 0;
        }

        $restoredRecords = 0;

        foreach ($matchesBySource as $source => $galleries) {
            $this->assertMatchingFileExtensions($uploadPath, $fileName);
            $this->copyUploadToGallerySource($uploadPath, $source, $fileName);
            $this->touchGalleryRecords($source, $fileName);
            $restoredRecords += $galleries->count();
        }

        if (! $this->disk('uploads')->delete($this->qualifyPath('uploads', $uploadPath))) {
            throw ValidationException::withMessages([
                'files' => 'The linked images were restored, but the My Files staging copy could not be removed.',
            ]);
        }

        return $restoredRecords;
    }

    protected function attachedGalleryForPath(string $source, string $path): ?Gallery
    {
        return $this->attachedGalleriesForPath($source, $path)->first();
    }

    protected function attachedGalleriesForPath(string $source, string $path): Collection
    {
        $definition = $this->sourceDefinition($source);
        $connection = tenant() && filled(tenant()->database_connection_name)
            ? tenant()->database_connection_name
            : 'tenant';
        $galleries = (new Gallery())
            ->setConnection($connection)
            ->newQuery()
            ->where('gallarieable_type', $definition['gallery_model'])
            ->where('name', $path)
            ->whereNotNull('gallarieable_id')
            ->get();

        if ($galleries->isEmpty()) {
            return collect();
        }

        $ownerModel = new $definition['gallery_model']();
        $ownerModel->setConnection($connection);
        $ownerIds = $ownerModel->newQuery()
            ->whereKey($galleries->pluck('gallarieable_id')->unique()->values())
            ->pluck($ownerModel->getKeyName())
            ->map(fn ($id) => (string) $id)
            ->all();

        return $galleries
            ->filter(fn (Gallery $gallery) => in_array((string) $gallery->gallarieable_id, $ownerIds, true))
            ->values();
    }

    protected function copyUploadToGallerySource(string $uploadPath, string $targetSource, string $targetPath): void
    {
        $sourceDisk = $this->disk('uploads');
        $qualifiedSource = $this->qualifyPath('uploads', $uploadPath);
        abort_unless($sourceDisk->exists($qualifiedSource), 404);

        $mimeType = (string) ($sourceDisk->mimeType($qualifiedSource) ?? '');

        if (! str_starts_with($mimeType, 'image/')) {
            throw ValidationException::withMessages([
                'source_path' => 'Only valid image files can replace a gallery image.',
            ]);
        }

        $targetDisk = $this->disk($targetSource);
        $qualifiedTarget = $this->qualifyPath($targetSource, $targetPath);
        $targetExisted = $targetDisk->exists($qualifiedTarget);
        $backupPath = '.file-manager-backups/' . Str::uuid() . '-' . basename($targetPath);

        if ($targetExisted && ! $targetDisk->copy($qualifiedTarget, $backupPath)) {
            throw ValidationException::withMessages([
                'source_path' => 'The existing library image could not be backed up before replacement.',
            ]);
        }

        $stream = $sourceDisk->readStream($qualifiedSource);

        if (! is_resource($stream)) {
            if ($targetExisted) {
                $targetDisk->delete($backupPath);
            }

            throw ValidationException::withMessages([
                'source_path' => 'The My Files image could not be read.',
            ]);
        }

        try {
            $written = $targetDisk->writeStream($qualifiedTarget, $stream, ['visibility' => 'public']);
            $sourceSize = (int) $sourceDisk->size($qualifiedSource);
            $targetSize = $targetDisk->exists($qualifiedTarget) ? (int) $targetDisk->size($qualifiedTarget) : -1;

            if (! $written || $targetSize !== $sourceSize) {
                throw ValidationException::withMessages([
                    'source_path' => 'The replacement image could not be verified in storage.',
                ]);
            }
        } catch (\Throwable $exception) {
            if ($targetExisted && $targetDisk->exists($backupPath)) {
                $targetDisk->copy($backupPath, $qualifiedTarget);
            } elseif (! $targetExisted) {
                $targetDisk->delete($qualifiedTarget);
            }

            throw $exception;
        } finally {
            fclose($stream);
        }

        if ($targetExisted) {
            $targetDisk->delete($backupPath);
        }
    }

    protected function touchGalleryRecords(string $source, string $path): void
    {
        $definition = $this->sourceDefinition($source);
        $connection = tenant() && filled(tenant()->database_connection_name)
            ? tenant()->database_connection_name
            : 'tenant';

        (new Gallery())
            ->setConnection($connection)
            ->newQuery()
            ->where('gallarieable_type', $definition['gallery_model'])
            ->where('name', $path)
            ->update(['updated_at' => now()]);

        $galleryModel = $definition['gallery_model'];

        if (method_exists($galleryModel, 'flushQueryCache')) {
            $galleryModel::flushQueryCache();
        }
    }

    protected function assertMatchingFileExtensions(string $sourcePath, string $targetPath): void
    {
        $sourceExtension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $targetExtension = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

        if ($sourceExtension === '' || $sourceExtension !== $targetExtension) {
            throw ValidationException::withMessages([
                'source_path' => 'The staging image must use the same file extension as the target image.',
            ]);
        }
    }

    protected function galleryOwnerLabel($owner): string
    {
        $identifier = $owner->sku ?? $owner->code ?? $owner->email ?? null;
        $name = $owner->name ?? $owner->title ?? null;

        if (filled($identifier) && filled($name)) {
            return $identifier . ' — ' . $name;
        }

        return (string) ($name ?: $identifier ?: ('Record #' . $owner->getKey()));
    }

    protected function renameGalleryBackedFile(
        string $source,
        string $sourcePath,
        string $destinationPath,
        string $qualifiedSource,
        string $qualifiedDestination
    ): void {
        $definition = $this->sourceDefinition($source);
        $connection = tenant() && filled(tenant()->database_connection_name)
            ? tenant()->database_connection_name
            : 'tenant';
        $galleryQuery = (new Gallery())
            ->setConnection($connection)
            ->newQuery()
            ->where('gallarieable_type', $definition['gallery_model']);

        if ((clone $galleryQuery)->where('name', $destinationPath)->exists()) {
            throw ValidationException::withMessages([
                'new_name' => 'A file with this name already exists in the same library.',
            ]);
        }

        $disk = $this->disk($source);

        if (! $disk->move($qualifiedSource, $qualifiedDestination)) {
            throw ValidationException::withMessages([
                'new_name' => 'The file could not be renamed. Please try again.',
            ]);
        }

        try {
            (clone $galleryQuery)
                ->where('name', $sourcePath)
                ->update([
                    'name' => $destinationPath,
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $exception) {
            $disk->move($qualifiedDestination, $qualifiedSource);

            throw $exception;
        }

        $galleryModel = $definition['gallery_model'];

        if (method_exists($galleryModel, 'flushQueryCache')) {
            $galleryModel::flushQueryCache();
        }
    }

    protected function validatedRenamedFileName(string $newName, string $currentName): string
    {
        $cleanName = basename(str_replace('\\', '/', $newName));
        $cleanName = preg_replace('/[\x00-\x1F\x7F]/u', '', $cleanName) ?? '';
        $cleanName = trim($cleanName, " .\t\n\r\0\x0B");

        if ($cleanName === '' || in_array($cleanName, ['.', '..'], true)) {
            throw ValidationException::withMessages([
                'new_name' => 'Enter a valid file name.',
            ]);
        }

        $currentExtension = pathinfo($currentName, PATHINFO_EXTENSION);

        if ($currentExtension === '') {
            return mb_substr($cleanName, 0, 180);
        }

        $submittedExtension = pathinfo($cleanName, PATHINFO_EXTENSION);

        if ($submittedExtension === '') {
            $stem = $cleanName;
        } elseif (strcasecmp($submittedExtension, $currentExtension) === 0) {
            $stem = pathinfo($cleanName, PATHINFO_FILENAME);
        } else {
            throw ValidationException::withMessages([
                'new_name' => "The file extension must remain .{$currentExtension}.",
            ]);
        }

        $stem = trim(mb_substr($stem, 0, 180), " .\t\n\r\0\x0B");

        if ($stem === '') {
            throw ValidationException::withMessages([
                'new_name' => 'Enter a valid file name.',
            ]);
        }

        return $stem . '.' . $currentExtension;
    }

    protected function deleteGalleryBackedFile(string $source, string $path): void
    {
        $definition = $this->sourceDefinition($source);
        $connection = tenant() && filled(tenant()->database_connection_name)
            ? tenant()->database_connection_name
            : 'tenant';

        $galleries = (new Gallery())
            ->setConnection($connection)
            ->newQuery()
            ->where('gallarieable_type', $definition['gallery_model'])
            ->where('name', $path)
            ->get();

        foreach ($galleries as $gallery) {
            $this->detachGalleryFromOwner($gallery, $definition);
            $gallery->delete();
        }

        if ($this->disk($source)->exists($path)) {
            $this->disk($source)->delete($path);
        }

        $galleryModel = $definition['gallery_model'];

        if (method_exists($galleryModel, 'flushQueryCache')) {
            $galleryModel::flushQueryCache();
        }
    }

    protected function storeGalleryBackedUpload(string $source, UploadedFile $file): void
    {
        $definition = $this->sourceDefinition($source);
        $fileName = $this->originalUploadName($file, Str::singular($source));

        $this->disk($source)->putFileAs('', $file, $fileName);

        $connection = tenant() && filled(tenant()->database_connection_name)
            ? tenant()->database_connection_name
            : 'tenant';

        $galleryQuery = (new Gallery())
            ->setConnection($connection)
            ->newQuery()
            ->where('gallarieable_type', $definition['gallery_model'])
            ->where('name', $fileName);

        if ($galleryQuery->exists()) {
            $galleryQuery->update(['updated_at' => now()]);
        } else {
            $gallery = new Gallery();
            $gallery->setConnection($connection);
            $gallery->forceFill([
                'gallarieable_type' => $definition['gallery_model'],
                'gallarieable_id' => null,
                'type' => 'other',
                'status' => 'Active',
                'name' => $fileName,
            ])->save();
        }

        $galleryModel = $definition['gallery_model'];

        if (method_exists($galleryModel, 'flushQueryCache')) {
            $galleryModel::flushQueryCache();
        }
    }

    protected function originalUploadName(UploadedFile $file, string $fallback): string
    {
        $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?? '';
        $originalName = trim($originalName, " .\t\n\r\0\x0B");

        if ($originalName !== '') {
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $stem = pathinfo($originalName, PATHINFO_FILENAME);
            $stem = mb_substr($stem, 0, 180);

            if ($stem !== '') {
                return $extension !== '' ? $stem.'.'.mb_substr($extension, 0, 20) : $stem;
            }
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');

        return Str::slug($fallback) . '.' . $extension;
    }

    protected function detachGalleryFromOwner(Gallery $gallery, array $definition): void
    {
        $ownerKey = $definition['owner_key'] ?? null;

        if (! $ownerKey) {
            return;
        }

        $ownerModel = new $definition['gallery_model']();
        $ownerModel->setConnection($gallery->getConnectionName());
        $owner = $ownerModel->newQuery()->whereKey($gallery->gallarieable_id)->first();

        if (! $owner) {
            return;
        }

        if ((int) ($owner->{$ownerKey} ?? 0) === (int) $gallery->getKey()) {
            $owner->forceFill([$ownerKey => null])->save();
        }
    }

    protected function disk(string $source)
    {
        return Storage::disk($this->sourceDefinition($source)['disk']);
    }
}
