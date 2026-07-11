<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Repositories\BranchRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    protected BranchRepository $branches;

    public function __construct(BranchRepository $branches)
    {
        $this->branches = $branches;
    }

    public function list(Request $request)
    {
        $search = $request->get('search', null);

        $branches = $this->branches
            ->list()
            ->when(! in_array($search, ['undefined', 'null', ''], true), function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('foreign_name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => BranchResource::collection($branches->items()),
            'current_page' => $branches->currentPage(),
            'per_page' => $branches->perPage(),
            'total' => $branches->total(),
            'last_page' => $branches->lastPage(),
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $this->validateBranch($request);
        $branch = $this->branches->createForAdmin($validated);

        return response()->json([
            'success' => true,
            'message' => translate('Branch created successfully.', request('lng')),
            'data' => new BranchResource($branch),
        ], 200);
    }

    public function edit(string $branch)
    {
        return response()->json([
            'data' => new BranchResource($this->branches->loadForAdminEdit((int) $branch)),
        ], 200);
    }

    public function update(Request $request, string $branch)
    {
        $branchModel = $this->branches->loadForAdminEdit((int) $branch);
        $validated = $this->validateBranch($request, $branchModel);
        $branchModel = $this->branches->updateForAdmin($branchModel, $validated);

        return response()->json([
            'success' => true,
            'message' => translate('Branch updated successfully.', request('lng')),
            'data' => new BranchResource($branchModel),
        ], 200);
    }

    public function delete(string $branch)
    {
        $branchModel = $this->branches->loadForAdminEdit((int) $branch);
        $this->branches->deleteForAdmin($branchModel);

        return response()->json([
            'success' => true,
            'message' => translate('Branch deleted successfully.', request('lng')),
        ], 200);
    }

    protected function validateBranch(Request $request, ?Branch $branch = null): array
    {
        $branchId = $branch?->id;
        $branchTable = $this->branchValidationTable();
        $requiredRule = $branch ? 'sometimes' : 'required';

        $validator = Validator::make($request->all(), [
            'code' => [$requiredRule, 'string', 'max:64', Rule::unique($branchTable, 'code')->ignore($branchId)],
            'name' => [$requiredRule, 'string', 'max:255', Rule::unique($branchTable, 'name')->ignore($branchId)],
            'foreign_name' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => [$branch ? 'sometimes' : 'nullable', Rule::in(['Active', 'Inactive'])],
        ]);

        if ($validator->fails()) {
            $message = formatValidationErrors($validator->errors()->toArray());
            if (in_array(request('lng'), explode(',', env('LNG_ALLOWED', 'en')), true)) {
                $message = translate($message, request('lng'));
            }

            abort(response()->json($message, 422));
        }

        return $validator->validated();
    }

    protected function branchValidationTable(): string
    {
        $table = (new Branch())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }
}
