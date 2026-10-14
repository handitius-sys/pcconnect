<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\corrective.php';
$content = file_get_contents($file);

$oldLogic = '        $partCost = (float)($_POST[''part_cost''] ?? 0);

        if ($repairCost > 0 || $solutionDetails !== '''' || $partNewName !== '''') {
            $repStmt = $pdo->prepare("INSERT INTO corrective_repairs (
                ticket_id, technician_name, action_type, solution_details, repair_cost
            ) VALUES (?, ?, ''corrective'', ?, ?)");
            $repStmt->execute([
                $ticketId, $user[''name''], $solutionDetails ?: ''Pekerjaan perbaikan saat tiket dibuat.'', $repairCost
            ]);';

$newLogic = '        $partCost = (float)($_POST[''part_cost''] ?? 0);

        $receiptPath = null;
        if (!empty($_FILES[''receipt_image''][''tmp_name'']) && is_uploaded_file($_FILES[''receipt_image''][''tmp_name''])) {
            $uploadDir = __DIR__ . ''/../../public/uploads/receipts'';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $ext = strtolower(pathinfo((string)$_FILES[''receipt_image''][''name''], PATHINFO_EXTENSION));
            if (in_array($ext, [''jpg'', ''jpeg'', ''png'', ''webp''], true)) {
                $filename = ''receipt_'' . date(''Ymd_His'') . ''_'' . bin2hex(random_bytes(4)) . ''.'' . $ext;
                if (move_uploaded_file($_FILES[''receipt_image''][''tmp_name''], $uploadDir . ''/'' . $filename)) {
                    $receiptPath = ''uploads/receipts/'' . $filename;
                }
            }
        }

        if ($repairCost > 0 || $solutionDetails !== '''' || $partNewName !== '''' || $receiptPath !== null) {
            $repStmt = $pdo->prepare("INSERT INTO corrective_repairs (
                ticket_id, technician_name, action_type, solution_details, repair_cost, receipt_image_path
            ) VALUES (?, ?, ''corrective'', ?, ?, ?)");
            $repStmt->execute([
                $ticketId, $user[''name''], $solutionDetails ?: ''Pekerjaan perbaikan saat tiket dibuat.'', $repairCost, $receiptPath
            ]);';

$content = str_replace($oldLogic, $newLogic, $content);

$oldHtml = '                <div class="grid two" style="margin-bottom:12px;">
                    <label>Nama Sparepart Baru
                        <input name="part_new_name" placeholder="Misal: RAM 8GB DDR4 / Ban Bridgestone / Aki GS">
                    </label>
                    <label>Serial Number / Spesifikasi Part
                        <input name="part_new_serial" placeholder="Misal: SN12345 / 195/65 R15">
                    </label>
                </div>

                <label>Catatan Kerja Lainnya';

$newHtml = '                <div class="grid two" style="margin-bottom:12px;">
                    <label>Nama Sparepart Baru
                        <input name="part_new_name" placeholder="Misal: RAM 8GB DDR4 / Ban Bridgestone / Aki GS">
                    </label>
                    <label>Serial Number / Spesifikasi Part
                        <input name="part_new_serial" placeholder="Misal: SN12345 / 195/65 R15">
                    </label>
                </div>

                <div style="margin-bottom:12px;">
                    <label>Foto / Dokumen Tagihan / Kwitansi (Opsional)
                        <input type="file" name="receipt_image" accept="image/*" capture="environment">
                    </label>
                </div>

                <label>Catatan Kerja Lainnya';

$content = str_replace($oldHtml, $newHtml, $content);

file_put_contents($file, $content);
echo "Replaced ticket create form.\n";
?>
