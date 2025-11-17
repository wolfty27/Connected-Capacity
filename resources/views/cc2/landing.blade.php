@extends('dashboard.layout')

@section('dashboard_content')
    <div class="content-page">
        <div class="content">
            <div class="container-fluid py-4">
                <div class="alert alert-info">
                    <h4 class="alert-heading">Connected Capacity 2.1 (CC2)</h4>
                    <p>The CC2 orchestration workspace is feature-flagged. Enable <code>cc2.enabled</code> to begin
                        onboarding SPO/SSPO workflows while legacy placement remains available under the Legacy section.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
