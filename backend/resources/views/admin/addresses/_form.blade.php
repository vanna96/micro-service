@once
    @push('styles')
        <style>
            .address-map-canvas {
                width: 100%;
                min-height: 320px;
                border-radius: 12px;
                border: 1px solid #e9e9ef;
                overflow: hidden;
                background: linear-gradient(135deg, #f6f8fc 0%, #eef3ff 100%);
            }

            .address-map-empty {
                min-height: 320px;
                display: flex;
                align-items: center;
                justify-content: center;
                text-align: center;
                color: #6c757d;
                padding: 1.5rem;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            (function () {
                function initializeAddressMap() {
                    const mapContainer = document.getElementById('address-map');
                    if (!mapContainer || !window.google || !window.google.maps) {
                        return;
                    }

                    const latInput = document.getElementById('address-latitude');
                    const lngInput = document.getElementById('address-longitude');
                    const addressInput = document.getElementById('address-line');
                    const cityInput = document.getElementById('address-city');
                    const searchInput = document.getElementById('address-google-search');

                    const defaultLat = parseFloat(latInput.value || '11.5564');
                    const defaultLng = parseFloat(lngInput.value || '104.9282');
                    const initialPosition = {
                        lat: Number.isFinite(defaultLat) ? defaultLat : 11.5564,
                        lng: Number.isFinite(defaultLng) ? defaultLng : 104.9282
                    };

                    const map = new google.maps.Map(mapContainer, {
                        center: initialPosition,
                        zoom: 14,
                        mapTypeControl: false,
                        streetViewControl: false,
                        fullscreenControl: false
                    });

                    const marker = new google.maps.Marker({
                        position: initialPosition,
                        map: map,
                        draggable: true
                    });

                    const geocoder = new google.maps.Geocoder();

                    function updateCoordinateFields(position) {
                        latInput.value = position.lat().toFixed(7);
                        lngInput.value = position.lng().toFixed(7);
                    }

                    function fillAddressFields(results) {
                        if (!Array.isArray(results) || !results.length) {
                            return;
                        }

                        const primaryResult = results[0];

                        if (primaryResult.formatted_address) {
                            addressInput.value = primaryResult.formatted_address;
                        }

                        const cityComponent = primaryResult.address_components.find((component) =>
                            component.types.includes('locality') ||
                            component.types.includes('administrative_area_level_1')
                        );

                        if (cityComponent) {
                            cityInput.value = cityComponent.long_name;
                        }
                    }

                    function reverseGeocode(position) {
                        geocoder.geocode({ location: position }, function (results, status) {
                            if (status === 'OK') {
                                fillAddressFields(results);
                            }
                        });
                    }

                    marker.addListener('dragend', function (event) {
                        updateCoordinateFields(event.latLng);
                        reverseGeocode(event.latLng);
                    });

                    map.addListener('click', function (event) {
                        marker.setPosition(event.latLng);
                        updateCoordinateFields(event.latLng);
                        reverseGeocode(event.latLng);
                    });

                    if (searchInput) {
                        const autocomplete = new google.maps.places.Autocomplete(searchInput, {
                            fields: ['formatted_address', 'geometry', 'address_components'],
                        });

                        autocomplete.addListener('place_changed', function () {
                            const place = autocomplete.getPlace();

                            if (!place.geometry || !place.geometry.location) {
                                return;
                            }

                            marker.setPosition(place.geometry.location);
                            map.setCenter(place.geometry.location);
                            map.setZoom(16);
                            updateCoordinateFields(place.geometry.location);

                            if (place.formatted_address) {
                                addressInput.value = place.formatted_address;
                            }

                            const cityComponent = (place.address_components || []).find((component) =>
                                component.types.includes('locality') ||
                                component.types.includes('administrative_area_level_1')
                            );

                            if (cityComponent) {
                                cityInput.value = cityComponent.long_name;
                            }
                        });
                    }
                }

                window.initializeAddressMap = initializeAddressMap;
            })();
        </script>
    @endpush
@endonce

<div class="row g-2">
    <div class="col-xl-8">
        <div class="card card-flush mb-10">
            <div class="card-header">
                <div class="card-title">
                    <h2>Address Details</h2>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required">Select User</label>
                        <select name="user_id" class="form-select @error('user_id') is-invalid @enderror">
                            <option value="">Select user</option>
                            @foreach ($userOptions as $userOption)
                                <option value="{{ $userOption->id }}" @selected((string) old('user_id', $address->user_id) === (string) $userOption->id)>
                                    {{ $userOption->name }} ({{ $userOption->username }})
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Label</label>
                        <input type="text" name="label" value="{{ old('label', $address->label) }}" class="form-control @error('label') is-invalid @enderror" placeholder="Home" />
                        @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Recipient Name</label>
                        <input type="text" name="recipient_name" value="{{ old('recipient_name', $address->recipient_name) }}" class="form-control @error('recipient_name') is-invalid @enderror" placeholder="Hhh Shopper" />
                        @error('recipient_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Code</label>
                        <select name="code" class="form-select @error('code') is-invalid @enderror">
                            @foreach ($dialCodeOptions as $dialCodeOption)
                                <option value="{{ $dialCodeOption }}" @selected(old('code', $address->code ?: '+855') === $dialCodeOption)>{{ $dialCodeOption }}</option>
                            @endforeach
                        </select>
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone', $address->phone) }}" class="form-control @error('phone') is-invalid @enderror" placeholder="12345678" />
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">City</label>
                        <input type="text" name="city" value="{{ old('city', $address->city) }}" class="form-control @error('city') is-invalid @enderror" placeholder="Phnom Penh" />
                        @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label required">Address Line</label>
                        <input id="address-line" type="text" name="address_line" value="{{ old('address_line', $address->address_line) }}" class="form-control @error('address_line') is-invalid @enderror" placeholder="Street 271, Boeng Keng Kang" />
                        @error('address_line')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Note</label>
                        <textarea name="note" rows="4" class="form-control @error('note') is-invalid @enderror" placeholder="Near the coffee shop">{{ old('note', $address->note) }}</textarea>
                        @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card card-flush mb-10">
            <div class="card-header">
                <div class="card-title">
                    <h2>Google Location</h2>
                </div>
            </div>
            <div class="card-body">
                @if ($googleMapsApiKey !== '')
                    <div class="mb-3">
                        <label class="form-label">Search on Google</label>
                        <input
                            id="address-google-search"
                            type="text"
                            class="form-control"
                            placeholder="Search a place or drop a pin on the map">
                    </div>

                    <div id="address-map" class="address-map-canvas"></div>
                    <div class="form-text mt-2">Use Google search, drag the pin, or click the map to save latitude and longitude.</div>
                @else
                    <div class="address-map-empty">
                        <div>
                            <div class="fw-semibold mb-2">Google Maps is not configured yet.</div>
                            <div>Add `GOOGLE_MAPS_API_KEY` to enable place search and map pinning. You can still enter coordinates manually below.</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="card card-flush">
            <div class="card-header">
                <div class="card-title">
                    <h2>Address Options</h2>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label">Latitude</label>
                        <input
                            id="address-latitude"
                            type="number"
                            step="0.0000001"
                            name="latitude"
                            value="{{ old('latitude', $address->latitude) }}"
                            class="form-control @error('latitude') is-invalid @enderror"
                            placeholder="11.5564000">
                        @error('latitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label">Longitude</label>
                        <input
                            id="address-longitude"
                            type="number"
                            step="0.0000001"
                            name="longitude"
                            value="{{ old('longitude', $address->longitude) }}"
                            class="form-control @error('longitude') is-invalid @enderror"
                            placeholder="104.9282000">
                        @error('longitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1" @checked(old('is_default', $address->is_default))>
                    <label class="form-check-label fw-semibold" for="is_default">Set as default address</label>
                </div>
                <div class="text-muted small">
                    When enabled, this address becomes the default one for the selected user.
                </div>
            </div>
        </div>
    </div>
</div>

@if ($googleMapsApiKey !== '')
    @push('scripts')
        <script
            src="https://maps.googleapis.com/maps/api/js?key={{ urlencode($googleMapsApiKey) }}&libraries=places&callback=initializeAddressMap"
            async
            defer></script>
    @endpush
@endif
