<?php

use App\Http\Controllers\Api\V2\ServiceTypeController;
use App\Http\Controllers\Api\V2\CareBundleBuilderController;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

// 1. Fetch Service Types (simulating useServiceTypes)
$serviceTypeController = App::make(ServiceTypeController::class);
$request = new Request();
$request->merge(['with_category' => true]);
$serviceTypesResponse = $serviceTypeController->index($request)->getData(true);
$apiServiceTypes = $serviceTypesResponse['data'];

echo "Fetched " . count($apiServiceTypes) . " service types.\n";

// 2. Fetch Bundles (simulating fetchData)
$patient = Patient::whereHas('user', fn($q) => $q->where('name', 'Margaret Thompson'))->first();
if (!$patient) {
    die("Patient not found.\n");
}

$builderController = App::make(CareBundleBuilderController::class);
$bundlesResponse = $builderController->getBundles($patient->id)->getData(true);
$bundles = $bundlesResponse['data'];

echo "Fetched " . count($bundles) . " bundles.\n";

if (empty($bundles)) {
    die("No bundles found.\n");
}

$selectedBundle = $bundles[0]; // Simulate selecting the first bundle
echo "Selected Bundle: " . $selectedBundle['name'] . "\n";
echo "Bundle Services Count: " . count($selectedBundle['services']) . "\n";

// 3. Simulate Matching Logic
$matchedCount = 0;
$servicesWithConfig = array_map(function ($s) use ($selectedBundle, &$matchedCount) {
    // Frontend logic:
    // const bundleService = selectedBundle.services?.find(b =>
    //     b.id == s.id ||
    //     (b.code && s.code && b.code === s.code) ||
    //     (b.name && s.name && b.name === s.name)
    // );

    $bundleService = null;
    foreach ($selectedBundle['services'] as $b) {
        // Loose comparison for ID (frontend uses ==)
        if ($b['id'] == $s['id']) {
            $bundleService = $b;
            break;
        }
        if (!empty($b['code']) && !empty($s['code']) && $b['code'] === $s['code']) {
            $bundleService = $b;
            break;
        }
        if (!empty($b['name']) && !empty($s['name']) && $b['name'] === $s['name']) {
            $bundleService = $b;
            break;
        }
    }

    $isCore = !is_null($bundleService);
    if ($isCore) {
        $matchedCount++;
        // echo "Matched: " . $s['name'] . " (ID: " . $s['id'] . ")\n";
    }

    return [
        'name' => $s['name'],
        'is_core' => $isCore,
        'defaultFrequency' => $isCore ? ($bundleService['currentFrequency'] ?? 1) : 0,
    ];
}, $apiServiceTypes);

echo "Matched Services: $matchedCount\n";

// Check Clinical Services
$clinicalServices = array_filter($servicesWithConfig, function ($s) {
    // Frontend uses: s.category && s.category.toUpperCase().includes('CLINICAL')
    // But here we don't have the mapped category, so we skip that check for now
    // and just check if any matched service is clinical (we know Nursing is)
    return $s['name'] === 'Nursing (RN/RPN)';
});

foreach ($clinicalServices as $s) {
    echo "Service: " . $s['name'] . " | is_core: " . ($s['is_core'] ? 'true' : 'false') . " | defaultFrequency: " . $s['defaultFrequency'] . "\n";
}
