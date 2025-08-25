<?php

namespace App\Http\Controllers\API\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Tenant;

class TenantController extends Controller
{

    public function list(Request $request)
    {
        $search = $request->get('search', null);
        $query = Tenant::query();

        if (!in_array($search, [null, '', 'undefined', 'null'], true)) {
            $query->where(function ($q) use ($search) {
                $q->where('data->db_name', 'like', "%{$search}%")
                ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $tenants = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $tenants->items(),
            'current_page' => $tenants->currentPage(),
            'per_page' => $tenants->perPage(),
            'total' => $tenants->total(),
            'last_page' => $tenants->lastPage(),
        ]);
    }


    public function edit(Tenant $tenant)
    {
        $data = $tenant->toArray();

        $data['created_at'] = $tenant->created_at->format('d M, Y');
        $data['updated_at'] = $tenant->updated_at->format('d M, Y');

        return response()->json([
            'success' => true,
            'message' => '',
            'data'    => $data,
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
            'status'            => 'nullable|in:Active,Inactive',
        ]);

        if(!$success) return response()->json($message, $status);

        $tenant = Tenant::create([
            'id'   => $data['id'],
            'status'   => $data['status'],
            'db_name'     => $data['db_name'],
            'db_host'     => $data['db_host'],
            'db_username' => $data['db_username'],
            'db_password' => $data['db_password'],
            'db_connection' => $data['db_connection'],
            'db_port' => $data['db_port']
        ]);
        $tenant->domains()->create(['domain' => $tenant->tenancy_db_name.'.'.env('TENANT_HOST', 'localhost')]);

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
            'status'            => 'nullable|in:Active,Inactive',
        ]);
        if(!$success) return response()->json($message, $status);

        $tenant->update([
            'status'        => $data['status'],
            'db_name'       => $data['db_name'],
            'db_host'       => $data['db_host'],
            'db_username'   => $data['db_username'],
            'db_password'   => $data['db_password'],
            'db_connection' => $data['db_connection'],
            'db_port'       => $data['db_port']
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
