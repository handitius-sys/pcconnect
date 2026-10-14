<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\field_service.php';
$lines = file($file);

for ($i = 0; $i < count($lines); $i++) {
    if (strpos($lines[$i], '// 2. Simpan Tindakan Reparasi') !== false) {
        $inject = <<<'EOD'
        $receiptPath = null;
        if (!empty($_FILES['receipt_image']['tmp_name']) && is_uploaded_file($_FILES['receipt_image']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../../public/uploads/receipts';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $ext = strtolower(pathinfo((string)$_FILES['receipt_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $filename = 'receipt_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $uploadDir . '/' . $filename)) {
                    $receiptPath = 'uploads/receipts/' . $filename;
                }
            }
        }

EOD;
        array_splice($lines, $i, 0, [$inject]);
        
        for ($j = $i + 1; $j < $i + 30; $j++) {
            if (strpos($lines[$j], 'repair_cost, photo_after') !== false) {
                $lines[$j] = str_replace('repair_cost, photo_after', 'repair_cost, photo_after, receipt_image_path', $lines[$j]);
            }
            if (strpos($lines[$j], ') VALUES (?, ?, ?, ?, ?, ?, ?)') !== false) {
                $lines[$j] = str_replace('?, ?)', '?, ?, ?)', $lines[$j]);
            }
            if (strpos($lines[$j], '], $repairCost, $photoAfter') !== false) {
                $lines[$j] = str_replace('$photoAfter', '$photoAfter, $receiptPath', $lines[$j]);
                break;
            }
        }
        break;
    }
}

file_put_contents($file, implode("", $lines));
echo "Successfully spliced logic in field_service.php!\n";
?>
