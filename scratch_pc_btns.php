<?php
$f = __DIR__ . '/app/modules/pc.php';
$c = file_get_contents($f);

// 1. Remove Upload JSON Analisa from Data PC (line 1063 approx)
$c = str_replace('<a class="btn" href="\' . route_url(\'upload_analysis\') . \'">Upload JSON Analisa</a>', '', $c);

// 2. Change Download Runner .CMD to Download PcNalisa-run-PcId
$c = str_replace('📥 Download Runner .CMD', '📥 Download PcNalisa-run-\' . e($pcId) . \'', $c);

file_put_contents($f, $c);
echo "Updated pc.php successfully\n";

