<?php

namespace App\Http\Controllers\API\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller; 
use App\Models\Tenant;

class TenantController extends Controller
{

    public function list(Request $request)
    {
        $tanents = Tenant::paginate($request->per_page ?? 20);
        return response()->json([
            'data' => Tenant::all(),  
            'current_page' => $tanents->currentPage(),
            'per_page' => $tanents->perPage(),
            'total' => $tanents->total(), 
            'last_page' => $tanents->lastPage(),
        ], 200);
    }

    public function store(Request $request)
    {
        $input = $request->all();
        [
            $success,
            $message,
            $data,
            $status
        ] = $this->customValidation($input, [
            'id'                => 'required|string|unique:tenants,id',
            'db_connection'     => 'required|string',
            'db_port'           => 'required|string',
            'db_name'           => 'required|string',
            'db_host'           => 'required|string',
            'db_username'       => 'required|string',
            'db_password'       => 'required|string',
        ]);

        if(!$success) return response()->json($message, $status);
    
        $tenant = Tenant::create([
            'id'   => $data['id'],
            'data' => [
                'db_name'     => $data['db_name'],
                'db_host'     => $data['db_host'],
                'db_username' => $data['db_username'],
                'db_password' => $data['db_password'],
                'db_connection' => $data['db_connection'],
                'db_port' => $data['db_port']
            ],
        ]);
        $tenant->domains()->create(['domain' => $tenant->tenancy_db_name.".localhost"]);
        
        return response()->json([
            'success' => true,
            'message' => translate('Tenant created successfully.', request('lng')),
            'data'    => $tenant
        ], 201);
    }

    public function update(Request $request, Tenant $tenant)
    { 
        $input = $request->all();
        [
            $success,
            $message,
            $data,
            $status
        ] = $this->customValidation($input, [
            'db_connection'     => 'required|string',
            'db_port'           => 'required|string',
            'db_name'           => 'required|string',
            'db_host'           => 'required|string',
            'db_username'       => 'required|string',
            'db_password'       => 'required|string',
        ]);
        if(!$success) return response()->json($message, $status);

        $tenant->update([
            'data' => array_merge($tenant->data, $request->only([
                'db_connection',
                'db_port',
                'db_name',
                'db_host',
                'db_username',
                'db_password',
            ])),
        ]);

        return response()->json([
            'success' => true,
            'message' => translate('Tenant updated successfully.', request('lng')),
            'data'    => $tenant
        ], 200);
    }

    public function delete(Tenant $tenant)
    {
        $tenant->delete();
        return response()->json([
            'success' => true,
            'message' => translate('Tenant deleted successfully.', request('lng')),
            'data'    => null
        ], 200);
    }
}
