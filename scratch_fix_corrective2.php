<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\corrective.php';
$content = file_get_contents($file);

$oldLogic = '      if ($solution !== '''') {
          $repStmt = $pdo->prepare("INSERT INTO corrective_repairs (
              ticket_id, technician_name, action_type, root_cause_analysis, solution_details, 
              vendor_name, vendor_invoice_no, repair_cost, photo_after
          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
          $repStmt->execute([
              $ticketId, $techName, $actionType, $rootCause, $solution,
              $vendorName ?: null, $vendorInv ?: null, $repairCost, $photoAfter
          ]);';

$newLogic = '      $receiptPath = null;
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

      if ($solution !== '''') {
          $repStmt = $pdo->prepare("INSERT INTO corrective_repairs (
              ticket_id, technician_name, action_type, root_cause_analysis, solution_details, 
              vendor_name, vendor_invoice_no, repair_cost, photo_after, receipt_image_path
          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
          $repStmt->execute([
              $ticketId, $techName, $actionType, $rootCause, $solution,
              $vendorName ?: null, $vendorInv ?: null, $repairCost, $photoAfter, $receiptPath
          ]);';

$content = str_replace($oldLogic, $newLogic, $content);

$oldHtml = '              <label>Biaya Service / Jasa (Rp)
                  <input type="number" name="repair_cost" value="0" placeholder="Contoh: 150000">
              </label>
          </div>

          <div id="dynamicPartsRows">';

$newHtml = '              <label>Biaya Service / Jasa (Rp)
                  <input type="number" name="repair_cost" value="0" placeholder="Contoh: 150000">
              </label>
          </div>

          <div style="margin-bottom:12px;">
              <label>Foto / Dokumen Tagihan / Kwitansi (Opsional)
                  <input type="file" name="receipt_image" accept="image/*" capture="environment">
              </label>
          </div>

          <div id="dynamicPartsRows">';

$content = str_replace($oldHtml, $newHtml, $content);

file_put_contents($file, $content);
echo "Replaced ticket action form.\n";
?>
