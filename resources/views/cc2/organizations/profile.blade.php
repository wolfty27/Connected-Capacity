@extends('dashboard.layout')

@section('dashboard_content')
    <div class="content-page">
        <div class="content">
            <div class="container-fluid py-4">
                <div class="page-title-box">
                    <h4 class="page-title">Provider Profile</h4>
                </div>

                @if (session('status'))
                    <div class="alert alert-success">
                        {{ session('status') }}
                    </div>
                @endif

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('cc2.organizations.profile.update') }}">
                            @csrf
                            @method('PUT')

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Organization Name</label>
                                    <input type="text" name="name" class="form-control"
                                           value="{{ old('name', $organization->name) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Organization Type</label>
                                    <select name="type" class="form-select" required>
                                        @foreach (['se_health' => 'SPO (Lead)', 'partner' => 'Partner', 'external' => 'External'] as $value => $label)
                                            <option value="{{ $value }}"
                                                @selected(old('type', $organization->type) === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Primary Contact Name</label>
                                    <input type="text" name="contact_name" class="form-control"
                                           value="{{ old('contact_name', $organization->contact_name) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Contact Email</label>
                                    <input type="email" name="contact_email" class="form-control"
                                           value="{{ old('contact_email', $organization->contact_email) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Contact Phone</label>
                                    <input type="text" name="contact_phone" class="form-control"
                                           value="{{ old('contact_phone', $organization->contact_phone) }}">
                                </div>
                            </div>

                            @php
                                $regionsValue = old('regions');
                                if (is_array($regionsValue)) {
                                    $regionsValue = implode(PHP_EOL, $regionsValue);
                                }
                                if ($regionsValue === null) {
                                    $regionsValue = implode(PHP_EOL, $organization->regions ?? []);
                                }
                            @endphp
                            <div class="mb-3">
                                <label class="form-label">Regions Served</label>
                                <textarea name="regions" class="form-control" rows="3"
                                          placeholder="Enter one region per line">{{ $regionsValue }}</textarea>
                                <small class="text-muted">Enter each LHIN / region on a separate line.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label d-block">Service Domains / Capabilities</label>
                                @foreach($capabilityOptions as $key => $label)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="capability_{{ $key }}"
                                               name="capabilities[]"
                                               value="{{ $key }}"
                                               @checked(in_array($key, old('capabilities', $organization->capabilities ?? []), true))>
                                        <label class="form-check-label" for="capability_{{ $key }}">
                                            {{ $label }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    Save Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
