
$controller = new \App\Http\Controllers\Api\V2\ServiceTypeController();
$request = \Illuminate\Http\Request::create('/api/v2/service-types', 'GET', ['active' => 'true']);
try {
    $response = $controller->index($request);
    $content = $response->getContent();
    $data = json_decode($content, true);
    echo "Count: " . count($data['data']) . "\n";
    foreach (array_slice($data['data'], 0, 5) as $item) {
        echo "ID: " . $item['id'] . ", Name: " . $item['name'] . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
