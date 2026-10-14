<?php
$file = 'app/modules/maintenance.php';
$content = file_get_contents($file);

$content = preg_replace(
    '/\$pcFilter = trim\(\$_GET\[\'pc_id\'\] \?\? \'\'\);\s*\$techFilter = \(int\)\(\$_GET\[\'technician_id\'\] \?\? 0\);/m',
    "\$pcFilter = trim(\$_GET['pc_id'] ?? '');\n    \$techFilter = (int)(\$_GET['technician_id'] ?? 0);\n    \$startDate = trim((string)(\$_GET['start_date'] ?? ''));\n    \$endDate = trim((string)(\$_GET['end_date'] ?? ''));",
    $content
);

file_put_contents($file, $content);
echo "Done.";
