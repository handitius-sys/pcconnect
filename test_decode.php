<?php
$payload = [
    'hardware' => ['processor' => 'Intel Core i7', 'ram_gb' => 16],
    'software' => ['os_caption' => 'Windows 11'],
    'devices' => ['network' => []],
    'benchmark' => ['cpu_quick_ms' => 100],
    'startup' => ['startup_items' => []]
];

$encode = function($val) {
    if (is_array($val)) return json_encode($val, JSON_UNESCAPED_UNICODE);
    if (is_string($val) && trim($val) !== '') {
        json_decode($val);
        if (json_last_error() === JSON_ERROR_NONE) return $val;
        return json_encode(['raw' => $val], JSON_UNESCAPED_UNICODE);
    }
    return '{}';
};

$specs = $encode($payload['general_specs'] ?? $payload['hardware'] ?? null);
$device = $encode($payload['device_management'] ?? $payload['devices'] ?? null);
$startup = $encode($payload['startup_analysis'] ?? $payload['startup'] ?? null);
$software = $encode($payload['software'] ?? null);
$benchmark = $encode($payload['benchmark'] ?? null);

echo "SPECS: $specs\n";
echo "DEVICE: $device\n";
echo "STARTUP: $startup\n";
echo "SOFTWARE: $software\n";
echo "BENCHMARK: $benchmark\n";

echo "------------------------\n";
echo "DECODE TESTS:\n";
function test_decode($title, $json) {
    $data = $json ? json_decode($json, true) : null;
    echo "Title: $title\n";
    echo "Is falsy?: " . ($data ? "NO" : "YES") . "\n";
    if (is_array($data)) echo "Keys: " . implode(', ', array_keys($data)) . "\n";
}
test_decode('Spesifikasi Umum', $specs);
test_decode('Device Management', $device);
test_decode('Startup Analisa', $startup);


