<?php
$file = 'app/modules/maintenance.php';
$content = file_get_contents($file);

$content = str_replace(
    'name="end_date" value="\' . e($startDate) . \'"',
    'name="end_date" value="\' . e($endDate) . \'"',
    $content
);

file_put_contents($file, $content);
