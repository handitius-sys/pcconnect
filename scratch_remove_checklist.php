<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\field_service.php';
$lines = file($file);

// 1. Remove HTML
for ($i = 0; $i < count($lines); $i++) {
    if (strpos($lines[$i], '// Ambil checklist tindakan Job Desk jika ada') !== false) {
        $start = $i - 1; // get the <?php line
        $end = 0;
        for ($j = $i; $j < $i + 30; $j++) {
            if (strpos($lines[$j], '<?php endif; ?>') !== false) {
                $end = $j;
                break;
            }
        }
        if ($end > 0) {
            array_splice($lines, $start, $end - $start + 1);
        }
        break;
    }
}

// 2. Remove POST logic
for ($i = 0; $i < count($lines); $i++) {
    if (strpos($lines[$i], '// Save requested actions if checked') !== false) {
        $start = $i;
        $end = 0;
        for ($j = $i; $j < $i + 20; $j++) {
            if (strpos($lines[$j], '        }') !== false && strpos($lines[$j-1], '}') !== false) {
                $end = $j;
                break;
            }
        }
        if ($end > 0) {
            array_splice($lines, $start, $end - $start + 1);
        }
        break;
    }
}

file_put_contents($file, implode("", $lines));
echo "Removed checklist from mobile service.\n";
?>
