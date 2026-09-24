@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const placementSelect = document.getElementById('slider-placement');
                const mediaTypeSelect = document.getElementById('slider-media-type');
                const dimensionsInput = document.getElementById('slider-recommended-dimensions');
                const imageHint = document.getElementById('slider-image-hint');
                
                // Form Inputs for Live Preview
                const titleInput = document.getElementById('slider-title');
                const subtitleInput = document.getElementById('slider-subtitle');
                const badgeInput = document.getElementById('slider-badge');
                const badgeBgInput = document.getElementById('slider-badge-bg');
                const badgeColorInput = document.getElementById('slider-badge-color');
                const discountInput = document.getElementById('slider-discount');
                const gradientInput = document.getElementById('slider-gradient');
                const iconInput = document.getElementById('slider-icon');
                const tagInput = document.getElementById('slider-tag');
                const mediaUrlInput = document.getElementById('slider-media-url');

                // Media Sections
                const sectionImage = document.getElementById('media-section-image');
                const sectionVideo = document.getElementById('media-section-video');
                const sectionGradient = document.getElementById('media-section-gradient');

                // Image Upload & Preview
                const imageInput = document.getElementById('slider-image-input');
                const previewImage = document.getElementById('slider-image-preview');
                const previewEmpty = document.getElementById('slider-image-preview-empty');
                let previewObjectUrl = null;

                // Live Preview Elements
                const liveCardBg = document.getElementById('live-preview-bg');
                const liveCardVideo = document.getElementById('live-preview-video');
                const liveBadge = document.getElementById('live-preview-badge');
                const liveBadgeIcon = document.getElementById('live-preview-badge-icon');
                const liveBadgeText = document.getElementById('live-preview-badge-text');
                const liveDiscount = document.getElementById('live-preview-discount');
                const liveTitle = document.getElementById('live-preview-title');
                const liveSubtitle = document.getElementById('live-preview-subtitle');
                const liveTag = document.getElementById('live-preview-tag');

                const placementHints = {
                    second_screen: {
                        summary: 'Customer Facing Display (Widescreen 16:9).',
                        example: 'Ideal for looping video showcase reels or rich promotional discount cards.'
                    },
                    Website: {
                        summary: 'Recommended website banner ratio 16:9 or wider.',
                        example: 'Good starting sizes: 1600x900, 1920x800, or similar wide hero banners.'
                    },
                    Mobile: {
                        summary: 'Recommended mobile banner ratio 4:5 or 9:16.',
                        example: 'Good starting sizes: 1080x1350, 1080x1920, or similar portrait banners.'
                    },
                    All: {
                        summary: 'Universal multi-channel placement.',
                        example: 'Displayed across Customer Display, Web Storefront, and Mobile App.'
                    }
                };

                const updatePlacementHint = function () {
                    if (!placementSelect || !dimensionsInput || !imageHint) return;
                    const val = placementSelect.value || 'second_screen';
                    const hint = placementHints[val] || placementHints.second_screen;
                    dimensionsInput.value = hint.summary + ' ' + hint.example;
                    imageHint.textContent = hint.summary + ' ' + hint.example;
                };

                const updateMediaTypeVisibility = function () {
                    if (!mediaTypeSelect) return;
                    const type = mediaTypeSelect.value || 'gradient';

                    if (sectionImage) sectionImage.classList.toggle('d-none', type !== 'image');
                    if (sectionVideo) sectionVideo.classList.toggle('d-none', type !== 'video');
                    if (sectionGradient) sectionGradient.classList.toggle('d-none', type !== 'gradient');

                    updateLivePreview();
                };

                const updateLivePreview = function () {
                    const type = mediaTypeSelect ? mediaTypeSelect.value : 'gradient';
                    const title = titleInput ? titleInput.value : '';
                    const subtitle = subtitleInput ? subtitleInput.value : '';
                    const badge = badgeInput ? badgeInput.value : '';
                    const badgeBg = badgeBgInput ? badgeBgInput.value : 'rgba(255, 255, 255, 0.95)';
                    const badgeColor = badgeColorInput ? badgeColorInput.value : '#0f172a';
                    const discount = discountInput ? discountInput.value : '';
                    const gradient = gradientInput ? gradientInput.value : 'linear-gradient(135deg, #1e1b4b 0%, #312e81 45%, #4338ca 100%)';
                    const icon = iconInput ? iconInput.value : 'ri-gift-line';
                    const tag = tagInput ? tagInput.value : '';
                    const mediaUrl = mediaUrlInput ? mediaUrlInput.value : '';

                    if (liveTitle) liveTitle.textContent = title || 'Your Promotion Title';
                    if (liveSubtitle) liveSubtitle.textContent = subtitle || 'Supporting description and offer details.';
                    
                    if (liveBadge) {
                        if (badge) {
                            liveBadge.classList.remove('d-none');
                            if (liveBadgeText) liveBadgeText.textContent = badge;
                            liveBadge.style.backgroundColor = badgeBg;
                            liveBadge.style.color = badgeColor;
                        } else {
                            liveBadge.classList.add('d-none');
                        }
                    }

                    if (liveBadgeIcon) {
                        liveBadgeIcon.className = icon || 'ri-gift-line';
                    }

                    if (liveDiscount) {
                        if (discount) {
                            liveDiscount.classList.remove('d-none');
                            liveDiscount.textContent = discount;
                        } else {
                            liveDiscount.classList.add('d-none');
                        }
                    }

                    if (liveTag) {
                        if (tag) {
                            liveTag.classList.remove('d-none');
                            liveTag.textContent = tag;
                        } else {
                            liveTag.classList.add('d-none');
                        }
                    }

                    if (liveCardBg) {
                        if (type === 'video') {
                            liveCardBg.style.background = '#0f172a';
                            if (liveCardVideo) {
                                liveCardVideo.classList.remove('d-none');
                                if (mediaUrl && liveCardVideo.src !== mediaUrl && !mediaUrl.startsWith('data:')) {
                                    liveCardVideo.src = mediaUrl;
                                    liveCardVideo.play().catch(() => undefined);
                                }
                            }
                        } else {
                            if (liveCardVideo) {
                                liveCardVideo.classList.add('d-none');
                                liveCardVideo.pause();
                            }
                            if (type === 'image' && previewObjectUrl) {
                                liveCardBg.style.background = `url(${previewObjectUrl}) center/cover no-repeat`;
                            } else if (type === 'image' && '{{ $slider->image_url }}') {
                                liveCardBg.style.background = `url('{{ $slider->image_url }}') center/cover no-repeat`;
                            } else {
                                liveCardBg.style.background = gradient;
                            }
                        }
                    }
                };

                // Attach Listeners
                if (placementSelect) placementSelect.addEventListener('change', updatePlacementHint);
                if (mediaTypeSelect) mediaTypeSelect.addEventListener('change', updateMediaTypeVisibility);

                [titleInput, subtitleInput, badgeInput, badgeBgInput, badgeColorInput, discountInput, gradientInput, iconInput, tagInput, mediaUrlInput].forEach(el => {
                    if (el) {
                        el.addEventListener('input', updateLivePreview);
                        el.addEventListener('change', updateLivePreview);
                    }
                });

                // Image upload preview
                if (imageInput) {
                    imageInput.addEventListener('change', function (event) {
                        const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
                        if (previewObjectUrl) {
                            URL.revokeObjectURL(previewObjectUrl);
                            previewObjectUrl = null;
                        }
                        if (file) {
                            previewObjectUrl = URL.createObjectURL(file);
                            if (previewImage) {
                                previewImage.src = previewObjectUrl;
                                previewImage.classList.remove('d-none');
                            }
                            if (previewEmpty) previewEmpty.classList.add('d-none');
                        } else {
                            if (previewImage) previewImage.classList.add('d-none');
                            if (previewEmpty) previewEmpty.classList.remove('d-none');
                        }
                        updateLivePreview();
                    });
                }

                // Video upload preview
                const videoFileInput = document.getElementById('slider-video-file-input');
                if (videoFileInput) {
                    videoFileInput.addEventListener('change', function (event) {
                        const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
                        if (file) {
                            const objectUrl = URL.createObjectURL(file);
                            if (liveCardVideo) {
                                liveCardVideo.src = objectUrl;
                                liveCardVideo.classList.remove('d-none');
                                liveCardVideo.play().catch(() => undefined);
                            }
                        }
                    });
                }

                // Global preset buttons handler
                document.querySelectorAll('[data-set-gradient]').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const val = this.getAttribute('data-set-gradient');
                        if (gradientInput) {
                            gradientInput.value = val;
                            updateLivePreview();
                        }
                    });
                });

                document.querySelectorAll('[data-set-badge-bg]').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const bg = this.getAttribute('data-set-badge-bg');
                        const color = this.getAttribute('data-set-badge-color');
                        if (badgeBgInput) badgeBgInput.value = bg;
                        if (badgeColorInput) badgeColorInput.value = color;
                        updateLivePreview();
                    });
                });

                document.querySelectorAll('[data-set-icon]').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const icon = this.getAttribute('data-set-icon');
                        if (iconInput) {
                            iconInput.value = icon;
                            updateLivePreview();
                        }
                    });
                });

                document.querySelectorAll('[data-set-video-url]').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const url = this.getAttribute('data-set-video-url');
                        if (mediaUrlInput) {
                            mediaUrlInput.value = url;
                            updateLivePreview();
                        }
                    });
                });

                // Initialize
                updatePlacementHint();
                updateMediaTypeVisibility();
                updateLivePreview();
            });
        </script>
    @endpush
@endonce

<div class="row g-3">
    {{-- Left Column: Configuration Form --}}
    <div class="col-xl-7">
        {{-- Card 1: Core Details --}}
        <div class="card card-flush shadow-sm mb-3">
            <div class="card-header bg-transparent border-bottom py-3">
                <div class="card-title d-flex align-items-center gap-2 m-0">
                    <i class="uil uil-sliders-v fs-18 text-primary"></i>
                    <h5 class="fw-bold mb-0">Slider & Campaign Settings</h5>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label fw-semibold required">Title</label>
                        <input id="slider-title" type="text" name="title" value="{{ old('title', $slider->title) }}" class="form-control @error('title') is-invalid @enderror" placeholder="e.g. Artisanal Taste Crafted Every Day" required />
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-5">
                        <label class="form-label fw-semibold required">Placement Channel</label>
                        <select id="slider-placement" name="placement" class="form-select @error('placement') is-invalid @enderror" required>
                            @php($placements = [
                                'second_screen' => '📺 Second Screen (Customer Display)',
                                'Mobile' => '📱 Mobile App Banner',
                                'Website' => '💻 Website Storefront',
                                'All' => '🌐 All Channels (Universal)'
                            ])
                            @foreach ($placements as $val => $label)
                                <option value="{{ $val }}" @selected(old('placement', $slider->placement ?: 'second_screen') === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('placement')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Subtitle / Body Copy</label>
                        <input id="slider-subtitle" type="text" name="subtitle" value="{{ old('subtitle', $slider->subtitle) }}" class="form-control @error('subtitle') is-invalid @enderror" placeholder="e.g. Watch our fresh daily roasts, handcrafted drinks, and oven-fresh pastries." />
                        @error('subtitle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-5">
                        <label class="form-label fw-semibold required">Media / Display Type</label>
                        <select id="slider-media-type" name="media_type" class="form-select @error('media_type') is-invalid @enderror">
                            @php($mediaTypes = [
                                'gradient' => '🎨 Rich Gradient Backdrop',
                                'video' => '🎬 Looping Video Reel (MP4)',
                                'image' => '🖼️ Graphic / Photo Upload'
                            ])
                            @foreach ($mediaTypes as $val => $label)
                                <option value="{{ $val }}" @selected(old('media_type', $slider->media_type ?: ($slider->media_url ? 'video' : ($slider->image_id ? 'image' : 'gradient'))) === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('media_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold required">Status</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror">
                            @foreach (['Active', 'Inactive'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $slider->status ?: 'Active') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Sort Order</label>
                        <input type="number" min="0" step="1" name="sort_order" value="{{ old('sort_order', $slider->sort_order ?? 0) }}" class="form-control @error('sort_order') is-invalid @enderror" />
                        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Target / Action URL (Optional)</label>
                        <input type="url" name="target_url" value="{{ old('target_url', $slider->target_url) }}" class="form-control @error('target_url') is-invalid @enderror" placeholder="https://yourstore.com/offers" />
                        @error('target_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted fs-12 fw-medium">Placement Guidance</label>
                        <input id="slider-recommended-dimensions" type="text" class="form-control form-control-sm bg-light text-muted" value="{{ old('recommended_dimensions', $slider->recommended_dimensions) }}" readonly />
                        <div id="slider-image-hint" class="form-text fs-12 text-muted mt-1">{{ $slider->recommended_dimensions ?: 'Select a placement to review dimension recommendations.' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: Customer Display & Promotional Card Styling --}}
        <div class="card card-flush shadow-sm mb-3">
            <div class="card-header bg-transparent border-bottom py-3">
                <div class="card-title d-flex align-items-center gap-2 m-0">
                    <i class="uil uil-palette fs-18 text-warning"></i>
                    <h5 class="fw-bold mb-0">Promotional Badge & Callout Styling</h5>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    {{-- Badge Text & Icon --}}
                    <div class="col-md-7">
                        <label class="form-label fw-semibold">Badge Label</label>
                        <input id="slider-badge" type="text" name="badge" value="{{ old('badge', $slider->badge ?: '🎬 STORE SHOWCASE') }}" class="form-control @error('badge') is-invalid @enderror" placeholder="e.g. ☕ BREAKFAST COMBO" />
                        <div class="form-text fs-12">Displayed as a pill badge at the top of the promo card.</div>
                        @error('badge')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Icon Class</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i id="icon-preview-affix" class="{{ old('icon', $slider->icon ?: 'ri-video-line') }}"></i></span>
                            <input id="slider-icon" type="text" name="icon" value="{{ old('icon', $slider->icon ?: 'ri-video-line') }}" class="form-control @error('icon') is-invalid @enderror" placeholder="e.g. ri-cup-line" />
                        </div>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            @php($quickIcons = [
                                'ri-video-line' => '🎬 Video',
                                'ri-cup-line' => '☕ Coffee',
                                'ri-vip-crown-line' => '⭐ VIP',
                                'ri-flashlight-line' => '⚡ Flash',
                                'ri-gift-line' => '🎁 Gift',
                                'ri-percent-line' => '🏷️ Discount'
                            ])
                            @foreach ($quickIcons as $ic => $icLabel)
                                <button type="button" class="btn btn-sm btn-light py-0 px-1 fs-11" data-set-icon="{{ $ic }}">{{ $icLabel }}</button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Badge Colors --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Badge Background</label>
                        <input id="slider-badge-bg" type="text" name="badge_bg" value="{{ old('badge_bg', $slider->badge_bg ?: 'rgba(255, 255, 255, 0.95)') }}" class="form-control @error('badge_bg') is-invalid @enderror" placeholder="rgba(255, 255, 255, 0.95)" />
                        @error('badge_bg')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Badge Text Color</label>
                        <input id="slider-badge-color" type="text" name="badge_color" value="{{ old('badge_color', $slider->badge_color ?: '#0f172a') }}" class="form-control @error('badge_color') is-invalid @enderror" placeholder="#0f172a" />
                        @error('badge_color')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                            <span class="fs-12 text-muted me-1">Badge Presets:</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0.5 px-2 fs-11" data-set-badge-bg="rgba(255, 255, 255, 0.95)" data-set-badge-color="#0f172a">⚪ White</button>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0.5 px-2 fs-11" data-set-badge-bg="rgba(238, 242, 255, 0.95)" data-set-badge-color="#4f46e5">🟣 Indigo</button>
                            <button type="button" class="btn btn-sm btn-outline-success py-0.5 px-2 fs-11" data-set-badge-bg="rgba(236, 253, 245, 0.95)" data-set-badge-color="#059669">🟢 Emerald</button>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0.5 px-2 fs-11" data-set-badge-bg="rgba(253, 242, 248, 0.95)" data-set-badge-color="#db2777">🌸 Pink</button>
                        </div>
                    </div>

                    {{-- Discount Highlight & Tag --}}
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Discount / Offer Callout</label>
                        <input id="slider-discount" type="text" name="discount" value="{{ old('discount', $slider->discount ?: 'FRESH & ORGANIC') }}" class="form-control @error('discount') is-invalid @enderror" placeholder="e.g. $4.50 ONLY, 2X POINTS, 50% OFF" />
                        @error('discount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-7">
                        <label class="form-label fw-semibold">Bottom Tag / Schedule Note</label>
                        <input id="slider-tag" type="text" name="tag" value="{{ old('tag', $slider->tag ?: 'Live Store Reel • Watch fresh daily specials') }}" class="form-control @error('tag') is-invalid @enderror" placeholder="e.g. Save 25% Everyday until 11:00 AM" />
                        @error('tag')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Right Column: Media Source & Live Interactive Preview --}}
    <div class="col-xl-5">
        {{-- Card 3: Media Source Config --}}
        <div class="card card-flush shadow-sm mb-3">
            <div class="card-header bg-transparent border-bottom py-3">
                <div class="card-title d-flex align-items-center gap-2 m-0">
                    <i class="uil uil-image-v fs-18 text-info"></i>
                    <h5 class="fw-bold mb-0">Media Asset</h5>
                </div>
            </div>
            <div class="card-body">
                {{-- Video Asset Section --}}
                <div id="media-section-video" class="mb-3">
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="form-label fw-semibold mb-0">Upload Video File</label>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-11">
                                <i class="uil uil-cloud-upload me-1"></i>MinIO S3
                            </span>
                        </div>
                        <input id="slider-video-file-input" type="file" name="video_file" accept=".mp4,.webm,.mov,.m4v,video/mp4,video/webm,video/quicktime" class="form-control @error('video_file') is-invalid @enderror" />
                        <div class="form-text fs-11 text-muted mt-1">Directly uploaded to MinIO storage bucket (MP4, WebM, MOV up to 50MB).</div>
                        @error('video_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold">Or Specify Video URL</label>
                        <div class="input-group mb-2">
                            <input id="slider-media-url" type="text" name="media_url" value="{{ old('media_url', $slider->media_url ?: '/uploads/slider/videos/store-promo.mp4') }}" class="form-control @error('media_url') is-invalid @enderror" placeholder="/uploads/slider/videos/... or https://..." />
                        </div>
                        <div class="d-flex flex-wrap gap-1.5">
                            <button type="button" class="btn btn-sm btn-outline-primary fs-11 py-1" data-set-video-url="/uploads/slider/videos/store-promo.mp4">
                                <i class="uil uil-cloud me-1"></i>MinIO Video Reel
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-dark fs-11 py-1" data-set-video-url="/videos/store-promo.mp4">
                                <i class="uil uil-film me-1"></i>Local App Video
                            </button>
                        </div>
                    </div>
                    <div class="form-text fs-12 mt-1">Video streams seamlessly to connected 2nd screen kiosks and mobile apps.</div>
                </div>

                {{-- Gradient Backdrop Section --}}
                <div id="media-section-gradient" class="mb-3">
                    <label class="form-label fw-semibold">CSS Gradient</label>
                    <input id="slider-gradient" type="text" name="gradient" value="{{ old('gradient', $slider->gradient ?: 'linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%)') }}" class="form-control mb-2 @error('gradient') is-invalid @enderror" />
                    <div class="d-flex flex-wrap gap-1.5 mb-2">
                        <button type="button" class="btn btn-sm text-white py-1 px-2 fs-11 rounded" style="background: linear-gradient(135deg, #0f172a, #334155)" data-set-gradient="linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%)">🌑 Slate</button>
                        <button type="button" class="btn btn-sm text-white py-1 px-2 fs-11 rounded" style="background: linear-gradient(135deg, #1e1b4b, #4338ca)" data-set-gradient="linear-gradient(135deg, #1e1b4b 0%, #312e81 45%, #4338ca 100%)">🔮 Indigo</button>
                        <button type="button" class="btn btn-sm text-white py-1 px-2 fs-11 rounded" style="background: linear-gradient(135deg, #064e3b, #047857)" data-set-gradient="linear-gradient(135deg, #064e3b 0%, #065f46 50%, #047857 100%)">🌿 Emerald</button>
                        <button type="button" class="btn btn-sm text-white py-1 px-2 fs-11 rounded" style="background: linear-gradient(135deg, #701a75, #9d174d)" data-set-gradient="linear-gradient(135deg, #701a75 0%, #86198f 50%, #9d174d 100%)">🌺 Berry</button>
                        <button type="button" class="btn btn-sm text-white py-1 px-2 fs-11 rounded" style="background: linear-gradient(135deg, #7c2d12, #c2410c)" data-set-gradient="linear-gradient(135deg, #7c2d12 0%, #9a3412 50%, #c2410c 100%)">🌅 Amber</button>
                    </div>
                </div>

                {{-- Image File Section --}}
                <div id="media-section-image" class="mb-3 d-none">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label class="form-label fw-semibold mb-0">Upload Image</label>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-11">
                            <i class="uil uil-cloud-upload me-1"></i>MinIO S3
                        </span>
                    </div>
                    <input id="slider-image-input" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control @error('image') is-invalid @enderror" />
                    <div class="form-text fs-11 text-muted mt-1">Saved directly to your MinIO S3 storage disk.</div>
                    @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    
                    <div id="slider-image-preview-wrapper" class="border rounded overflow-hidden mt-2 d-none">
                        <img id="slider-image-preview" src="" alt="Selected slider preview" class="img-fluid w-100 d-none" style="max-height: 180px; object-fit: cover;">
                        <div id="slider-image-preview-empty" class="p-3 text-muted text-center fs-12 d-none">No file selected</div>
                    </div>
                    @if ($slider->image_url)
                        <div class="border rounded overflow-hidden mt-2">
                            <img src="{{ $slider->image_url }}" alt="Current Slider Image" class="img-fluid w-100" style="max-height: 180px; object-fit: cover;">
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Card 4: Live Interactive Customer Display Preview --}}
        <div class="card card-flush shadow-sm">
            <div class="card-header bg-transparent border-bottom py-2.5 d-flex align-items-center justify-content-between">
                <div class="card-title d-flex align-items-center gap-1.5 m-0">
                    <i class="uil uil-eye fs-16 text-success"></i>
                    <h6 class="fw-bold mb-0">Live Customer Display Preview</h6>
                </div>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-11">Real-Time</span>
            </div>
            <div class="card-body p-3">
                <div class="border rounded-4 overflow-hidden shadow-sm position-relative" style="min-height: 280px; background: #0f172a;">
                    {{-- Preview Background Container --}}
                    <div id="live-preview-bg" class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%); transition: all 0.3s ease;">
                        {{-- Embedded Video Preview --}}
                        <video id="live-preview-video" class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover d-none" autoplay loop muted playsinline style="object-fit: cover; opacity: 0.65;"></video>
                        {{-- Dark Vignette Gradient --}}
                        <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(to top, rgba(15, 23, 42, 0.95) 0%, rgba(15, 23, 42, 0.4) 60%, rgba(15, 23, 42, 0.2) 100%);"></div>
                    </div>

                    {{-- Card Foreground Elements --}}
                    <div class="position-relative p-3.5 d-flex flex-column justify-content-between h-100" style="min-height: 280px; z-index: 2;">
                        {{-- Top Bar: Badge & Discount --}}
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <span id="live-preview-badge" class="badge shadow-sm rounded-pill px-2.5 py-1.5 fs-11 fw-bold text-uppercase d-inline-flex align-items-center gap-1" style="background-color: rgba(255, 255, 255, 0.95); color: #0f172a;">
                                <i id="live-preview-badge-icon" class="ri-video-line"></i>
                                <span id="live-preview-badge-text">🎬 STORE SHOWCASE</span>
                            </span>

                            <span id="live-preview-discount" class="badge bg-white text-dark shadow-sm rounded-pill px-2.5 py-1 fs-11 fw-black text-uppercase border">
                                FRESH & ORGANIC
                            </span>
                        </div>

                        {{-- Middle/Bottom: Title, Subtitle, Tag --}}
                        <div class="mt-auto text-white">
                            <h5 id="live-preview-title" class="fw-bold mb-1 fs-16 text-white text-truncate-2" style="text-shadow: 0 1px 3px rgba(0,0,0,0.5);">Artisanal Taste Crafted Every Day</h5>
                            <p id="live-preview-subtitle" class="fs-12 text-white-50 mb-2.5 text-truncate-2" style="line-height: 1.4;">Watch our fresh daily roasts, handcrafted drinks, and oven-fresh pastries in action.</p>
                            
                            <div class="pt-2 border-top border-white border-opacity-10 d-flex align-items-center justify-content-between">
                                <span id="live-preview-tag" class="fs-11 text-white-50 text-truncate">
                                    Live Store Reel • Watch fresh daily specials
                                </span>
                                <span class="badge bg-white bg-opacity-15 text-white rounded-pill fs-10 px-2 py-0.5">Live Kiosk</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-center text-muted fs-11 mt-2">Preview illustrates layout on Customer Display and Tablet POS.</div>
            </div>
        </div>
    </div>
</div>
