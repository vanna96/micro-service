<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use App\Models\Tenant;
use App\Repositories\SliderRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SliderController extends Controller
{
    protected SliderRepository $sliders;

    public function __construct(SliderRepository $sliders)
    {
        $this->sliders = $sliders;
        $this->middleware('admin.permission:sliders.view')->only(['index']);
        $this->middleware('admin.permission:sliders.create')->only(['create', 'store']);
        $this->middleware('admin.permission:sliders.edit')->only(['edit', 'update']);
        $this->middleware('admin.permission:sliders.delete')->only(['destroy']);
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.sliders.index', [
            'sliders' => $this->sliders->getAdminListing($search),
            'search' => $search,
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.sliders.create', [
            'slider' => new Slider([
                'placement' => 'second_screen',
                'media_type' => 'gradient',
                'status' => 'Active',
                'sort_order' => 0,
                'badge' => '☕ SPECIAL PROMO',
                'badge_bg' => 'rgba(255, 255, 255, 0.95)',
                'badge_color' => '#0f172a',
                'gradient' => 'linear-gradient(135deg, #1e1b4b 0%, #312e81 45%, #4338ca 100%)',
                'icon' => 'ri-cup-line',
            ]),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant($request);
        $validated = $this->validateSlider($request);
        $this->sliders->createForAdmin($validated);

        return redirect()
            ->route('admin.sliders.index')
            ->with('status', 'Slider created successfully.');
    }

    public function edit(Request $request, string $slider): View
    {
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.sliders.edit', [
            'slider' => $this->sliders->loadForAdminEdit((int) $slider),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function update(Request $request, string $slider): RedirectResponse
    {
        $this->requiredTenant($request);
        $sliderModel = $this->sliders->loadForAdminEdit((int) $slider);
        $validated = $this->validateSlider($request, $sliderModel);
        $this->sliders->updateForAdmin($sliderModel, $validated);

        return redirect()
            ->route('admin.sliders.index')
            ->with('status', 'Slider updated successfully.');
    }

    public function destroy(Request $request, string $slider): RedirectResponse
    {
        $this->requiredTenant($request);
        $sliderModel = $this->sliders->loadForAdminEdit((int) $slider);
        $this->sliders->deleteForAdmin($sliderModel);

        return redirect()
            ->route('admin.sliders.index')
            ->with('status', 'Slider deleted successfully.');
    }

    private function validateSlider(Request $request, ?Slider $slider = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'placement' => ['required', Rule::in(['Website', 'Mobile', 'second_screen', 'All'])],
            'media_type' => ['nullable', 'string', Rule::in(['image', 'video', 'gradient'])],
            'media_url' => ['nullable', 'string', 'max:1000'],
            'badge' => ['nullable', 'string', 'max:100'],
            'badge_bg' => ['nullable', 'string', 'max:100'],
            'badge_color' => ['nullable', 'string', 'max:100'],
            'discount' => ['nullable', 'string', 'max:100'],
            'gradient' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:100'],
            'tag' => ['nullable', 'string', 'max:255'],
            'target_url' => ['nullable', 'url', 'max:2048'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'video_file' => ['nullable', 'file', 'mimes:mp4,webm,mov,ogg,m4v', 'max:51200'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }

    private function requiredTenant(Request $request): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        return $tenant;
    }
}
