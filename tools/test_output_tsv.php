<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../app/lib/bootstrap.php';
require_once __DIR__ . '/../app/modules/maintenance.php';

echo "START TEST\n";
$rows = [
    ['A' => 'Item 1', 'B' => '100', 'C' => '2026-01-01'],
    ['A' => 'Item 2', 'B' => '200', 'C' => '2026-02-02'],
];
output_tsv(['Col A', 'Col B', 'Col C'], $rows, 'Test');
echo "\nDONE OUTPUT_TSV\n";
