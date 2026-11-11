<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/lib/db.php';
require_once __DIR__ . '/../app/lib/auth.php';
require_once __DIR__ . '/../app/lib/bootstrap.php';
require_once __DIR__ . '/../app/modules/maintenance.php';

// Mock logged-in admin user
$_SESSION['user'] = [
    'id' => 1,
    'username' => 'admin',
    'role' => 'admin',
    'name' => 'Administrator'
];

// Run export directly to file
ob_start();
try {
    export_excel($pdo, 'asset_items');
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
$data = ob_get_clean();
echo "OUTPUT SIZE: " . strlen($data) . "\n";
file_put_contents('/tmp/direct_export.xlsx', $data);
echo "FIRST 50 BYTES: " . bin2hex(substr($data, 0, 50)) . "\n";
