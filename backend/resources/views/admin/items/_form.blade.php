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
                @php($galleryImages = $item->galleries->where('type', 'galleries'))
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
