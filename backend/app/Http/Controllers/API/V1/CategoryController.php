<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\CategoryRepository;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    protected $category;

    public function __construct(
        CategoryRepository $category
    ){
        $this->category = $category;
    }

    public function list(Request $request)
    {
        $search = $request->get('search', null);
        $categories = $this->category
                            ->list()
                            ->when(!in_array($search, ['undefined', 'null', '']), function ($q) use ($search) {
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
        // Validation
        $input = $request->all();
        $validator = Validator::make($input, [
            'foreign_name'  => 'nullable|string|max:255',
            'name'          => 'required|string|max:255|unique:categories',
            'status'        => 'nullable|in:Active,Inactive',
            'parent_id'     => 'nullable|exists:categories,id',
        ]);

        if ($validator->fails()) {
            $message = formatValidationErrors($validator->errors()->toArray());
            if (in_array(request('lng'), explode(',', env('LNG_ALLOWED', 'en')))) {
                $message = translate($message, request('lng'));
            }
            return response()->json($message, 422);
        }

        $validated = $validator->validated();
        $category = Category::create($validated);
        $profile = $request->input('attachment');

        if ($profile) { 
            $matches = [];
            $extension =  'png';
            // Try to match "data:image/png;base64,"
            preg_match("/^data:image\/(\w+);base64,/", $profile, $matches);

            if (isset($matches[1])) {
                $extension = strtolower($matches[1]); // e.g. png, jpg, jpeg, gif
            }
            $base64Data = preg_replace("/^data:image\/\w+;base64,/", '', $profile);
            $imageData = base64_decode($base64Data);
            $fileName = "user_".uniqid().".".$extension;
            \Storage::disk('category')->put($fileName, $imageData);

            $gallery = $category->galleries()->create([ 
                'type' => "thumbnail",
                'status' => "Active",
                'name' => $fileName
            ]);

            $category->image_id = $gallery->id;
            $category->save();
        }

        return response()->json([
            'success' => true,
            'message' => translate('Category created successfully.', request('lng')),
            'data' => $category
        ], 200);
    }
}
