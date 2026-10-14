<?php
$cfg = require __DIR__ . '/config/config.php';
$dsn = 'mysql:host='.$cfg['db_host'].';port='.$cfg['db_port'].';dbname='.$cfg['db_name'].';charset=utf8mb4';
try {
    $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("ALTER TABLE asset_movements ADD COLUMN IF NOT EXISTS from_location_label VARCHAR(180) NULL AFTER to_parent_asset_item_id");
    $pdo->exec("ALTER TABLE asset_movements ADD COLUMN IF NOT EXISTS to_location_label VARCHAR(180) NULL AFTER from_location_label");
    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
