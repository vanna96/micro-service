@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const placementSelect = document.getElementById('slider-placement');
                const dimensionsInput = document.getElementById('slider-recommended-dimensions');
                const imageHint = document.getElementById('slider-image-hint');
                const imageInput = document.getElementById('slider-image-input');
                const previewWrapper = document.getElementById('slider-image-preview-wrapper');
                const previewImage = document.getElementById('slider-image-preview');
                const previewEmpty = document.getElementById('slider-image-preview-empty');
                let previewObjectUrl = null;

                const placementHints = {
                    Website: {
                        summary: 'Recommended website banner ratio 16:9 or wider.',
                        example: 'Good starting sizes: 1600x900, 1920x800, or similar wide hero banners.'
                    },
                    Mobile: {
                        summary: 'Recommended mobile banner ratio 4:5 or 9:16.',
                        example: 'Good starting sizes: 1080x1350, 1080x1920, or similar portrait banners.'
                    }
                };

                const applyPlacementHint = function () {
                    if (!placementSelect || !dimensionsInput || !imageHint) {
                        return;
                    }

                    const selectedPlacement = placementSelect.value === 'Mobile' ? 'Mobile' : 'Website';
                    const hint = placementHints[selectedPlacement];

                    dimensionsInput.value = hint.summary + ' ' + hint.example;
                    imageHint.textContent = hint.summary + ' ' + hint.example;
                };

                if (!placementSelect) {
                    return;
                }

                applyPlacementHint();
                placementSelect.addEventListener('change', applyPlacementHint);

                if (!imageInput || !previewWrapper || !previewImage || !previewEmpty) {
                    return;
                }

                imageInput.addEventListener('change', function (event) {
                    const selectedFile = event.target.files && event.target.files[0] ? event.target.files[0] : null;

                    if (previewObjectUrl) {
                        URL.revokeObjectURL(previewObjectUrl);
                        previewObjectUrl = null;
                    }

                    if (!selectedFile) {
                        previewImage.setAttribute('src', '');
                        previewImage.classList.add('d-none');
                        previewEmpty.classList.remove('d-none');
                        previewWrapper.classList.add('d-none');

                        return;
                    }

                    previewObjectUrl = URL.createObjectURL(selectedFile);
                    previewImage.setAttribute('src', previewObjectUrl);
                    previewImage.classList.remove('d-none');
                    previewEmpty.classList.add('d-none');
                    previewWrapper.classList.remove('d-none');
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
                    <h2>Slider Details</h2>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" value="{{ old('title', $slider->title) }}" class="form-control @error('title') is-invalid @enderror" placeholder="Homepage hero banner" />
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Subtitle</label>
                        <input type="text" name="subtitle" value="{{ old('subtitle', $slider->subtitle) }}" class="form-control @error('subtitle') is-invalid @enderror" placeholder="Optional supporting copy" />
                        @error('subtitle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Placement</label>
                        <select id="slider-placement" name="placement" class="form-select @error('placement') is-invalid @enderror">
                            @foreach (['Website', 'Mobile'] as $placement)
                                <option value="{{ $placement }}" @selected(old('placement', $slider->placement ?: 'Website') === $placement)>{{ $placement }}</option>
                            @endforeach
                        </select>
                        @error('placement')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Target URL</label>
                        <input type="url" name="target_url" value="{{ old('target_url', $slider->target_url) }}" class="form-control @error('target_url') is-invalid @enderror" placeholder="https://example.com/promo" />
                        @error('target_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" min="0" step="1" name="sort_order" value="{{ old('sort_order', $slider->sort_order ?? 0) }}" class="form-control @error('sort_order') is-invalid @enderror" />
                        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Status</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror">
                            @foreach (['Active', 'Inactive'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $slider->status ?: 'Active') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Placement Guidance</label>
                        <input id="slider-recommended-dimensions" type="text" class="form-control" value="{{ old('recommended_dimensions', $slider->recommended_dimensions) }}" readonly />
                        <div id="slider-image-hint" class="form-text">{{ $slider->recommended_dimensions ?: 'Choose Website or Mobile to see the recommended banner ratio.' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card card-flush mb-10">
            <div class="card-header">
                <div class="card-title">
                    <h2>Slider Image</h2>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label {{ $slider->exists ? '' : 'required' }}">Image</label>
                    <input id="slider-image-input" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control @error('image') is-invalid @enderror" />
                    @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div id="slider-image-preview-wrapper" class="border rounded overflow-hidden mb-3 d-none">
                    <img id="slider-image-preview" src="" alt="Selected slider preview" class="img-fluid w-100 d-none">
                    <div id="slider-image-preview-empty" class="p-3 text-muted d-none">No image selected.</div>
                </div>
                @if ($slider->image_url)
                    <div class="border rounded overflow-hidden mb-3">
                        <img src="{{ $slider->image_url }}" alt="{{ $slider->title ?: $slider->placement }}" class="img-fluid w-100">
                    </div>
                @endif
                <div class="form-text">
                    One image per slider record. Create separate records when website and mobile need different artwork or dimensions.
                </div>
            </div>
        </div>
    </div>
</div>
