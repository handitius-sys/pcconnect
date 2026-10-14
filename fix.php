<?php
$file = 'app/modules/maintenance.php';
$content = file_get_contents($file);

$content = str_replace(
    'e(\\) .',
    'e($startDate) .',
    $content
);
$content = str_replace(
    'e(\\) .',
    'e($endDate) .',
    $content
); // wait, both were e(\) so I must be careful.

// actually I'll just use preg_replace
$content = preg_replace(
    '/name="start_date" value="\' \. e\(\\\\\) \. \'"/',
    'name="start_date" value="\' . e($startDate) . \'"',
    $content
);
$content = preg_replace(
    '/name="end_date" value="\' \. e\(\\\\\) \. \'"/',
    'name="end_date" value="\' . e($endDate) . \'"',
    $content
);
file_put_contents($file, $content);
