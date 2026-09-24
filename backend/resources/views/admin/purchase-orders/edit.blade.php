@extends('layouts.app')

@section('title', 'Edit Purchase Order')
@section('page_title', 'Edit Purchase Order')

@section('content')
<form method="POST" action="{{ route('admin.purchase-orders.update', ['purchase_order' => $purchaseOrder->id]) }}" class="admin-form-page">
    @csrf
    @method('PUT')

    <div class="alert alert-border-left alert-light mb-4" role="alert">
        <i class="mdi mdi-database me-2"></i>Editing purchase order <strong>{{ $purchaseOrder->po_number }}</strong> in tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-border-left alert-dismissible fade show" role="alert">
            <i class="mdi mdi-block-helper me-2"></i><strong>Please correct the errors below:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @include('admin.purchase-orders._form')
</form>
@endsection
