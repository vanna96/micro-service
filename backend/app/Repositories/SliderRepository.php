<?php

namespace App\Repositories;

use App\Models\Gallery;
use App\Models\Slider;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SliderRepository extends RepositoryBase
{
    protected $repositoryId = 'rinvex.repository.id';
    protected $model = 'App\Models\Slider';

    public function getAdminListing(string $search = ''): Collection
    {
        $query = $this->sliderModel()
            ->newQuery()
            ->cacheFor($this->sliderCacheTtl())
            ->cachePrefix($this->sliderListingCachePrefix())
            ->cacheTags($this->sliderListingCacheTags())
            ->with('image')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('placement', 'like', "%{$search}%")
                        ->orWhere('target_url', 'like', "%{$search}%")
                        ->orWhere('recommended_dimensions', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('placement')
            ->orderByDesc('id');

        $this->reportCacheState($query, 'admin.sliders.index');

        return $query->get();
    }

    public function loadForAdminEdit(int $sliderId): Slider
    {
        $query = $this->sliderModel()
            ->newQuery()
            ->with(['galleries', 'image'])
            ->whereKey($sliderId);

        $this->reportCacheState($query, 'admin.sliders.edit');

        return $query->firstOrFail();
    }

    public function createForAdmin(array $attributes): Slider
    {
        $image = $attributes['image'] ?? null;
        $videoFile = $attributes['video_file'] ?? null;
        unset($attributes['image'], $attributes['video_file']);

        $slider = $this->sliderModel();
        $slider->fill($this->normalizeAttributes($attributes));
        $slider->save();
        $this->syncImageUpload($slider, $image);
        $this->syncVideoUpload($slider, $videoFile);
        Slider::flushQueryCache();

        return $slider;
    }

    public function updateForAdmin(Slider $slider, array $attributes): Slider
    {
        $image = $attributes['image'] ?? null;
        $videoFile = $attributes['video_file'] ?? null;
        unset($attributes['image'], $attributes['video_file']);

        $slider->fill($this->normalizeAttributes($attributes));
        $slider->save();
        $this->syncImageUpload($slider, $image);
        $this->syncVideoUpload($slider, $videoFile);
        Slider::flushQueryCache();

        return $slider;
    }

    public function deleteForAdmin(Slider $slider): void
    {
        $this->deleteSliderImage($slider);
        $slider->delete();
        Slider::flushQueryCache();
    }

    protected function normalizeAttributes(array $attributes): array
    {
        $placement = $attributes['placement'] ?? 'Website';
        $attributes['recommended_dimensions'] = $this->recommendedDimensionsForPlacement($placement);
        $attributes['sort_order'] = (int) ($attributes['sort_order'] ?? 0);

        if (empty($attributes['media_type'])) {
            if (!empty($attributes['media_url'])) {
                $attributes['media_type'] = 'video';
            } elseif (!empty($attributes['gradient'])) {
                $attributes['media_type'] = 'gradient';
            } else {
                $attributes['media_type'] = 'image';
            }
        }

        return $attributes;
    }

    protected function recommendedDimensionsForPlacement(string $placement): string
    {
        if ($placement === 'Mobile') {
            return 'Recommended mobile banner ratio 4:5 or 9:16, for example 1080x1350 or 1080x1920.';
        }

        if ($placement === 'second_screen') {
            return 'Recommended customer display 16:9 widescreen or video showcase loop (e.g. 1920x1080 or MP4 video).';
        }

        if ($placement === 'All') {
            return 'Universal multi-channel placement across Website, Mobile App, and Customer Display.';
        }

        return 'Recommended website banner ratio 16:9 or wider, for example 1600x900 or 1920x800.';
    }

    protected function syncImageUpload(Slider $slider, $image): void
    {
        if (! $image instanceof UploadedFile) {
            return;
        }

        $this->deleteSliderImage($slider);

        $extension = strtolower($image->getClientOriginalExtension() ?: $image->extension() ?: 'jpg');
        $fileName = 'slider_' . Str::uuid()->toString() . '.' . $extension;

        Storage::disk('slider')->putFileAs('', $image, $fileName);

        $gallery = $slider->galleries()->create([
            'type' => 'thumbnail',
            'status' => 'Active',
            'name' => $fileName,
        ]);

        $slider->forceFill(['image_id' => $gallery->id, 'media_url' => null, 'media_type' => 'image'])->save();
    }

    protected function deleteSliderImage(Slider $slider): void
    {
        if (! $slider->image_id) {
            return;
        }

        $gallery = $slider->galleries()->find($slider->image_id);

        if (! $gallery instanceof Gallery) {
            $slider->forceFill(['image_id' => null])->save();

            return;
        }

        if (Storage::disk('slider')->exists($gallery->name)) {
            Storage::disk('slider')->delete($gallery->name);
        }

        $gallery->delete();
        $slider->forceFill(['image_id' => null])->save();
    }

    protected function syncVideoUpload(Slider $slider, $videoFile): void
    {
        if (! $videoFile instanceof UploadedFile) {
            return;
        }

        $extension = strtolower($videoFile->getClientOriginalExtension() ?: $videoFile->extension() ?: 'mp4');
        $fileName = 'video_' . Str::uuid()->toString() . '.' . $extension;

        Storage::disk('slider')->putFileAs('videos', $videoFile, $fileName);

        $slider->forceFill([
            'media_url' => '/uploads/slider/videos/' . $fileName,
            'media_type' => 'video',
        ])->save();
    }

    protected function sliderCacheTtl(): int
    {
        return (int) config('query-cache.models.slider', config('query-cache.default_ttl', 300));
    }

    protected function sliderListingCacheTags(): array
    {
        $tenantTag = tenant()
            ? 'tenant-slider-listing:' . tenant()->getTenantKey()
            : 'tenant-slider-listing:central';

        return [
            'admin-slider-listing',
            $tenantTag,
        ];
    }

    protected function sliderListingCachePrefix(): string
    {
        $tenantKey = tenant()
            ? (string) tenant()->getTenantKey()
            : 'central';

        return (string) config('query-cache.prefix', 'micro_service_backend') . ':slider-listing:' . $tenantKey;
    }

    protected function sliderModel(): Slider
    {
        /** @var \App\Models\Slider $model */
        $model = $this->createModel();

        if (tenant()) {
            $model->setConnection(tenant()->database_connection_name);
        }

        return $model;
    }

    protected function reportCacheState(EloquentBuilder $query, string $context): void
    {
        if (! config('app.debug')) {
            return;
        }

        $baseQuery = $query->getQuery();

        if (! method_exists($baseQuery, 'getCacheKey') || ! method_exists($baseQuery, 'getCache')) {
            return;
        }

        $cacheKey = $baseQuery->getCacheKey('get');
        $cacheHit = $baseQuery->getCache()->has($cacheKey);
        $payload = [
            'context' => $context,
            'cache_hit' => $cacheHit,
            'cache_key' => $cacheKey,
            'ttl' => $this->sliderCacheTtl(),
        ];

        Log::debug('Slider repository query cache state', $payload);

        if (class_exists(\Barryvdh\Debugbar\Facades\Debugbar::class) && app()->bound('debugbar')) {
            \Barryvdh\Debugbar\Facades\Debugbar::addMessage($payload, 'query-cache');
        }
    }
}
