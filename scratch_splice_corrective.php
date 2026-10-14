<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\corrective.php';
$lines = file($file);

// 1. ticket_action HTML (around 1450)
for ($i = 1440; $i < 1470; $i++) {
    if (strpos($lines[$i], '<h3>Penggantian Sparepart') !== false) {
        $inject = "                <div style=\"margin-bottom:12px;\">\n                    <label>Foto / Dokumen Tagihan / Kwitansi (Opsional)\n                        <input type=\"file\" name=\"receipt_image\" accept=\"image/*\" capture=\"environment\">\n                    </label>\n                </div>\n\n";
        array_splice($lines, $i, 0, [$inject]);
        break;
    }
}

// 2. ticket_create HTML (around 1039)
for ($i = 1000; $i < 1050; $i++) {
    if (strpos($lines[$i], '<label>Catatan Kerja Lainnya') !== false) {
        $inject = "                <div style=\"margin-bottom:12px;\">\n                    <label>Foto / Dokumen Tagihan / Kwitansi (Opsional)\n                        <input type=\"file\" name=\"receipt_image\" accept=\"image/*\" capture=\"environment\">\n                    </label>\n                </div>\n\n";
        array_splice($lines, $i, 0, [$inject]);
        break;
    }
}

// 3. ticket_action POST (around 1585)
for ($i = 1570; $i < 1620; $i++) {
    if (strpos($lines[$i], 'if ($solution !== \'\') {') !== false) {
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
        
        // update the INSERT statement
        // we need to find the INSERT and execute blocks after this
        for ($j = $i + 1; $j < $i + 20; $j++) {
            if (strpos($lines[$j], 'vendor_name, vendor_invoice_no, repair_cost, photo_after') !== false) {
                $lines[$j] = str_replace('photo_after', 'photo_after, receipt_image_path', $lines[$j]);
            }
            if (strpos($lines[$j], ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)') !== false) {
                $lines[$j] = str_replace('?, ?, ?)', '?, ?, ?, ?)', $lines[$j]);
            }
            if (strpos($lines[$j], '$vendorName ?: null, $vendorInv ?: null, $repairCost, $photoAfter') !== false) {
                $lines[$j] = str_replace('$photoAfter', '$photoAfter, $receiptPath', $lines[$j]);
                break;
            }
        }
        break;
    }
}

// 4. ticket_create POST (around 893)
for ($i = 880; $i < 910; $i++) {
    if (strpos($lines[$i], 'if ($repairCost > 0 || $solutionDetails !== \'\' || $partNewName !== \'\') {') !== false) {
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
        $lines[$i] = str_replace('|| $partNewName !== \'\'', '|| $partNewName !== \'\' || $receiptPath !== null', $lines[$i]);
        array_splice($lines, $i, 0, [$inject]);
        
        for ($j = $i + 1; $j < $i + 20; $j++) {
            if (strpos($lines[$j], 'ticket_id, technician_name, action_type, solution_details, repair_cost') !== false) {
                $lines[$j] = str_replace('repair_cost', 'repair_cost, receipt_image_path', $lines[$j]);
            }
            if (strpos($lines[$j], ') VALUES (?, ?, \'corrective\', ?, ?)') !== false) {
                $lines[$j] = str_replace('?, ?)', '?, ?, ?)', $lines[$j]);
            }
            if (strpos($lines[$j], '$ticketId, $user[\'name\'], $solutionDetails ?: \'Pekerjaan perbaikan saat tiket dibuat.\', $repairCost') !== false) {
                $lines[$j] = str_replace('$repairCost', '$repairCost, $receiptPath', $lines[$j]);
                break;
            }
        }
        break;
    }
}

file_put_contents($file, implode("", $lines));
echo "Successfully spliced all logic in corrective.php!\n";
?>
