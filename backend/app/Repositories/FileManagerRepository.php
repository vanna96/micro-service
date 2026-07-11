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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileManagerRepository extends RepositoryBase
{
    protected string $disk = 'file_manager';

    public function getListing(string $directory = ''): array
    {
        $normalizedDirectory = $this->normalizeDirectory($directory);
        $location = $this->resolveLocation($normalizedDirectory);
        $currentFiles = $this->filesForLocation($location['source'], $location['path']);
        $allFiles = $normalizedDirectory === ''
            ? $this->allRootFiles()
            : $this->allFilesForLocation($location['source'], $location['path']);

        return [
            'current_directory' => $normalizedDirectory,
            'current_source' => $location['source'],
            'current_source_label' => $this->sourceDefinition($location['source'])['label'],
            'current_source_writable' => $this->sourceDefinition($location['source'])['writable'],
            'current_source_uploadable' => $this->sourceDefinition($location['source'])['uploadable'] ?? false,
            'current_source_supports_folders' => $this->sourceDefinition($location['source'])['supports_folders'] ?? false,
            'breadcrumbs' => $this->breadcrumbsFor($normalizedDirectory),
            'directories' => $this->directoriesForLocation($location['source'], $location['path'], $normalizedDirectory),
            'files' => $currentFiles,
            'recent_files' => $allFiles
                ->sortByDesc('updated_timestamp')
                ->take(6)
                ->values(),
            'summary_cards' => $this->summaryCards($allFiles),
            'activity_chart' => $this->activityChart($allFiles),
            'recent_types' => $this->recentTypes($allFiles),
            'total_files_count' => $allFiles->count(),
            'total_storage_label' => $this->humanFileSize((int) $allFiles->sum('size_bytes')),
            'parent_directory' => $this->parentDirectory($normalizedDirectory),
        ];
    }

    public function uploadFiles(array $files, string $directory = ''): void
    {
        $normalizedDirectory = $this->normalizeDirectory($directory);
        $location = $this->resolveLocation($normalizedDirectory);
        $definition = $this->sourceDefinition($location['source']);

        abort_if(! ($definition['uploadable'] ?? false), 403);

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            if (($definition['gallery_model'] ?? null) !== null) {
                $this->storeGalleryBackedUpload($location['source'], $file);

                continue;
            }

            $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
            $safeName = Str::slug($name);
            $safeName = $safeName !== '' ? $safeName : 'file';
            $fileName = $safeName . '-' . Str::lower(Str::random(8)) . '.' . $extension;

            $this->disk($location['source'])->putFileAs($this->qualifyPath($location['source'], $location['path']), $file, $fileName);
        }
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
                'uploadable' => true,
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
                'uploadable' => true,
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
                'uploadable' => true,
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
                'uploadable' => true,
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
                'uploadable' => true,
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

        $disk = $this->disk($source);

        return $galleries
            ->unique('name')
            ->map(function (Gallery $gallery) use ($source, $disk) {
                $fileName = (string) $gallery->name;
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
                ];
            });
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
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $safeName = Str::slug($name);
        $safeName = $safeName !== '' ? $safeName : Str::singular($source);
        $fileName = $safeName . '-' . Str::lower(Str::random(8)) . '.' . $extension;

        $this->disk($source)->putFileAs('', $file, $fileName);

        $connection = tenant() && filled(tenant()->database_connection_name)
            ? tenant()->database_connection_name
            : 'tenant';

        $gallery = new Gallery();
        $gallery->setConnection($connection);
        $gallery->forceFill([
            'gallarieable_type' => $definition['gallery_model'],
            'gallarieable_id' => null,
            'type' => 'other',
            'status' => 'Active',
            'name' => $fileName,
        ])->save();

        $galleryModel = $definition['gallery_model'];

        if (method_exists($galleryModel, 'flushQueryCache')) {
            $galleryModel::flushQueryCache();
        }
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
