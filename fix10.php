<?php
$file = 'app/modules/maintenance.php';
$content = file_get_contents($file);

$find = "function maintenance_status_rows(PDO \$pdo, bool \$printerReady, string \$assetTypeFilter, string \$statusFilter, int \$techFilter, array \$user): array\n{";
$replace = "function maintenance_status_rows(PDO \$pdo, bool \$printerReady, string \$assetTypeFilter, string \$statusFilter, int \$techFilter, array \$user): array\n{\n    \$startDate = trim((string)(\$_GET['start_date'] ?? ''));\n    \$endDate = trim((string)(\$_GET['end_date'] ?? ''));";

$content = str_replace($find, $replace, $content);
file_put_contents($file, $content);
echo "Replaced: " . (strpos($content, '$startDate = trim') !== false ? "YES" : "NO");
