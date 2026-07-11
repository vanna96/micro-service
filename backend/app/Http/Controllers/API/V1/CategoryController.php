<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use App\Rules\Base64Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    protected CategoryRepository $category;

    public function __construct(CategoryRepository $category)
    {
        $this->category = $category;
    }

    public function list(Request $request)
    {
        $search = $request->get('search', null);
        $categories = $this->category
                            ->list()
                            ->when(! in_array($search, ['undefined', 'null', ''], true), function ($q) use ($search) {
                                $q->where(function ($query) use ($search) {
                                    $query->where('name', 'like', "%{$search}%")
                                        ->orWhere('foreign_name', 'like', "%{$search}%")
                                        ->orWhereHas('parent', function ($parentQuery) use ($search) {
                                            $parentQuery->where('name', 'like', "%{$search}%")
                                                        ->orWhere('foreign_name', 'like', "%{$search}%");
                                        });
                                });
                            })
                            ->select('*')
                            ->orderBy('id', 'desc')
                            ->paginate($request->per_page ?? 20);
           
        return response()->json([
            'data' => CategoryResource::collection($categories->items()),  
            'current_page' => $categories->currentPage(),
            'per_page' => $categories->perPage(),
            'total' => $categories->total(), 
            'last_page' => $categories->lastPage(),
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $this->validateCategory($request);
        $validated['image'] = $request->file('image') ?: $request->input('attachment');
        $category = $this->category->createForAdmin($validated);

        return response()->json([
            'success' => true,
            'message' => translate('Category created successfully.', request('lng')),
            'data' => new CategoryResource($category->load(['parent', 'galleries', 'image'])),
        ], 200);
    }

    public function edit(string $category)
    {
        return response()->json([
            'data' => new CategoryResource($this->category->loadForAdminEdit((int) $category)),
        ], 200);
    }

    public function update(Request $request, string $category)
    {
        $categoryModel = $this->category->loadForAdminEdit((int) $category);
        $validated = $this->validateCategory($request, $categoryModel);

        if ($request->hasFile('image') || $request->filled('attachment')) {
            $validated['image'] = $request->file('image') ?: $request->input('attachment');
        }

        $categoryModel = $this->category->updateForAdmin($categoryModel, $validated);

        return response()->json([
            'success' => true,
            'message' => translate('Category updated successfully.', request('lng')),
            'data' => new CategoryResource($categoryModel->load(['parent', 'galleries', 'image'])),
        ], 200);
    }

    public function delete(string $category)
    {
        $categoryModel = $this->category->loadForAdminEdit((int) $category);
        $this->category->deleteForAdmin($categoryModel);

        return response()->json([
            'success' => true,
            'message' => translate('Category deleted successfully.', request('lng')),
        ], 200);
    }

    protected function validateCategory(Request $request, ?Category $category = null): array
    {
        $categoryId = $category?->id;
        $categoryTable = $this->categoryValidationTable();
        $requiredRule = $category ? 'sometimes' : 'required';

        $validator = Validator::make($request->all(), [
            'foreign_name' => ['nullable', 'string', 'max:255'],
            'name' => [$requiredRule, 'string', 'max:255', Rule::unique($categoryTable, 'name')->ignore($categoryId)],
            'status' => [$category ? 'sometimes' : 'nullable', Rule::in(['Active', 'Inactive'])],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists($categoryTable, 'id'),
                Rule::notIn(array_filter([$categoryId])),
            ],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'attachment' => ['nullable', new Base64Image()],
        ]);

        if ($validator->fails()) {
            $message = formatValidationErrors($validator->errors()->toArray());
            if (in_array(request('lng'), explode(',', env('LNG_ALLOWED', 'en')), true)) {
                $message = translate($message, request('lng'));
            }

            abort(response()->json($message, 422));
        }

        $validated = $validator->validated();

        if (array_key_exists('parent_id', $validated) && blank($validated['parent_id'])) {
            $validated['parent_id'] = null;
        }

        return $validated;
    }

    protected function categoryValidationTable(): string
    {
        $table = (new Category())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }
}
