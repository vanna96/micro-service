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
        $this->middleware('admin.permission:sliders.manage')->except(['index']);
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
                'placement' => 'Website',
                'status' => 'Active',
                'sort_order' => 0,
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
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'placement' => ['required', Rule::in(['Website', 'Mobile'])],
            'target_url' => ['nullable', 'url', 'max:2048'],
            'image' => [$slider ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
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
