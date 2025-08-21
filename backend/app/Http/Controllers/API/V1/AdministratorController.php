<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Validator;
use App\Rules\Base64Image;

class AdministratorController extends Controller
{
    protected $user;

    public function __construct(
        UserRepository $user
    ){
        $this->user = $user;
    }

    public function list(Request $request)
    {
        $search = $request->get('search', null);
        $users = $this->user->list()
                            ->when(!in_array($search, ['undefined', 'null', '']), function ($q) use ($search) {
                                $q->where(function ($query) use ($search) {
                                    $query->where('name', 'like', "%{$search}%")
                                        ->orWhere('username', 'like', "%{$search}%")
                                        ->orWhere('first_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%")
                                        ->orWhere('phone', 'like', "%{$search}%")
                                        ->orWhere('gender', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%");
                                });
                            })
                            ->select('*')
                            ->orderBy('id', 'desc')
                            ->paginate($request->per_page ?? 20);
                            
        return response()->json([
            'data' => UserResource::collection($users->items()),  
            'current_page' => $users->currentPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(), 
            'last_page' => $users->lastPage(),
        ], 200);
    }

    public function edit(User $admin){
        $admin->load('galleries');
        return response()->json([
            'success' => true,
            'message' => '',
            'data'    => new UserResource($admin)
        ], 200);
    }

    public function update(Request $request, User $admin)
    {
        $input = $request->all();
        $country = country_code()[$request->input('country_code') ?? '855'] ?? 'KH';
        if (!empty($input['phone'])) {
            $input['phone'] = ltrim($input['phone'], '0');
        }

        // Validation
        $validator = Validator::make($input, [
            'name'          => 'required|string|max:255',
            'username'      => 'required|string|max:255|unique:users,username,' . $admin->id,
            'email'         => 'nullable|string|email|unique:users,email,' . $admin->id,
            'password'      => 'nullable|string|min:6|confirmed',
            'first_name'    => 'nullable|string|max:255',
            'last_name'     => 'nullable|string|max:255',
            'country_code'  => 'nullable|string|max:5',
            'phone'         => "required|phone:{$country}|unique:users,phone," . $admin->id,
            'gender'        => 'nullable|in:Male,Female',
            'dob'           => 'nullable|date',
            'status'        => 'nullable|in:Active,Inactive',
            'profile'       => ['nullable', new Base64Image]
        ]);

        if ($validator->fails()) {
            $message = formatValidationErrors($validator->errors()->toArray());
            if (in_array(request('lng'), explode(',', env('LNG_ALLOWED', 'en')))) {
                $message = translate($message, request('lng'));
            }
            return response()->json($message, 422);
        }

        $validated = $validator->validated();
        // Delete previous profile image
        if ($admin->profile_id) {
            $oldGallery = $admin->galleries()->find($admin->profile_id);
            if ($oldGallery) {
                if (\Storage::disk('user')->exists($oldGallery->name)) {
                    \Storage::disk('user')->delete($oldGallery->name);
                }
                $oldGallery->delete();
            }
        }
        
        // Handle profile image upload if provided (base64)
        if (!empty($validated['profile'])) {
            $profileBase64 = $validated['profile'];
            preg_match("/^data:image\/(\w+);base64,/", $profileBase64, $matches);
            $extension = isset($matches[1]) ? strtolower($matches[1]) : 'png';
            $base64Data = preg_replace("/^data:image\/\w+;base64,/", '', $profileBase64);
            $imageData = base64_decode($base64Data, true);

            if ($imageData) {
                $fileName = "user_" . uniqid() . "." . $extension;
                \Storage::disk('user')->put($fileName, $imageData, 'public');

                // Create new gallery record
                $gallery = $admin->galleries()->create([
                    'type' => 'thumbnail',
                    'status' => 'Active',
                    'name' => $fileName
                ]);

                $admin->profile_id = $gallery->id;
                $admin->save();
            }
        }


        // Hash password if provided
        if (!empty($validated['password'])) {
            $validated['password'] = \Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }
 
        $admin->update($validated); 

        return response()->json([
            'success' => true,
            'message' => translate('Administrator updated successfully.', request('lng')),
            'data'    => $admin
        ], 200);
    }
}
