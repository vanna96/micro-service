<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Repositories\PosRepository;
use App\Services\PromotionPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(
        protected PosRepository $pos,
        protected PromotionPricingService $promotionPricing
    ) {
        $this->middleware('admin.permission:pos.view')->only(['index', 'price']);
    }

    public function index(Request $request): View
    {
        $selectedTenant = $this->requiredTenant($request);
        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $search = trim((string) $request->input('search', ''));

        return view('admin.pos.index', [
            'selectedTenant' => $selectedTenant,
            'workspace' => $this->pos->getWorkspace($branchId, $categoryId, $search),
        ]);
    }

    public function price(Request $request): JsonResponse
    {
        $this->requiredTenant($request);

        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $payloadItems = collect($request->input('items', []));
            $requestedIds = $payloadItems->pluck('item_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values();

            if ($requestedIds->isEmpty()) {
                return;
            }

            $activeIds = $this->pos->getActiveItemIds($requestedIds->all());
            $invalidIds = $requestedIds->diff($activeIds);

            if ($invalidIds->isEmpty()) {
                return;
            }

            $payloadItems->values()->each(function (array $line, int $index) use ($validator, $invalidIds) {
                if ($invalidIds->contains((int) ($line['item_id'] ?? 0))) {
                    $validator->errors()->add("items.{$index}.item_id", 'Selected item must exist and be active.');
                }
            });
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        return response()->json(
            $this->promotionPricing->price($validated['items'])
        );
    }

    protected function requiredTenant(Request $request): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        return $tenant;
    }
}
