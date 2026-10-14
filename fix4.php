<?php
$file = 'app/modules/maintenance.php';
$content = file_get_contents($file);

$find = "\$pcFilter = trim(\$_GET['pc_id'] ?? '');\n    \$techFilter = (int)(\$_GET['technician_id'] ?? 0);";
$replace = "\$pcFilter = trim(\$_GET['pc_id'] ?? '');\n    \$techFilter = (int)(\$_GET['technician_id'] ?? 0);\n    \$startDate = trim((string)(\$_GET['start_date'] ?? ''));\n    \$endDate = trim((string)(\$_GET['end_date'] ?? ''));";

$content = str_replace($find, $replace, $content);
file_put_contents($file, $content);
echo "Done.";
