<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require 'app/lib/bootstrap.php';
    require 'app/lib/schema.php';

    $pdo = Database::pdo();
    $pdo->exec('ALTER TABLE asset_type_identifiers ADD COLUMN is_permanent TINYINT(1) NOT NULL DEFAULT 0 AFTER is_searchable');
    echo "Column is_permanent added successfully.\n";
} catch (Throwable $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column is_permanent already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

