<?php
$file = 'app/modules/maintenance.php';
$content = file_get_contents($file);

$content = str_replace(
    'ORDER BY COALESCE(r.created_at, s.scheduled_date) DESC, s.id DESC',
    'ORDER BY r.created_at DESC, s.scheduled_date DESC, s.id DESC',
    $content
);

file_put_contents($file, $content);
echo "Replaced: " . (strpos($content, 'ORDER BY r.created_at DESC, s.scheduled_date DESC') !== false ? "YES" : "NO");
