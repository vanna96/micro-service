@once
    @push('styles')
        <link href="{{ global_asset('minible/assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
        <style>
            .category-upload-preview {
                width: 100%;
                max-width: 240px;
                aspect-ratio: 1 / 1;
                border-radius: 24px;
                object-fit: cover;
                border: 1px solid #e9edf4;
                background: #fff;
            }

            .category-upload-placeholder {
                width: 100%;
                max-width: 240px;
                aspect-ratio: 1 / 1;
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
        </style>
    @endpush

    @push('scripts')
        <script src="{{ global_asset('minible/assets/libs/select2/js/select2.min.js') }}"></script>
        <script>
            $(function () {
                $('.category-parent-combobox').select2({
                    width: '100%',
                    placeholder: 'Select parent category',
                    allowClear: true
                });

                const imageInput = document.getElementById('category-image-input');
                const imagePreview = document.getElementById('category-image-preview');
                const imagePlaceholder = document.getElementById('category-image-placeholder');

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
            });
        </script>
    @endpush
@endonce

<div class="row g-2">
    <div class="col-xl-8">
        <div class="card card-flush mb-10">
            <div class="card-header">
                <div class="card-title">
                    <h2>Category Details</h2>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label required">Name</label>
                        <input type="text" name="name" value="{{ old('name', $category->name) }}" class="form-control @error('name') is-invalid @enderror" />
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Foreign Name</label>
                        <input type="text" name="foreign_name" value="{{ old('foreign_name', $category->foreign_name) }}" class="form-control @error('foreign_name') is-invalid @enderror" />
                        @error('foreign_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Parent Category</label>
                        <select
                            name="parent_id"
                            class="form-select category-parent-combobox @error('parent_id') is-invalid @enderror"
                            data-placeholder="Select parent category"
                        >
                            <option value="">Top level</option>
                            @foreach ($parentOptions as $parentOption)
                                <option value="{{ $parentOption->id }}" @selected((string) old('parent_id', $category->parent_id) === (string) $parentOption->id)>
                                    {{ $parentOption->name }}{{ $parentOption->foreign_name ? ' / ' . $parentOption->foreign_name : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Status</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror">
                            @foreach (['Active', 'Inactive'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $category->status ?: 'Active') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                    @if ($category->image_url)
                        <img id="category-image-preview" src="{{ $category->image_url }}" alt="Category image" class="category-upload-preview">
                        <div id="category-image-placeholder" class="category-upload-placeholder d-none">
                            {{ strtoupper(substr($category->name ?: 'C', 0, 1)) }}
                        </div>
                    @else
                        <img id="category-image-preview" src="" alt="Category image" class="category-upload-preview d-none">
                        <div id="category-image-placeholder" class="category-upload-placeholder">
                            {{ strtoupper(substr($category->name ?: 'C', 0, 1)) }}
                        </div>
                    @endif
                </div>

                <input id="category-image-input" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control @error('image') is-invalid @enderror" />
                <div class="form-text">Upload JPG, PNG, or WEBP up to 2MB.</div>
                @error('image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>
