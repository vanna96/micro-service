@extends('layouts.app')

@section('title', 'Addresses')
@section('page_title', 'Addresses')

@push('styles')
<link href="{{ global_asset('minible/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
@endpush

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="card-title mb-1">Address Directory</h4>
                        <p class="card-title-desc mb-0">
                            Maintain delivery addresses for users in the currently selected tenant.
                        </p>
                    </div>
                    @if ($canCreateAddresses)
                        <div class="mt-3 mt-sm-0">
                            <a href="{{ route('admin.addresses.create') }}" class="btn btn-primary waves-effect waves-light">
                                <i class="uil uil-plus me-1"></i>Create Address
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
                    <i class="mdi mdi-database me-2"></i>Showing addresses for tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
                </div>

                <div class="table-responsive">
                    <table id="datatable-addresses" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Label</th>
                                <th>Recipient</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>City</th>
                                <th>Coordinates</th>
                                <th>Default</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($addresses as $address)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $address->user?->name ?: 'Unknown user' }}</div>
                                        <div class="text-muted font-size-12">{{ $address->user?->username ?: '-' }}</div>
                                    </td>
                                    <td>{{ $address->label }}</td>
                                    <td>{{ $address->recipient_name }}</td>
                                    <td>{{ $address->code }} {{ $address->phone }}</td>
                                    <td>
                                        <div>{{ $address->address_line }}</div>
                                        <div class="text-muted font-size-12">{{ \Illuminate\Support\Str::limit($address->note ?: 'No note', 40) }}</div>
                                    </td>
                                    <td>{{ $address->city }}</td>
                                    <td>
                                        @if ($address->latitude !== null && $address->longitude !== null)
                                            <span class="font-monospace small">{{ number_format($address->latitude, 6) }}, {{ number_format($address->longitude, 6) }}</span>
                                        @else
                                            <span class="text-muted">No coordinates</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($address->is_default)
                                            <span class="badge bg-success">Default</span>
                                        @else
                                            <span class="badge bg-light text-dark border">No</span>
                                        @endif
                                    </td>
                                    <td>{{ optional($address->updated_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                    <td class="text-nowrap">
                                        @if ($canEditAddresses)
                                            <a href="{{ route('admin.addresses.edit', ['address' => $address->id]) }}" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                        @endif
                                        @if ($canDeleteAddresses)
                                            <form action="{{ route('admin.addresses.destroy', ['address' => $address->id]) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this address?')">Delete</button>
                                            </form>
                                        @endif
                                        @if (! $canEditAddresses && ! $canDeleteAddresses)
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
        $('#datatable-addresses').DataTable({
            responsive: true,
            order: [[8, 'desc']],
            language: {
                emptyTable: 'No addresses found for this tenant.'
            }
        });
        $('.dataTables_length select').addClass('form-select form-select-sm');
    });
</script>
@endpush
