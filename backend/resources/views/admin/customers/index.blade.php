@extends('layouts.app')

@section('title', 'Customers')
@section('page_title', 'Customers')

@push('styles')
<link href="{{ global_asset('minible/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
@endpush

@section('content')
@php($canManageCustomers = admin_has_permission('customers.manage'))
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="card-title mb-1">Customer Directory</h4>
                        <p class="card-title-desc mb-0">
                            Maintain the customers available to the currently selected tenant.
                        </p>
                    </div>
                    @if ($canManageCustomers)
                        <div class="mt-3 mt-sm-0">
                            <a href="{{ route('admin.customers.create') }}" class="btn btn-primary waves-effect waves-light">
                                <i class="uil uil-plus me-1"></i>Create Customer
                            </a>
                        </div>
                    @endif
                </div>

                @if (session('status'))
                    <div class="alert alert-success alert-border-left alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-all me-2"></i>{{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="alert alert-border-left alert-light mb-4" role="alert">
                    <i class="mdi mdi-database me-2"></i>Showing customers for tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
                </div>

                <div class="table-responsive">
                    <table id="datatable-customers" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Address</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customers as $customer)
                                <tr>
                                    <td>{{ $customer->code }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            @if ($customer->profile_image_url)
                                                <img src="{{ $customer->profile_image_url }}" alt="{{ $customer->name }}" class="rounded-circle" style="width: 44px; height: 44px; object-fit: cover;">
                                            @else
                                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center fw-semibold" style="width: 44px; height: 44px; background: #eef1ff; color: #5b73e8;">
                                                    {{ strtoupper(substr($customer->name ?: 'C', 0, 1)) }}
                                                </div>
                                            @endif
                                            <div>
                                                <div class="fw-semibold">{{ $customer->name }}</div>
                                                <div class="text-muted font-size-12">{{ \Illuminate\Support\Str::limit($customer->notes ?: 'No notes', 60) }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>{{ $customer->email ?: 'No email' }}</div>
                                        <div class="text-muted font-size-12">{{ $customer->phone ?: 'No phone' }}</div>
                                    </td>
                                    <td>{{ $customer->address ?: 'No address' }}</td>
                                    <td>
                                        <span class="badge {{ $customer->status === 'Active' ? 'bg-success' : 'bg-danger' }}">
                                            {{ $customer->status }}
                                        </span>
                                    </td>
                                    <td>{{ optional($customer->updated_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                    <td class="text-nowrap">
                                        @if ($canManageCustomers)
                                            <a href="{{ route('admin.customers.edit', ['customer' => $customer->id]) }}" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                            <form action="{{ route('admin.customers.destroy', ['customer' => $customer->id]) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this customer?')">Delete</button>
                                            </form>
                                        @else
                                            <span class="text-muted">View only</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ global_asset('minible/assets/libs/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-responsive/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/js/responsive.bootstrap4.min.js') }}"></script>
<script>
    $(function () {
        $('#datatable-customers').DataTable({
            responsive: true,
            order: [[1, 'asc']],
            language: {
                emptyTable: 'No customers found for this tenant.'
            }
        });
        $('.dataTables_length select').addClass('form-select form-select-sm');
    });
</script>
@endpush
