<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\UserRepository;

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
        $users = $this->user ->list()
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
            'data' => $users->items(),  
            'current_page' => $users->currentPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(), 
            'last_page' => $users->lastPage(),
        ], 200);
    }
}
