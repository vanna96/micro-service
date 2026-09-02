@once
    @push('styles')
        <link href="{{ global_asset('minible/assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ global_asset('minible/assets/libs/dropzone/min/dropzone.min.css') }}" rel="stylesheet" type="text/css" />
        <style>
            .item-upload-preview {
                width: 100%;
                max-width: 240px;
                aspect-ratio: 4 / 5;
                border-radius: 24px;
                object-fit: cover;
                border: 1px solid #e9edf4;
                background: #fff;
            }

            .item-upload-placeholder {
                width: 100%;
                max-width: 240px;
                aspect-ratio: 4 / 5;
                border-radius: 24px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #eef4ff 0%, #f8fbff 100%);
                color: #5b73e8;
                font-size: 3rem;
                font-weight: 700;
                border: 1px dashed #bfd1ff;
            }

            .select2-container .select2-selection--single {
                height: calc(1.5em + 0.94rem + 2px);
                border: 1px solid #ced4da;
            }

            .select2-container--default .select2-selection--single .select2-selection__rendered {
                line-height: calc(1.5em + 0.94rem);
                padding-left: 0.75rem;
                padding-right: 2rem;
            }

            .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: calc(1.5em + 0.94rem + 2px);
                right: 0.5rem;
            }

            .select2-container {
                width: 100% !important;
            }

            .item-gallery-dropzone {
                border: 2px dashed #bfd1ff;
                border-radius: 20px;
                background: linear-gradient(135deg, #f8fbff 0%, #eef4ff 100%);
                min-height: 220px;
                padding: 1rem;
            }

            .item-gallery-dropzone .dz-message {
                margin: 2.5rem 0;
                color: #5b73e8;
                font-weight: 600;
            }

            .item-gallery-dropzone .dz-preview .dz-image {
                border-radius: 16px;
            }

            .item-gallery-dropzone .dz-preview .dz-remove {
                margin-top: 0.5rem;
                display: inline-block;
                color: #f46a6a;
                font-weight: 600;
            }

            .item-existing-gallery-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 0.75rem;
            }

            .item-existing-gallery-card {
                position: relative;
                border: 1px solid #e9edf4;
                border-radius: 18px;
                overflow: hidden;
                background: #fff;
                box-shadow: 0 4px 16px rgba(39, 48, 78, 0.06);
            }

            .item-existing-gallery-card img {
                width: 100%;
                aspect-ratio: 1 / 1;
                object-fit: cover;
                display: block;
            }

            .item-existing-gallery-remove {
                position: absolute;
                top: 0.5rem;
                right: 0.5rem;
                width: 30px;
                height: 30px;
                border-radius: 50%;
                border: none;
                background: rgba(244, 106, 106, 0.92);
                color: #fff;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 6px 14px rgba(244, 106, 106, 0.24);
            }

            .item-existing-gallery-caption {
                padding: 0.5rem 0.75rem 0.75rem;
                font-size: 0.75rem;
                color: #6c757d;
            }

            .item-config-mode {
                display: inline-flex;
                gap: 4px;
                padding: 4px;
                border: 1px solid #e2e7f0;
                border-radius: 8px;
                background: #f5f7fb;
            }

            .item-config-mode button {
                min-width: 132px;
                border: 0;
                border-radius: 6px;
                padding: 0.62rem 1rem;
                background: transparent;
                color: #68738a;
                font-weight: 600;
            }

            .item-config-mode button.is-active {
                background: #fff;
                color: #1f2a44;
                box-shadow: 0 2px 8px rgba(31, 42, 68, 0.09);
            }

            .item-config-summary {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                color: #68738a;
                font-size: 0.875rem;
            }

            .item-config-summary strong {
                color: #1f2a44;
            }

            .item-master-picker {
                display: grid;
                grid-template-columns: minmax(180px, 0.8fr) minmax(220px, 1.2fr) auto auto;
                gap: 0.65rem;
                align-items: center;
                padding: 0.85rem;
                border: 1px solid #e2e7f0;
                border-radius: 8px;
                background: #f8f9fc;
            }

            .item-master-picker > .select2-container {
                min-width: 0;
            }

            .item-master-picker .select2-selection--multiple {
                min-height: calc(1.5em + 0.94rem + 2px);
                border-color: #ced4da;
            }

            .item-master-value-list {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .item-master-value {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                padding: 0.45rem 0.65rem;
                border: 1px solid #e2e7f0;
                border-radius: 6px;
                background: #fff;
                color: #35415b;
                font-size: 0.82rem;
                font-weight: 600;
            }

            .item-master-swatch {
                width: 14px;
                height: 14px;
                border: 1px solid rgba(31, 42, 68, 0.18);
                border-radius: 3px;
            }

            .item-group-master-fields {
                display: grid;
                grid-template-columns: minmax(180px, 0.8fr) minmax(240px, 1.2fr);
                gap: 0.75rem;
                align-items: end;
            }

            .item-group-master-fields .select2-container {
                min-width: 0;
            }

            .item-group-settings {
                display: grid;
                grid-template-columns: minmax(140px, 1fr) minmax(140px, 1fr) minmax(110px, 0.75fr) minmax(110px, 0.75fr) minmax(110px, 0.75fr);
                gap: 0.75rem;
                align-items: end;
                padding: 0.85rem;
                border: 1px solid #e7ebf2;
                border-radius: 8px;
                background: #fafbfe;
            }

            .item-group-required {
                min-height: 38px;
                display: flex;
                align-items: center;
            }

            .item-option-group {
                border: 1px solid #e2e7f0;
                border-radius: 8px;
                background: #fff;
                overflow: hidden;
            }

            .item-option-group-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                padding: 0.85rem 1rem;
                border-bottom: 1px solid #e9edf4;
                background: #f8f9fc;
            }

            .item-option-group-index {
                width: 32px;
                height: 32px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 6px;
                background: #e8edff;
                color: #4f64d8;
                font-weight: 700;
            }

            .item-option-group-body {
                padding: 1rem;
            }

            .item-option-value-head,
            .item-option-value-row {
                display: grid;
                grid-template-columns: minmax(150px, 1.5fr) minmax(110px, 0.8fr) minmax(125px, 0.8fr) 90px 46px;
                gap: 0.65rem;
                align-items: center;
            }

            .item-option-value-head {
                margin-bottom: 0.45rem;
                color: #7b8498;
                font-size: 0.72rem;
                font-weight: 700;
                text-transform: uppercase;
            }

            .item-option-value-row + .item-option-value-row {
                margin-top: 0.55rem;
            }

            .item-option-empty {
                padding: 2.25rem 1rem;
                border: 1px dashed #cfd7e6;
                border-radius: 8px;
                text-align: center;
                color: #7b8498;
                background: #fafbfe;
            }

            .item-variant-table th {
                white-space: nowrap;
                color: #7b8498;
                font-size: 0.72rem;
                text-transform: uppercase;
            }

            .item-variant-table td {
                vertical-align: middle;
                min-width: 120px;
            }

            .item-variant-table td:first-child {
                min-width: 210px;
            }

            .item-variant-scroll {
                scrollbar-width: none;
                -ms-overflow-style: none;
            }

            .item-variant-scroll::-webkit-scrollbar {
                display: none;
            }

            .item-config-icon-button {
                width: 36px;
                height: 36px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0;
            }

            @media (max-width: 767.98px) {
                .item-group-master-fields {
                    grid-template-columns: 1fr;
                }

                .item-group-settings {
                    grid-template-columns: 1fr 1fr;
                }

                .item-master-picker {
                    grid-template-columns: 1fr 1fr;
                }

                .item-master-picker .item-master-select {
                    grid-column: 1 / -1;
                }

                .item-master-picker > .select2-container {
                    grid-column: 1 / -1;
                }

                .item-config-mode {
                    width: 100%;
                }

                .item-config-mode button {
                    flex: 1;
                    min-width: 0;
                }

                .item-option-value-head {
                    display: none;
                }

                .item-option-value-row {
                    grid-template-columns: 1fr 1fr;
                    padding: 0.75rem;
                    border: 1px solid #edf0f5;
                    border-radius: 8px;
                }

                .item-option-value-row .item-option-value-name {
                    grid-column: 1 / -1;
                }

                .item-option-value-row .item-option-value-remove {
                    justify-self: end;
                }

                .item-variant-scroll {
                    overflow: visible;
                }

                .item-variant-table,
                .item-variant-table tbody,
                .item-variant-table tr,
                .item-variant-table td {
                    display: block;
                    width: 100%;
                }

                .item-variant-table {
                    border: 0;
                }

                .item-variant-table thead {
                    display: none;
                }

                .item-variant-table tbody {
                    display: grid;
                    gap: 0.75rem;
                }

                .item-variant-table tr {
                    padding: 0.75rem;
                    border: 1px solid #e5e9f2;
                    border-radius: 8px;
                    background: #fff;
                }

                .item-variant-table td,
                .item-variant-table td:first-child {
                    display: grid;
                    grid-template-columns: 96px minmax(0, 1fr);
                    gap: 0.65rem;
                    align-items: center;
                    min-width: 0;
                    padding: 0.35rem 0;
                    border: 0;
                }

                .item-variant-table td::before {
                    content: attr(data-label);
                    color: #7b8498;
                    font-size: 0.7rem;
                    font-weight: 700;
                    text-transform: uppercase;
                }

                .item-variant-table td:last-child {
                    grid-template-columns: 1fr;
                    padding-top: 0.55rem;
                }

                .item-variant-table td:last-child::before {
                    display: none;
                }

                .item-variant-table td:last-child .item-config-icon-button {
                    justify-self: end;
                }
            }

            @media (max-width: 479.98px) {
                .item-group-settings {
                    grid-template-columns: 1fr;
                }

                .item-master-picker {
                    grid-template-columns: 1fr;
                }

                .item-master-picker .item-master-select {
                    grid-column: auto;
                }
            }
        </style>
    @endpush

    @push('scripts')
        <script src="{{ global_asset('minible/assets/libs/select2/js/select2.min.js') }}"></script>
        <script src="{{ global_asset('minible/assets/libs/dropzone/min/dropzone.min.js') }}"></script>
        <script>
            $(function () {
                $('.item-category-combobox').select2({
                    width: '100%',
                    placeholder: 'Select category',
                    allowClear: true
                });

                $('.item-branch-combobox').select2({
                    width: '100%',
                    placeholder: 'Select branch',
                    allowClear: true
                });

                $('.item-price-list-combobox').select2({
                    width: '100%',
                    placeholder: 'Select price list',
                    allowClear: true
                });

                $('.item-currency-combobox').select2({
                    width: '100%',
                    placeholder: 'Select currency'
                });

                const currencySelect = document.getElementById('item-currency-id');
                const priceInput = document.getElementById('item-price');
                const priceHelp = document.getElementById('item-price-help');

                const applyCurrencyPricingState = () => {
                    if (!currencySelect || !priceInput || !priceHelp) {
                        return;
                    }

                    const selectedOption = currencySelect.options[currencySelect.selectedIndex];
                    const currencyCode = selectedOption?.dataset.currencyCode || '';
                    const decimalPlaces = selectedOption?.dataset.decimalPlaces || '';
                    const inputStep = selectedOption?.dataset.inputStep || '0.00000001';
                    const formatExample = selectedOption?.dataset.formatExample || '2.22';

                    priceInput.setAttribute('step', inputStep);
                    priceInput.setAttribute('placeholder', formatExample);

                    if (!currencyCode) {
                        priceHelp.textContent = 'Choose a currency to control allowed decimal places for this item price.';

                        return;
                    }

                    priceHelp.textContent = `${currencyCode} allows up to ${decimalPlaces} decimal place(s). Example: ${formatExample}.`;
                };

                if (currencySelect) {
                    applyCurrencyPricingState();
                    currencySelect.addEventListener('change', applyCurrencyPricingState);
                }

                const imageInput = document.getElementById('item-image-input');
                const imagePreview = document.getElementById('item-image-preview');
                const imagePlaceholder = document.getElementById('item-image-placeholder');

                if (imageInput && imagePreview && imagePlaceholder) {
                    imageInput.addEventListener('change', function (event) {
                        const file = event.target.files && event.target.files[0];

                        if (!file) {
                            return;
                        }

                        const reader = new FileReader();

                        reader.onload = function (loadEvent) {
                            imagePreview.src = loadEvent.target.result;
                            imagePreview.classList.remove('d-none');
                            imagePlaceholder.classList.add('d-none');
                        };

                        reader.readAsDataURL(file);
                    });
                }

                Dropzone.autoDiscover = false;

                const galleryInput = document.getElementById('item-gallery-input');
                const galleryDropzoneElement = document.getElementById('item-gallery-dropzone');
                const itemForm = document.getElementById('item-form');

                const syncGalleryInput = (dropzoneInstance) => {
                    if (!galleryInput || !dropzoneInstance) {
                        return;
                    }

                    const transfer = new DataTransfer();

                    dropzoneInstance.files.forEach((file) => {
                        if (file.accepted !== false && file instanceof File) {
                            transfer.items.add(file);
                        }
                    });

                    galleryInput.files = transfer.files;
                };

                if (galleryDropzoneElement && galleryInput && itemForm) {
                    const galleryDropzone = new Dropzone(galleryDropzoneElement, {
                        url: window.location.href,
                        autoProcessQueue: false,
                        uploadMultiple: true,
                        parallelUploads: 20,
                        maxFilesize: 2,
                        acceptedFiles: 'image/jpeg,image/png,image/webp',
                        addRemoveLinks: true,
                        clickable: true,
                        previewsContainer: galleryDropzoneElement,
                        dictDefaultMessage: 'Drop gallery images here or click to browse',
                    });

                    const browseGalleryButton = document.getElementById('item-gallery-browse');

                    galleryDropzone.on('addedfile', function () {
                        syncGalleryInput(galleryDropzone);
                    });

                    galleryDropzone.on('removedfile', function () {
                        syncGalleryInput(galleryDropzone);
                    });

                    itemForm.addEventListener('submit', function () {
                        syncGalleryInput(galleryDropzone);
                    });

                    if (browseGalleryButton && galleryDropzone.hiddenFileInput) {
                        browseGalleryButton.addEventListener('click', function () {
                            galleryDropzone.hiddenFileInput.click();
                        });
                    }
                }

                document.querySelectorAll('[data-gallery-delete-url]').forEach((button) => {
                    button.addEventListener('click', async function () {
                        const card = button.closest('[data-gallery-card]');
                        const deleteUrl = button.getAttribute('data-gallery-delete-url');

                        if (!deleteUrl || !card) {
                            return;
                        }

                        if (!window.confirm('Remove this gallery image?')) {
                            return;
                        }

                        button.disabled = true;

                        try {
                            const response = await fetch(deleteUrl, {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                },
                            });

                            if (!response.ok) {
                                throw new Error('Unable to delete gallery image.');
                            }

                            card.remove();
                        } catch (error) {
                            button.disabled = false;
                            window.alert(error.message || 'Unable to delete gallery image.');
                        }
                    });
                });
            });
        </script>
    @endpush
@endonce

<div class="row g-2">
    <div class="col-xl-8">
        <div class="card card-flush mb-10">
            <div class="card-header">
                <div class="card-title">
                    <h2>Item Details</h2>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2" id="item-form-fields">
                    <div class="col-md-4">
                        <label class="form-label required">SKU</label>
                        <input type="text" name="sku" value="{{ old('sku', $item->sku) }}"
                            class="form-control @error('sku') is-invalid @enderror" />
                        @error('sku')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Name</label>
                        <input type="text" name="name" value="{{ old('name', $item->name) }}"
                            class="form-control @error('name') is-invalid @enderror" />
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Foreign Name</label>
                        <input type="text" name="foreign_name" value="{{ old('foreign_name', $item->foreign_name) }}"
                            class="form-control @error('foreign_name') is-invalid @enderror" />
                        @error('foreign_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <select name="category_id"
                            class="form-select item-category-combobox @error('category_id') is-invalid @enderror">
                            <option value="">Unassigned</option>
                            @foreach ($categoryOptions as $categoryOption)
                                <option value="{{ $categoryOption->id }}" @selected((string) old('category_id', $item->category_id) === (string) $categoryOption->id)>
                                    {{ $categoryOption->name }}{{ $categoryOption->foreign_name ? ' / ' . $categoryOption->foreign_name : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Branch</label>
                        <select name="branch_id"
                            class="form-select item-branch-combobox @error('branch_id') is-invalid @enderror">
                            <option value="">Unassigned</option>
                            @foreach ($branchOptions as $branchOption)
                                <option value="{{ $branchOption->id }}" @selected((string) old('branch_id', $item->branch_id) === (string) $branchOption->id)>
                                    {{ $branchOption->name }}{{ $branchOption->location ? ' / ' . $branchOption->location : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Price List</label>
                        <select name="price_list_id"
                            class="form-select item-price-list-combobox @error('price_list_id') is-invalid @enderror">
                            <option value="">Unassigned</option>
                            @foreach ($priceListOptions as $priceListOption)
                                <option value="{{ $priceListOption->id }}" @selected((string) old('price_list_id', $item->price_list_id) === (string) $priceListOption->id)>
                                    {{ $priceListOption->name }}{{ $priceListOption->is_default ? ' / Default' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('price_list_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Currency</label>
                        <select id="item-currency-id" name="currency_id"
                            class="form-select item-currency-combobox @error('currency_id') is-invalid @enderror">
                            <option value="">Select currency</option>
                            @foreach ($currencyOptions as $currencyOption)
                                <option
                                    value="{{ $currencyOption->id }}"
                                    data-currency-code="{{ $currencyOption->code }}"
                                    data-decimal-places="{{ $currencyOption->decimal_places }}"
                                    data-input-step="{{ $currencyOption->input_step }}"
                                    data-format-example="{{ $currencyOption->format_example }}"
                                    @selected((string) old('currency_id', $item->currency_id) === (string) $currencyOption->id)
                                >
                                    {{ $currencyOption->code }} - {{ $currencyOption->name }}
                                </option>
                            @endforeach
                        </select>
                        <div id="item-price-help" class="form-text">
                            Choose a currency to control allowed decimal places for this item price.
                        </div>
                        @error('currency_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="4"
                            class="form-control @error('description') is-invalid @enderror">{{ old('description', $item->description) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Price</label>
                        <input id="item-price" type="number" min="0" step="0.00000001" name="price" value="{{ old('price', $item->price) }}"
                            class="form-control @error('price') is-invalid @enderror" />
                        @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Discount %</label>
                        <input type="number" min="0" max="100" step="1" name="discount_percent"
                            value="{{ old('discount_percent', $item->discount_percent ?? 0) }}"
                            class="form-control @error('discount_percent') is-invalid @enderror" />
                        @error('discount_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Stock</label>
                        <input type="number" min="0" step="1" name="stock" value="{{ old('stock', $item->stock ?? 0) }}"
                            class="form-control @error('stock') is-invalid @enderror" />
                        @error('stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Status</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror">
                            @foreach (['Active', 'Inactive'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $item->status ?: 'Active') === $status)>
                                    {{ $status }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <div class="border rounded-3 p-3">
                            <label class="form-label d-block mb-3">Display Flags</label>
                            <div class="d-flex flex-column gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_premium" value="1"
                                        id="item-is-premium" @checked(old('is_premium', $item->is_premium))>
                                    <label class="form-check-label" for="item-is-premium">Premium</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_featured" value="1"
                                        id="item-is-featured" @checked(old('is_featured', $item->is_featured))>
                                    <label class="form-check-label" for="item-is-featured">Featured</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_new_arrival" value="1"
                                        id="item-is-new-arrival" @checked(old('is_new_arrival', $item->is_new_arrival))>
                                    <label class="form-check-label" for="item-is-new-arrival">New arrival</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_try_on_enabled" value="1"
                                        id="item-is-try-on-enabled" @checked(old('is_try_on_enabled', $item->is_try_on_enabled ?? true))>
                                    <label class="form-check-label" for="item-is-try-on-enabled">Virtual try-on
                                        enabled</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card card-flush">
            <div class="card-header">
                <div class="card-title">
                    <h2>Thumbnail</h2>
                </div>
            </div>
            <div class="card-body text-center">
                <div class="mb-3">
                    @if ($item->image_url)
                        <img id="item-image-preview" src="{{ $item->image_url }}" alt="Item image"
                            class="item-upload-preview">
                        <div id="item-image-placeholder" class="item-upload-placeholder d-none">
                            {{ strtoupper(substr($item->name ?: 'I', 0, 1)) }}
                        </div>
                    @else
                        <img id="item-image-preview" src="" alt="Item image" class="item-upload-preview d-none">
                        <div id="item-image-placeholder" class="item-upload-placeholder">
                            {{ strtoupper(substr($item->name ?: 'I', 0, 1)) }}
                        </div>
                    @endif
                </div>

                <input id="item-image-input" type="file" name="image"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    class="form-control @error('image') is-invalid @enderror" />
                <div class="form-text">Upload JPG, PNG, or WEBP up to 2MB.</div>
                @error('image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="card card-flush mt-4">
            <div class="card-header">
                <div class="card-title">
                    <h2>Gallery Images</h2>
                </div>
            </div>
            <div class="card-body">
                @if ($item->relationLoaded('galleries') || $item->exists)
                @php
                    $galleryImages = $item->galleries->where('type', 'galleries');
                @endphp
                @if ($galleryImages->isNotEmpty())
                    <div class="item-existing-gallery-grid mb-3">
                        @foreach ($galleryImages as $gallery)
                            <div class="item-existing-gallery-card" data-gallery-card>
                                <button type="button" class="item-existing-gallery-remove"
                                    data-gallery-delete-url="{{ route('admin.items.gallery.destroy', ['item' => $item->id, 'gallery' => $gallery->id]) }}"
                                    aria-label="Remove gallery image">
                                    <i class="mdi mdi-close"></i>
                                </button>
                                <img src="{{ \Storage::disk('item')->url($gallery->name) }}" alt="Gallery image">
                                <div class="item-existing-gallery-caption">
                                    Gallery #{{ $loop->iteration }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                @endif

                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div class="text-muted small">
                        Add gallery images with preview support, then remove unwanted ones before save.
                    </div>
                    <button type="button" id="item-gallery-browse" class="btn btn-primary btn-sm">
                        <i class="mdi mdi-image-multiple me-1"></i>Choose Gallery Images
                    </button>
                </div>
                <div id="item-gallery-dropzone" class="item-gallery-dropzone"></div>
                <input id="item-gallery-input" type="file" name="gallery_images[]"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple class="d-none">
                <div class="form-text">Use the gallery uploader to preview images before save and remove them one by
                    one.</div>
                @error('gallery_images')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                @error('gallery_images.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

@php
    $oldOptionGroups = old('option_groups');
    $oldVariants = old('variants');
    $valueKeyById = [];

    if ($oldOptionGroups !== null) {
        $serializedOptionGroups = array_values($oldOptionGroups);
        $serializedVariants = array_values($oldVariants ?? []);
    } else {
        $storedOptionGroups = $item->relationLoaded('optionGroups') ? $item->optionGroups : collect();
        $serializedOptionGroups = $storedOptionGroups->values()->map(function ($group, $groupIndex) use (&$valueKeyById) {
            $groupKey = 'group_' . ($group->id ?: $groupIndex);

            return [
                'key' => $groupKey,
                'item_variation_id' => $group->item_variation_id,
                'name' => $group->name,
                'foreign_name' => $group->foreign_name,
                'type' => $group->type,
                'selection_type' => $group->selection_type,
                'is_required' => (bool) $group->is_required,
                'min_selections' => (int) $group->min_selections,
                'max_selections' => $group->max_selections !== null ? (int) $group->max_selections : null,
                'sort_order' => (int) $group->sort_order,
                'status' => $group->status,
                'values' => $group->values->values()->map(function ($value, $valueIndex) use (&$valueKeyById, $groupKey) {
                    $valueKey = 'value_' . ($value->id ?: $groupKey . '_' . $valueIndex);
                    $valueKeyById[$value->id] = $valueKey;

                    return [
                        'key' => $valueKey,
                        'name' => $value->name,
                        'foreign_name' => $value->foreign_name,
                        'sku_suffix' => $value->sku_suffix,
                        'color_hex' => $value->color_hex,
                        'price_adjustment' => (float) $value->price_adjustment,
                        'is_default' => (bool) $value->is_default,
                        'sort_order' => (int) $value->sort_order,
                        'status' => $value->status,
                    ];
                })->all(),
            ];
        })->all();

        $storedVariants = $item->relationLoaded('variants') ? $item->variants : collect();
        $serializedVariants = $storedVariants->values()->map(function ($variant) use ($valueKeyById) {
            return [
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'name' => $variant->name,
                'price' => $variant->price !== null ? (float) $variant->price : null,
                'stock' => (int) $variant->stock,
                'is_default' => (bool) $variant->is_default,
                'sort_order' => (int) $variant->sort_order,
                'status' => $variant->status,
                'option_value_keys' => $variant->optionValues
                    ->pluck('id')
                    ->map(fn ($id) => $valueKeyById[$id] ?? null)
                    ->filter()
                    ->values()
                    ->all(),
            ];
        })->all();
    }

    $configurationEnabled = old('configuration_enabled') !== null
        ? (bool) old('configuration_enabled')
        : count($serializedOptionGroups) > 0;
    $configurationJson = json_encode([
        'enabled' => $configurationEnabled,
        'option_groups' => $serializedOptionGroups,
        'variants' => $serializedVariants,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $variationMastersJson = json_encode($variationMasters->values()->map(fn ($variation) => [
        'id' => (int) $variation->id,
        'name' => $variation->name,
        'foreign_name' => $variation->foreign_name,
        'type' => $variation->type,
        'selection_type' => $variation->selection_type,
        'is_required' => (bool) $variation->is_required,
        'min_selections' => (int) $variation->min_selections,
        'max_selections' => $variation->max_selections !== null ? (int) $variation->max_selections : null,
        'options' => $variation->options->values()->map(fn ($option) => [
            'id' => (int) $option->id,
            'name' => $option->name,
            'foreign_name' => $option->foreign_name,
            'sku_suffix' => $option->sku_suffix,
            'color_hex' => $option->color_hex,
            'price_adjustment' => (float) $option->price_adjustment,
            'is_default' => (bool) $option->is_default,
            'status' => $option->status,
        ])->all(),
    ])->all(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
@endphp

<div class="card card-flush mt-4" id="item-configurator">
    <div class="card-header align-items-center gap-3 flex-wrap py-3">
        <div class="card-title d-block">
            <h2 class="mb-1">Options &amp; Variants</h2>
            <div class="text-muted fs-6">Options, modifiers, and sellable combinations</div>
        </div>
        <div class="item-config-mode" role="group" aria-label="Item configuration type">
            <button type="button" data-config-mode="simple">
                <i class="mdi mdi-cube-outline me-1"></i>Simple item
            </button>
            <button type="button" data-config-mode="configurable">
                <i class="mdi mdi-source-branch me-1"></i>Configurable
            </button>
        </div>
    </div>
    <div class="card-body pt-2">
        <input type="hidden" id="item-configuration-enabled" name="configuration_enabled" value="{{ $configurationEnabled ? 1 : 0 }}">

        @if ($errors->has('option_groups') || $errors->has('variants'))
            <div class="alert alert-danger mb-4">
                {{ $errors->first('option_groups') ?: $errors->first('variants') }}
            </div>
        @endif

        <div id="item-config-simple-state" class="item-option-empty">
            <i class="mdi mdi-cube-outline d-block fs-2 mb-2"></i>
            This item uses the base SKU, price, and stock entered above.
        </div>

        <div id="item-config-content" class="d-none">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div class="item-config-summary" id="item-config-summary"></div>
            </div>

            <div class="item-master-picker mb-3">
                <select id="item-master-select" class="form-select item-master-select" aria-label="Select variation">
                    <option value="">Select variation</option>
                    <option value="custom">Custom group</option>
                    @foreach ($variationMasters as $variationMaster)
                        <option value="{{ $variationMaster->id }}">{{ $variationMaster->name }}</option>
                    @endforeach
                </select>
                <select id="item-master-option-select" class="form-select" multiple disabled aria-label="Select options"></select>
                <button type="button" id="item-master-add" class="btn btn-primary">
                    <i class="mdi mdi-plus me-1"></i>Add
                </button>
            </div>

            <div id="item-option-groups" class="d-flex flex-column gap-3"></div>

            <div class="border-top mt-4 pt-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
                    <div>
                        <h3 class="h5 mb-1">Sellable Variants</h3>
                        <p class="text-muted mb-0">Each combination can use its own SKU, barcode, price, and stock.</p>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" data-generate-variants>
                        <i class="mdi mdi-auto-fix me-1"></i>Generate variants
                    </button>
                </div>
                <div id="item-variants"></div>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="item-configuration-state">{!! $configurationJson !!}</script>
<script type="application/json" id="item-variation-masters-state">{!! $variationMastersJson !!}</script>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const root = document.getElementById('item-configurator');
        const stateElement = document.getElementById('item-configuration-state');
        const masterStateElement = document.getElementById('item-variation-masters-state');

        if (!root || !stateElement || !masterStateElement) {
            return;
        }

        const state = JSON.parse(stateElement.textContent || '{}');
        const variationMasters = JSON.parse(masterStateElement.textContent || '[]');
        state.enabled = Boolean(state.enabled);
        state.option_groups = Array.isArray(state.option_groups) ? state.option_groups : [];
        state.variants = Array.isArray(state.variants) ? state.variants : [];

        const enabledInput = document.getElementById('item-configuration-enabled');
        const content = document.getElementById('item-config-content');
        const simpleState = document.getElementById('item-config-simple-state');
        const groupContainer = document.getElementById('item-option-groups');
        const variantContainer = document.getElementById('item-variants');
        const summary = document.getElementById('item-config-summary');
        const masterSelect = document.getElementById('item-master-select');
        const masterOptionSelect = document.getElementById('item-master-option-select');
        const masterAddButton = document.getElementById('item-master-add');

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const uniqueKey = (prefix) => {
            const token = window.crypto && window.crypto.randomUUID
                ? window.crypto.randomUUID()
                : `${Date.now()}_${Math.random().toString(16).slice(2)}`;

            return `${prefix}_${token.replace(/-/g, '_')}`;
        };

        const checked = (value) => value ? 'checked' : '';
        const selected = (value, expected) => value === expected ? 'selected' : '';
        const inputValue = (value) => value === null || value === undefined ? '' : value;

        const normalizeGroup = (group, index) => ({
            key: group.key || uniqueKey('group'),
            item_variation_id: group.item_variation_id ? Number(group.item_variation_id) : null,
            name: group.name || '',
            foreign_name: group.foreign_name || '',
            type: group.type === 'modifier' ? 'modifier' : 'variant',
            selection_type: group.type === 'variant' ? 'single' : (group.selection_type || 'single'),
            is_required: group.type === 'variant' ? true : Boolean(group.is_required),
            min_selections: group.type === 'variant' ? 1 : Number(group.min_selections || 0),
            max_selections: group.type === 'variant' ? 1 : inputValue(group.max_selections),
            sort_order: Number(group.sort_order ?? index),
            status: group.status || 'Active',
            values: Array.isArray(group.values) ? group.values.map((value, valueIndex) => ({
                key: value.key || uniqueKey('value'),
                name: value.name || '',
                foreign_name: value.foreign_name || '',
                sku_suffix: value.sku_suffix || '',
                color_hex: value.color_hex || '',
                price_adjustment: inputValue(value.price_adjustment ?? 0),
                is_default: Boolean(value.is_default),
                sort_order: Number(value.sort_order ?? valueIndex),
                status: value.status || 'Active',
            })) : [],
        });

        state.option_groups = state.option_groups.map(normalizeGroup);
        state.variants = state.variants.map((variant, index) => ({
            sku: variant.sku || '',
            barcode: variant.barcode || '',
            name: variant.name || '',
            price: inputValue(variant.price),
            stock: Number(variant.stock || 0),
            is_default: Boolean(variant.is_default),
            sort_order: Number(variant.sort_order ?? index),
            status: variant.status || 'Active',
            option_value_keys: Array.isArray(variant.option_value_keys) ? variant.option_value_keys : [],
        }));

        const renderGroupSettings = (group, groupIndex, prefix) => {
            const isVariant = group.type === 'variant';

            return `
                <div class="item-group-settings mt-3">
                    <div>
                        <label class="form-label">Group behavior</label>
                        <select class="form-select" name="${prefix}[type]" data-group-index="${groupIndex}" data-group-field="type">
                            <option value="variant" ${selected(group.type, 'variant')}>Variant</option>
                            <option value="modifier" ${selected(group.type, 'modifier')}>Modifier</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Customer selection</label>
                        ${isVariant ? `
                            <select class="form-select" disabled data-config-locked><option>Single</option></select>
                            <input type="hidden" name="${prefix}[selection_type]" value="single">` : `
                            <select class="form-select" name="${prefix}[selection_type]" data-group-index="${groupIndex}" data-group-field="selection_type">
                                <option value="single" ${selected(group.selection_type, 'single')}>Single</option>
                                <option value="multiple" ${selected(group.selection_type, 'multiple')}>Multiple</option>
                            </select>`}
                    </div>
                    <div>
                        <label class="form-label">Required</label>
                        <div class="item-group-required">
                            ${isVariant ? `
                                <input type="hidden" name="${prefix}[is_required]" value="1">
                                <div class="form-check"><input class="form-check-input" type="checkbox" checked disabled data-config-locked><label class="form-check-label">Required</label></div>` : `
                                <input type="hidden" name="${prefix}[is_required]" value="0">
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="${prefix}[is_required]" value="1" ${checked(group.is_required)} data-group-index="${groupIndex}" data-group-field="is_required"><label class="form-check-label">Required</label></div>`}
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Minimum</label>
                        ${isVariant ? `
                            <input class="form-control" type="number" value="1" disabled data-config-locked>
                            <input type="hidden" name="${prefix}[min_selections]" value="1">` : `
                            <input class="form-control" type="number" min="0" name="${prefix}[min_selections]" value="${escapeHtml(group.min_selections)}" data-group-index="${groupIndex}" data-group-field="min_selections">`}
                    </div>
                    <div>
                        <label class="form-label">Maximum</label>
                        ${isVariant ? `
                            <input class="form-control" type="number" value="1" disabled data-config-locked>
                            <input type="hidden" name="${prefix}[max_selections]" value="1">` : `
                            <input class="form-control" type="number" min="1" name="${prefix}[max_selections]" value="${escapeHtml(group.max_selections)}" placeholder="No limit" data-group-index="${groupIndex}" data-group-field="max_selections">`}
                    </div>
                </div>`;
        };

        const renderGroup = (group, groupIndex) => {
            const prefix = `option_groups[${groupIndex}]`;
            const variation = variationMasters.find((entry) => Number(entry.id) === Number(group.item_variation_id));
            const isCustom = !variation;

            if (isCustom) {
                const valueRows = group.values.map((value, valueIndex) => {
                    const valuePrefix = `${prefix}[values][${valueIndex}]`;

                    return `
                        <div class="item-option-value-row">
                            <div class="item-option-value-name d-grid gap-1">
                                <input type="text" name="${valuePrefix}[name]" value="${escapeHtml(value.name)}" class="form-control form-control-sm" placeholder="Value name" required data-group-index="${groupIndex}" data-value-index="${valueIndex}" data-value-field="name">
                                <input type="text" name="${valuePrefix}[color_hex]" value="${escapeHtml(value.color_hex)}" class="form-control form-control-sm" placeholder="Color HEX, optional" data-group-index="${groupIndex}" data-value-index="${valueIndex}" data-value-field="color_hex">
                            </div>
                            <input type="text" name="${valuePrefix}[sku_suffix]" value="${escapeHtml(value.sku_suffix)}" class="form-control form-control-sm" placeholder="RED or XL" data-group-index="${groupIndex}" data-value-index="${valueIndex}" data-value-field="sku_suffix">
                            <input type="number" step="0.00000001" name="${valuePrefix}[price_adjustment]" value="${escapeHtml(value.price_adjustment)}" class="form-control form-control-sm" placeholder="0.00" data-group-index="${groupIndex}" data-value-index="${valueIndex}" data-value-field="price_adjustment">
                            <div class="form-check d-flex justify-content-center">
                                <input class="form-check-input" type="checkbox" ${checked(value.is_default)} data-value-default data-group-index="${groupIndex}" data-value-index="${valueIndex}" title="Default value">
                            </div>
                            <button type="button" class="btn btn-outline-danger btn-sm item-config-icon-button item-option-value-remove" data-remove-option-value data-group-index="${groupIndex}" data-value-index="${valueIndex}" title="Remove value"><i class="mdi mdi-delete-outline"></i></button>
                            <input type="hidden" name="${valuePrefix}[key]" value="${escapeHtml(value.key)}">
                            <input type="hidden" name="${valuePrefix}[foreign_name]" value="${escapeHtml(value.foreign_name)}">
                            <input type="hidden" name="${valuePrefix}[is_default]" value="${value.is_default ? 1 : 0}">
                            <input type="hidden" name="${valuePrefix}[sort_order]" value="${valueIndex}">
                            <input type="hidden" name="${valuePrefix}[status]" value="${escapeHtml(value.status || 'Active')}">
                        </div>`;
                }).join('');

                return `
                    <section class="item-option-group">
                        <div class="item-option-group-header">
                            <div class="d-flex align-items-center gap-2">
                                <span class="item-option-group-index">${groupIndex + 1}</span>
                                <div><strong>Option group ${groupIndex + 1}</strong><div class="text-muted small">Builds sellable combinations</div></div>
                            </div>
                            <button type="button" class="btn btn-outline-danger btn-sm item-config-icon-button" data-remove-option-group data-group-index="${groupIndex}" title="Remove group"><i class="mdi mdi-delete-outline"></i></button>
                        </div>
                        <div class="item-option-group-body">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6"><label class="form-label">Group name</label><input type="text" name="${prefix}[name]" value="${escapeHtml(group.name)}" class="form-control" placeholder="Color, Size, Storage" required data-group-index="${groupIndex}" data-group-field="name"></div>
                                <div class="col-md-6"><label class="form-label">Foreign name</label><input type="text" name="${prefix}[foreign_name]" value="${escapeHtml(group.foreign_name)}" class="form-control" placeholder="Optional" data-group-index="${groupIndex}" data-group-field="foreign_name"></div>
                            </div>
                            ${renderGroupSettings(group, groupIndex, prefix)}
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                <label class="form-label mb-0">Values</label>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-add-option-value data-group-index="${groupIndex}"><i class="mdi mdi-plus me-1"></i>Add value</button>
                            </div>
                            <div class="item-option-value-head"><span>Value / color</span><span>SKU suffix</span><span>Price adjustment</span><span>Default</span><span></span></div>
                            <div>${valueRows}</div>
                            <input type="hidden" name="${prefix}[key]" value="${escapeHtml(group.key)}">
                            <input type="hidden" name="${prefix}[item_variation_id]" value="">
                            <input type="hidden" name="${prefix}[sort_order]" value="${groupIndex}">
                            <input type="hidden" name="${prefix}[status]" value="Active">
                        </div>
                    </section>`;
            }

            const variationOptions = variationMasters.map((entry) => `
                <option value="${entry.id}" ${Number(entry.id) === Number(group.item_variation_id) ? 'selected' : ''}>${escapeHtml(entry.name)}</option>`).join('');
            const selectedNames = new Set(group.values.map((value) => value.name));
            const optionChoices = variation
                ? variation.options.map((option) => `<option value="${option.id}" ${selectedNames.has(option.name) ? 'selected' : ''}>${escapeHtml(option.name)}</option>`).join('')
                : '';
            const hiddenValues = group.values.map((value, valueIndex) => {
                const valuePrefix = `${prefix}[values][${valueIndex}]`;
                return `
                    <input type="hidden" name="${valuePrefix}[key]" value="${escapeHtml(value.key)}">
                    <input type="hidden" name="${valuePrefix}[name]" value="${escapeHtml(value.name)}">
                    <input type="hidden" name="${valuePrefix}[foreign_name]" value="${escapeHtml(value.foreign_name)}">
                    <input type="hidden" name="${valuePrefix}[sku_suffix]" value="${escapeHtml(value.sku_suffix)}">
                    <input type="hidden" name="${valuePrefix}[color_hex]" value="${escapeHtml(value.color_hex)}">
                    <input type="hidden" name="${valuePrefix}[price_adjustment]" value="${escapeHtml(value.price_adjustment)}">
                    <input type="hidden" name="${valuePrefix}[is_default]" value="${value.is_default ? 1 : 0}">
                    <input type="hidden" name="${valuePrefix}[sort_order]" value="${valueIndex}">
                    <input type="hidden" name="${valuePrefix}[status]" value="${escapeHtml(value.status || 'Active')}">`;
            }).join('');

            return `
                <section class="item-option-group">
                    <div class="item-option-group-header">
                        <div class="d-flex align-items-center gap-2">
                            <span class="item-option-group-index">${groupIndex + 1}</span>
                            <div><strong>${escapeHtml(variation?.name || 'Select variation')}</strong><div class="text-muted small">Variation Master</div></div>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-sm item-config-icon-button" data-remove-option-group data-group-index="${groupIndex}" title="Remove group"><i class="mdi mdi-delete-outline"></i></button>
                    </div>
                    <div class="item-option-group-body">
                        <div class="item-group-master-fields">
                            <div><label class="form-label">Variation</label><select class="form-select" data-group-variation-select data-group-index="${groupIndex}"><option value="">Select variation</option>${variationOptions}</select></div>
                            <div><label class="form-label">Options</label><select class="form-select item-group-option-select" multiple data-group-option-select data-group-index="${groupIndex}" ${variation ? '' : 'disabled'}>${optionChoices}</select></div>
                        </div>
                        ${renderGroupSettings(group, groupIndex, prefix)}
                        <input type="hidden" name="${prefix}[key]" value="${escapeHtml(group.key)}">
                        <input type="hidden" name="${prefix}[item_variation_id]" value="${escapeHtml(group.item_variation_id || '')}">
                        <input type="hidden" name="${prefix}[name]" value="${escapeHtml(group.name)}">
                        <input type="hidden" name="${prefix}[foreign_name]" value="${escapeHtml(group.foreign_name)}">
                        <input type="hidden" name="${prefix}[sort_order]" value="${groupIndex}">
                        <input type="hidden" name="${prefix}[status]" value="Active">
                        ${hiddenValues}
                    </div>
                </section>`;
        };

        const valueByKey = () => {
            const map = new Map();
            state.option_groups.forEach((group) => group.values.forEach((value) => map.set(value.key, { group, value })));
            return map;
        };

        const renderVariants = () => {
            const variantGroups = state.option_groups.filter((group) => group.type === 'variant');

            if (!variantGroups.length) {
                variantContainer.innerHTML = '<div class="item-option-empty py-3">Add a variation group to create sellable variants. Modifier groups do not create SKUs.</div>';
                return;
            }

            if (!state.variants.length) {
                variantContainer.innerHTML = '<div class="item-option-empty py-3">Generate combinations after adding values to every variation group.</div>';
                return;
            }

            const values = valueByKey();
            const rows = state.variants.map((variant, index) => {
                const prefix = `variants[${index}]`;
                const labels = variant.option_value_keys.map((key) => values.get(key)?.value?.name || key);
                const hiddenKeys = variant.option_value_keys.map((key) => `<input type="hidden" name="${prefix}[option_value_keys][]" value="${escapeHtml(key)}">`).join('');

                return `
                    <tr>
                        <td data-label="Combination">
                            ${hiddenKeys}
                            <input type="hidden" name="${prefix}[sort_order]" value="${index}">
                            <input type="text" name="${prefix}[name]" value="${escapeHtml(variant.name || labels.join(' / '))}" class="form-control form-control-sm mb-1"
                                data-variant-index="${index}" data-variant-field="name">
                            <div class="d-flex flex-wrap gap-1">${labels.map((label) => `<span class="badge bg-light text-dark">${escapeHtml(label)}</span>`).join('')}</div>
                        </td>
                        <td data-label="SKU"><input type="text" name="${prefix}[sku]" value="${escapeHtml(variant.sku)}" class="form-control form-control-sm" required data-variant-index="${index}" data-variant-field="sku"></td>
                        <td data-label="Barcode"><input type="text" name="${prefix}[barcode]" value="${escapeHtml(variant.barcode)}" class="form-control form-control-sm" data-variant-index="${index}" data-variant-field="barcode"></td>
                        <td data-label="Price"><input type="number" min="0" step="0.00000001" name="${prefix}[price]" value="${escapeHtml(variant.price)}" class="form-control form-control-sm" placeholder="Base price" data-variant-index="${index}" data-variant-field="price"></td>
                        <td data-label="Stock"><input type="number" min="0" step="1" name="${prefix}[stock]" value="${escapeHtml(variant.stock)}" class="form-control form-control-sm" required data-variant-index="${index}" data-variant-field="stock"></td>
                        <td data-label="Default / status">
                            <input type="hidden" name="${prefix}[is_default]" value="${variant.is_default ? 1 : 0}">
                            <input type="hidden" name="${prefix}[status]" value="${escapeHtml(variant.status)}">
                            <div class="d-flex align-items-center gap-2">
                                <input class="form-check-input mt-0" type="radio" name="item_default_variant" ${checked(variant.is_default)} data-default-variant data-variant-index="${index}" title="Default variant">
                                <select class="form-select form-select-sm" name="${prefix}[status]" data-variant-index="${index}" data-variant-field="status">
                                    <option value="Active" ${selected(variant.status, 'Active')}>Active</option>
                                    <option value="Inactive" ${selected(variant.status, 'Inactive')}>Inactive</option>
                                </select>
                            </div>
                        </td>
                        <td data-label="Actions">
                            <button type="button" class="btn btn-outline-danger btn-sm item-config-icon-button" data-remove-variant data-variant-index="${index}" title="Remove variant">
                                <i class="mdi mdi-delete-outline"></i>
                            </button>
                        </td>
                    </tr>`;
            }).join('');

            variantContainer.innerHTML = `
                <div class="table-responsive item-variant-scroll">
                    <table class="table table-bordered align-middle item-variant-table mb-0">
                        <thead><tr><th>Combination</th><th>SKU</th><th>Barcode</th><th>Price</th><th>Stock</th><th>Default / status</th><th></th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>`;
        };

        const render = () => {
            enabledInput.value = state.enabled ? '1' : '0';
            content.classList.toggle('d-none', !state.enabled);
            simpleState.classList.toggle('d-none', state.enabled);
            root.querySelectorAll('[data-config-mode]').forEach((button) => {
                button.classList.toggle('is-active', button.dataset.configMode === (state.enabled ? 'configurable' : 'simple'));
            });

            groupContainer.innerHTML = state.option_groups.length
                ? state.option_groups.map(renderGroup).join('')
                : '<div class="item-option-empty"><i class="mdi mdi-format-list-bulleted d-block fs-2 mb-2"></i>Add a variation group.</div>';

            if (window.jQuery && window.jQuery.fn.select2) {
                window.jQuery('.item-group-option-select').select2({
                    width: '100%',
                    placeholder: 'Select options',
                    closeOnSelect: false,
                });
            }

            const valueCount = state.option_groups.reduce((total, group) => total + group.values.length, 0);
            const groupCount = state.option_groups.length;
            const variantCount = state.variants.length;
            summary.innerHTML = `<span><strong>${groupCount}</strong> ${groupCount === 1 ? 'group' : 'groups'}</span><span><strong>${valueCount}</strong> ${valueCount === 1 ? 'value' : 'values'}</span><span><strong>${variantCount}</strong> ${variantCount === 1 ? 'variant' : 'variants'}</span>`;
            renderVariants();
            content.querySelectorAll('input, select, textarea').forEach((control) => {
                control.disabled = !state.enabled || control.hasAttribute('data-config-locked');
            });
            masterAddButton.disabled = !state.enabled || state.option_groups.length >= 20 || !masterSelect.value;
        };

        const clearVariants = () => {
            state.variants = [];
        };

        const createCustomGroup = () => normalizeGroup({
            key: uniqueKey('group'),
            item_variation_id: null,
            name: '',
            values: [{ key: uniqueKey('value'), name: '' }],
        }, state.option_groups.length);

        const createMasterGroup = (variation, selectedOptionIds) => normalizeGroup({
            key: uniqueKey('group'),
            item_variation_id: variation.id,
            name: variation.name,
            foreign_name: variation.foreign_name || '',
            type: variation.type,
            selection_type: variation.selection_type,
            is_required: variation.is_required,
            min_selections: variation.min_selections,
            max_selections: variation.max_selections,
            status: 'Active',
            values: variation.options
                .filter((option) => selectedOptionIds.includes(Number(option.id)))
                .map((option) => ({ ...option, key: uniqueKey('value') })),
        }, state.option_groups.length);

        const applyVariationToGroup = (groupIndex, variationId) => {
            const group = state.option_groups[groupIndex];
            const variation = variationMasters.find((entry) => Number(entry.id) === Number(variationId));

            if (!variation) {
                return;
            }

            const duplicate = state.option_groups.some((entry, index) => index !== groupIndex && Number(entry.item_variation_id) === Number(variation.id));
            if (duplicate) {
                window.alert('This variation is already added to the item.');
                return;
            }

            Object.assign(group, createMasterGroup(
                variation,
                variation.options.map((option) => Number(option.id))
            ), { key: group.key, sort_order: groupIndex });
        };

        const refreshMasterOptions = () => {
            const variation = variationMasters.find((entry) => Number(entry.id) === Number(masterSelect.value));
            const options = variation?.options || [];
            const isCustom = masterSelect.value === 'custom';

            masterOptionSelect.innerHTML = options.map((option) => `<option value="${option.id}" selected>${escapeHtml(option.name)}</option>`).join('');
            masterOptionSelect.disabled = !variation;

            if (window.jQuery && window.jQuery.fn.select2) {
                window.jQuery(masterOptionSelect).trigger('change.select2');
            }

            masterAddButton.disabled = !state.enabled || !masterSelect.value || (!isCustom && !options.length);
        };

        const combinationKey = (keys) => [...keys].sort().join('|');

        const generateVariants = () => {
            const groups = state.option_groups.filter((group) => group.type === 'variant');

            if (!groups.length) {
                window.alert('Add at least one variation group first.');
                return;
            }

            if (groups.some((group) => !group.values.length || group.values.some((value) => !value.name.trim()))) {
                window.alert('Enter a name for every value in each variation group.');
                return;
            }

            let combinations = [[]];
            groups.forEach((group) => {
                combinations = combinations.flatMap((combination) => group.values.map((value) => [...combination, value]));
            });

            if (combinations.length > 500) {
                window.alert('This configuration creates more than 500 variants. Reduce the number of values first.');
                return;
            }

            const existing = new Map(state.variants.map((variant) => [combinationKey(variant.option_value_keys), variant]));
            const baseSku = (document.querySelector('input[name="sku"]')?.value || 'ITEM').trim().toUpperCase();
            const baseStock = Number(document.querySelector('input[name="stock"]')?.value || 0);

            state.variants = combinations.map((values, index) => {
                const keys = values.map((value) => value.key);
                const previous = existing.get(combinationKey(keys));
                const suffix = values.map((value) => value.sku_suffix || value.name)
                    .map((value) => String(value).trim().toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-|-$/g, ''))
                    .filter(Boolean)
                    .join('-');

                return previous || {
                    sku: suffix ? `${baseSku}-${suffix}` : `${baseSku}-${index + 1}`,
                    barcode: '',
                    name: values.map((value) => value.name).join(' / '),
                    price: '',
                    stock: baseStock,
                    is_default: index === 0,
                    sort_order: index,
                    status: 'Active',
                    option_value_keys: keys,
                };
            });

            if (!state.variants.some((variant) => variant.is_default) && state.variants.length) {
                state.variants[0].is_default = true;
            }

            render();
        };

        root.addEventListener('click', function (event) {
            const modeButton = event.target.closest('[data-config-mode]');
            const addMasterButton = event.target.closest('#item-master-add');
            const addValueButton = event.target.closest('[data-add-option-value]');
            const removeValueButton = event.target.closest('[data-remove-option-value]');
            const removeGroupButton = event.target.closest('[data-remove-option-group]');
            const generateButton = event.target.closest('[data-generate-variants]');
            const removeVariantButton = event.target.closest('[data-remove-variant]');

            if (modeButton) {
                state.enabled = modeButton.dataset.configMode === 'configurable';
                render();
                refreshMasterOptions();
            } else if (addMasterButton) {
                if (masterSelect.value === 'custom') {
                    state.option_groups.push(createCustomGroup());
                } else {
                    const variation = variationMasters.find((entry) => Number(entry.id) === Number(masterSelect.value));
                    const selectedOptionIds = Array.from(masterOptionSelect.selectedOptions).map((option) => Number(option.value));

                    if (!variation || !selectedOptionIds.length) {
                        window.alert('Select at least one option for this variation.');
                        return;
                    }

                    if (state.option_groups.some((group) => Number(group.item_variation_id) === Number(variation.id))) {
                        window.alert('This variation is already added to the item.');
                        return;
                    }

                    state.option_groups.push(createMasterGroup(variation, selectedOptionIds));
                }

                masterSelect.value = '';
                masterOptionSelect.innerHTML = '';
                clearVariants();
                render();
                refreshMasterOptions();
            } else if (addValueButton) {
                const groupIndex = Number(addValueButton.dataset.groupIndex);
                state.option_groups[groupIndex].values.push({
                    key: uniqueKey('value'),
                    name: '',
                    foreign_name: '',
                    sku_suffix: '',
                    color_hex: '',
                    price_adjustment: 0,
                    is_default: false,
                    sort_order: state.option_groups[groupIndex].values.length,
                    status: 'Active',
                });
                clearVariants();
                render();
            } else if (removeValueButton) {
                const groupIndex = Number(removeValueButton.dataset.groupIndex);
                const valueIndex = Number(removeValueButton.dataset.valueIndex);
                state.option_groups[groupIndex].values.splice(valueIndex, 1);
                clearVariants();
                render();
            } else if (removeGroupButton) {
                state.option_groups.splice(Number(removeGroupButton.dataset.groupIndex), 1);
                clearVariants();
                render();
            } else if (generateButton) {
                generateVariants();
            } else if (removeVariantButton) {
                state.variants.splice(Number(removeVariantButton.dataset.variantIndex), 1);
                if (!state.variants.some((variant) => variant.is_default) && state.variants.length) {
                    state.variants[0].is_default = true;
                }
                render();
            }
        });

        root.addEventListener('input', function (event) {
            const target = event.target;
            const groupIndex = Number(target.dataset.groupIndex);
            const valueIndex = Number(target.dataset.valueIndex);
            const variantIndex = Number(target.dataset.variantIndex);
            const fieldValue = target.type === 'checkbox' ? target.checked : target.value;

            if (target.dataset.groupField && Number.isInteger(groupIndex)) {
                state.option_groups[groupIndex][target.dataset.groupField] = fieldValue;
                clearVariants();
            }

            if (target.dataset.valueField && Number.isInteger(groupIndex) && Number.isInteger(valueIndex)) {
                state.option_groups[groupIndex].values[valueIndex][target.dataset.valueField] = fieldValue;
                clearVariants();
            }

            if (target.dataset.variantField && Number.isInteger(variantIndex)) {
                state.variants[variantIndex][target.dataset.variantField] = fieldValue;
            }
        });

        root.addEventListener('change', function (event) {
            const target = event.target;

            if (target === masterSelect) {
                refreshMasterOptions();
            }

            if (target.matches('[data-group-variation-select]')) {
                applyVariationToGroup(Number(target.dataset.groupIndex), target.value);
                clearVariants();
                render();
            }

            if (target.matches('[data-group-option-select]')) {
                const groupIndex = Number(target.dataset.groupIndex);
                const group = state.option_groups[groupIndex];
                const variation = variationMasters.find((entry) => Number(entry.id) === Number(group.item_variation_id));
                const selectedIds = Array.from(target.selectedOptions).map((option) => Number(option.value));
                const existingByName = new Map(group.values.map((value) => [value.name, value]));

                if (variation) {
                    group.values = variation.options
                        .filter((option) => selectedIds.includes(Number(option.id)))
                        .map((option) => ({ ...option, key: existingByName.get(option.name)?.key || uniqueKey('value') }));
                    clearVariants();
                    render();
                }
            }

            if (target.matches('[data-group-field="type"]')) {
                const groupIndex = Number(target.dataset.groupIndex);
                const group = state.option_groups[groupIndex];

                if (group.type === 'variant') {
                    group.selection_type = 'single';
                    group.is_required = true;
                    group.min_selections = 1;
                    group.max_selections = 1;
                } else {
                    group.is_required = false;
                    group.min_selections = 0;
                    group.max_selections = group.selection_type === 'single' ? 1 : inputValue(group.max_selections);
                }

                clearVariants();
                render();
            }

            if (target.matches('[data-group-field="selection_type"]')) {
                const groupIndex = Number(target.dataset.groupIndex);
                const group = state.option_groups[groupIndex];

                if (group.selection_type === 'single') {
                    group.max_selections = 1;
                    group.min_selections = Math.min(Number(group.min_selections || 0), 1);
                }

                render();
            }

            if (target.matches('[data-value-default]')) {
                const groupIndex = Number(target.dataset.groupIndex);
                const valueIndex = Number(target.dataset.valueIndex);
                state.option_groups[groupIndex].values.forEach((value, index) => {
                    value.is_default = target.checked && index === valueIndex;
                });
                render();
            }

            if (target.matches('[data-default-variant]')) {
                const selectedIndex = Number(target.dataset.variantIndex);
                state.variants.forEach((variant, index) => variant.is_default = index === selectedIndex);
                render();
            }
        });

        if (window.jQuery && window.jQuery.fn.select2) {
            window.jQuery(masterOptionSelect).select2({
                width: '100%',
                placeholder: 'Select options',
                closeOnSelect: false,
            });
        }

        render();
        refreshMasterOptions();
    });
</script>
@endpush
