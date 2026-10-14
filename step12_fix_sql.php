<?php
$file = 'app/modules/corrective.php';
$content = file_get_contents($file);

// Fix the status='active' query
$content = str_replace("status='active'", "is_active=1", $content);

file_put_contents($file, $content);
echo "Fixed is_active SQL errors.\n";
