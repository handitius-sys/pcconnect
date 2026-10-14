<?php
$file = 'app/modules/maintenance.php';
$content = file_get_contents($file);

$find = <<<EOT
    \$pcFilter = trim(\$_GET['pc_id'] ?? '');
    \$techFilter = (int)(\$_GET['technician_id'] ?? 0);
EOT;
$replace = <<<EOT
    \$pcFilter = trim(\$_GET['pc_id'] ?? '');
    \$techFilter = (int)(\$_GET['technician_id'] ?? 0);
    \$startDate = trim((string)(\$_GET['start_date'] ?? ''));
    \$endDate = trim((string)(\$_GET['end_date'] ?? ''));
EOT;
$content = str_replace($find, $replace, $content);

file_put_contents($file, $content);
echo "Replaced: " . (strpos($content, '$startDate = trim') !== false ? "YES" : "NO");
