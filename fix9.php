<?php
$file = 'app/modules/maintenance.php';
$content = file_get_contents($file);

$content = preg_replace(
    '/\$sql \.= \' ORDER BY s\.scheduled_date DESC, s\.id DESC\';/',
    "    if (\$startDate !== '') {\n        \$sql .= ' AND DATE(COALESCE(r.created_at, s.scheduled_date)) >= ?';\n        \$params[] = \$startDate;\n    }\n    if (\$endDate !== '') {\n        \$sql .= ' AND DATE(COALESCE(r.created_at, s.scheduled_date)) <= ?';\n        \$params[] = \$endDate;\n    }\n    \$sql .= ' ORDER BY COALESCE(r.created_at, s.scheduled_date) DESC, s.id DESC';",
    $content
);

file_put_contents($file, $content);
echo "Replaced: " . (strpos($content, 'DATE(COALESCE(r.created_at') !== false ? "YES" : "NO");
