<?php
$file = 'app/modules/maintenance.php';
$content = file_get_contents($file);

$content = preg_replace(
    '/if \(\$type === \'maintenance\'\) \{\s*\$rows = \$pdo->query\("SELECT s\.scheduled_date,/',
    "if (\$type === 'maintenance') {\n        \$startDate = trim((string)(\$_GET['start_date'] ?? ''));\n        \$endDate = trim((string)(\$_GET['end_date'] ?? ''));\n        \$where = 'WHERE 1=1';\n        \$params = [];\n        if (\$startDate !== '') {\n            \$where .= ' AND s.scheduled_date >= ?';\n            \$params[] = \$startDate;\n        }\n        if (\$endDate !== '') {\n            \$where .= ' AND s.scheduled_date <= ?';\n            \$params[] = \$endDate;\n        }\n        \$stmt = \$pdo->prepare(\"SELECT s.scheduled_date,",
    $content
);

$content = preg_replace(
    '/LEFT JOIN users u ON u\.id = s\.technician_id \s*ORDER BY s\.scheduled_date DESC"\)->fetchAll\(\);/',
    "LEFT JOIN users u ON u.id = s.technician_id \n        \$where \n        ORDER BY s.scheduled_date DESC\");\n        \$stmt->execute(\$params);\n        \$rows = \$stmt->fetchAll();",
    $content
);


file_put_contents($file, $content);
echo "Replaced: " . (strpos($content, 'WHERE 1=1') !== false ? "YES" : "NO");
