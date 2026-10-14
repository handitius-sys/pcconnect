<?php
$file = 'app/modules/maintenance.php';
$content = file_get_contents($file);

$find = <<<EOT
    if (\$user['role'] === 'technician') {
        \$sql .= ' AND s.technician_id = ?';
        \$params[] = \$user['id'];
    }
    \$sql .= ' ORDER BY s.scheduled_date DESC, s.id DESC';
EOT;

$replace = <<<EOT
    if (\$user['role'] === 'technician') {
        \$sql .= ' AND s.technician_id = ?';
        \$params[] = \$user['id'];
    }
    if (\$startDate !== '') {
        \$sql .= ' AND DATE(COALESCE(r.created_at, s.scheduled_date)) >= ?';
        \$params[] = \$startDate;
    }
    if (\$endDate !== '') {
        \$sql .= ' AND DATE(COALESCE(r.created_at, s.scheduled_date)) <= ?';
        \$params[] = \$endDate;
    }
    \$sql .= ' ORDER BY COALESCE(r.created_at, s.scheduled_date) DESC, s.id DESC';
EOT;

$content = str_replace($find, $replace, $content);

file_put_contents($file, $content);
echo "Replaced: " . (strpos($content, 'DATE(COALESCE(r.created_at') !== false ? "YES" : "NO");
