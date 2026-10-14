<?php
$file = 'app/modules/maintenance.php';
$content = file_get_contents($file);

$content = preg_replace(
    '/(if \(\$type === \'reports\'\) \{\s*\$printerReady = printer_schema_ready\(\$pdo\);\s*\$pcFilter = [^\n]+\n\s*\$techFilter = [^\n]+\n)/',
    "$1        \$startDate = trim((string)(\$_GET['start_date'] ?? ''));\n        \$endDate = trim((string)(\$_GET['end_date'] ?? ''));\n",
    $content
);

file_put_contents($file, $content);
echo "Done regex.";
