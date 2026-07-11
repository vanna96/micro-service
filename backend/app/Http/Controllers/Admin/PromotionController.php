<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Tenant;
use App\Repositories\PromotionRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PromotionController extends Controller
{
    protected PromotionRepository $promotions;

    public function __construct(PromotionRepository $promotions)
    {
        $this->promotions = $promotions;
        $this->middleware('admin.permission:promotions.view')->only(['index']);
        $this->middleware('admin.permission:promotions.manage')->except(['index']);
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.promotions.index', [
            'promotions' => $this->promotions->getAdminListing($search),
            'search' => $search,
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.promotions.create', [
            'promotion' => new Promotion([
                'type' => Promotion::TYPE_ITEM_PRICE,
                'status' => 'Active',
                'start_at' => now()->startOfDay(),
                'end_at' => now()->endOfDay(),
            ]),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant($request);
        $validated = $this->validatePromotion($request);
        $promotion = $this->promotions->createForAdmin($validated);

        return redirect()
            ->route('admin.promotions.edit', ['promotion' => $promotion->id])
            ->with('status', 'Promotion created successfully. Continue below to set up the promotion items.');
    }

    public function edit(Request $request, string $promotion): View
    {
        $selectedTenant = $this->requiredTenant($request);
        $promotionModel = $this->promotions->loadForAdminEdit((int) $promotion);

        return view('admin.promotions.edit', [
            'promotion' => $promotionModel,
            'itemOptions' => $this->promotions->getItemOptions(),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function update(Request $request, string $promotion): RedirectResponse
    {
        $this->requiredTenant($request);
        $promotionModel = $this->promotions->loadForAdminEdit((int) $promotion);
        $validated = $this->validatePromotion($request, $promotionModel);
        $this->promotions->updateForAdmin($promotionModel, $validated);

        return redirect()
            ->route('admin.promotions.edit', ['promotion' => $promotionModel->id])
            ->with('status', 'Promotion updated successfully.');
    }

    public function destroy(Request $request, string $promotion): RedirectResponse
    {
        $this->requiredTenant($request);
        $promotionModel = $this->promotions->loadForAdminEdit((int) $promotion);
        $this->promotions->deleteForAdmin($promotionModel);

        return redirect()
            ->route('admin.promotions.index')
            ->with('status', 'Promotion deleted successfully.');
    }

    public function storeLine(Request $request, string $promotion): RedirectResponse
    {
        $this->requiredTenant($request);
        $promotionModel = $this->promotions->loadForAdminEdit((int) $promotion);
        $validated = $this->validateStoreLine($request, $promotionModel);
        $this->promotions->createLineForAdmin($promotionModel, $validated);

        return redirect()
            ->route('admin.promotions.edit', ['promotion' => $promotionModel->id])
            ->with('status', 'Promotion item added successfully.');
    }

    public function updateLine(Request $request, string $promotion, string $line): RedirectResponse
    {
        $this->requiredTenant($request);
        $promotionModel = $this->promotions->loadForAdminEdit((int) $promotion);
        $lineModel = $promotionModel->lines()->whereKey((int) $line)->firstOrFail();
        $validated = $this->validateUpdateLine($request, $lineModel);
        $this->promotions->updateLineForAdmin($lineModel, $validated);

        return redirect()
            ->route('admin.promotions.edit', ['promotion' => $promotionModel->id])
            ->with('status', 'Promotion item updated successfully.');
    }

    public function destroyLine(Request $request, string $promotion, string $line): RedirectResponse
    {
        $this->requiredTenant($request);
        $promotionModel = $this->promotions->loadForAdminEdit((int) $promotion);
        $lineModel = $promotionModel->lines()->whereKey((int) $line)->firstOrFail();
        $this->promotions->deleteLineForAdmin($lineModel);

        return redirect()
            ->route('admin.promotions.edit', ['promotion' => $promotionModel->id])
            ->with('status', 'Promotion item removed successfully.');
    }

    private function validatePromotion(Request $request, ?Promotion $promotion = null): array
    {
        $promotionId = $promotion?->id;
        $table = $this->promotionValidationTable();

        return $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique($table, 'code')->ignore($promotionId)],
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'type' => ['required', Rule::in([
                Promotion::TYPE_ITEM_PRICE,
                Promotion::TYPE_SUBTOTAL_DISCOUNT,
                Promotion::TYPE_BOGO,
            ])],
            'description' => ['nullable', 'string'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'threshold_amount' => [
                'nullable',
                'numeric',
                'gt:0',
                Rule::requiredIf($request->input('type') === Promotion::TYPE_SUBTOTAL_DISCOUNT),
            ],
            'reward_discount_percent' => [
                'nullable',
                'integer',
                'between:1,100',
                Rule::requiredIf($request->input('type') === Promotion::TYPE_SUBTOTAL_DISCOUNT),
            ],
            'buy_quantity' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf($request->input('type') === Promotion::TYPE_BOGO),
            ],
            'get_quantity' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf($request->input('type') === Promotion::TYPE_BOGO),
            ],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }

    private function validateStoreLine(Request $request, Promotion $promotion): array
    {
        if (! $promotion->usesItemLines()) {
            throw ValidationException::withMessages([
                'type' => 'This promotion type does not use item lines.',
            ]);
        }

        $table = $this->promotionItemValidationTable();
        $itemTable = $this->itemValidationTable();
        if ($promotion->supportsLinePricing()) {
            return $request->validate([
                'item_id' => [
                    'required',
                    'integer',
                    Rule::exists($itemTable, 'id'),
                    Rule::unique($table, 'item_id')->where(fn ($query) => $query
                        ->where('promotion_id', $promotion->id)
                        ->where('line_role', 'item')),
                ],
                'pricing_method' => ['required', Rule::in(['fixed', 'discount'])],
                'fixed_price' => ['nullable', 'numeric', 'min:0', Rule::requiredIf($request->input('pricing_method') === 'fixed')],
                'discount_percent' => ['nullable', 'integer', 'between:0,100', Rule::requiredIf($request->input('pricing_method') === 'discount')],
                'status' => ['required', Rule::in(['Active', 'Inactive'])],
            ]);
        }

        return $request->validate([
            'item_id' => ['required', 'integer', Rule::exists($itemTable, 'id')],
            'line_role' => [
                'required',
                Rule::in([Promotion::BOGO_ROLE_BUY, Promotion::BOGO_ROLE_GET]),
                function (string $attribute, mixed $value, \Closure $fail) use ($promotion) {
                    if ($promotion->lines()->where('line_role', $value)->exists()) {
                        $fail('This promotion already has a ' . $value . ' item.');
                    }
                },
            ],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }

    private function validateUpdateLine(Request $request, PromotionItem $line): array
    {
        $promotion = $line->promotion()->firstOrFail();
        $rules = [
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];

        if ($promotion->supportsLinePricing()) {
            $rules['pricing_method'] = ['required', Rule::in(['fixed', 'discount'])];
            $rules['fixed_price'] = ['nullable', 'numeric', 'min:0', Rule::requiredIf($request->input('pricing_method') === 'fixed')];
            $rules['discount_percent'] = ['nullable', 'integer', 'between:0,100', Rule::requiredIf($request->input('pricing_method') === 'discount')];
        }

        if ($promotion->type === Promotion::TYPE_BOGO) {
            $rules['line_role'] = [
                'required',
                Rule::in([Promotion::BOGO_ROLE_BUY, Promotion::BOGO_ROLE_GET]),
                function (string $attribute, mixed $value, \Closure $fail) use ($promotion, $line) {
                    if ($promotion->lines()
                        ->where('line_role', $value)
                        ->where('id', '!=', $line->id)
                        ->exists()) {
                        $fail('This promotion already has a ' . $value . ' item.');
                    }
                },
            ];
        }

        return $request->validate($rules);
    }

    private function promotionValidationTable(): string
    {
        $table = (new Promotion())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    private function promotionItemValidationTable(): string
    {
        $table = (new PromotionItem())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    private function itemValidationTable(): string
    {
        $table = (new Item())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    private function requiredTenant(Request $request): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        return $tenant;
    }
}
