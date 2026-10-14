<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\asset_loan.php';
$lines = file($file);

$startIndex = -1;
$endIndex = -1;

for ($i = 0; $i < count($lines); $i++) {
    if (strpos($lines[$i], '// Lembar Tanda Terima') !== false) {
        $startIndex = $i + 1;
    }
    if ($startIndex !== -1 && strpos($lines[$i], '. \'</div>\'') !== false && strpos($lines[$i+1], '. \'</section>\';') !== false) {
        $endIndex = $i;
        break;
    }
}

if ($startIndex !== -1 && $endIndex !== -1) {
    $newContent = <<<'EOD'
        . '<div class="print-receipt" style="background:#fff;border:1px solid #cbd5e1;padding:20mm;width:210mm;min-height:297mm;box-sizing:border-box;margin:20px auto;box-shadow:0 10px 25px rgba(0,0,0,0.15);font-family: Arial, sans-serif;position:relative;">'
        . '  <div style="text-align:center;border-bottom:2px solid #0f172a;padding-bottom:15px;margin-bottom:30px;">'
        . '    <h2 style="margin:0;font-size:24px;color:#0f172a;letter-spacing:1px;text-transform:uppercase;">TANDA TERIMA PEMINJAMAN</h2>'
        . '    <p style="margin:8px 0 0 0;color:#475569;font-size:15px;font-weight:600;">No: ' . e($loan['loan_code']) . '</p>'
        . '  </div>'
        
        . '  <div style="margin-bottom:40px;font-size:15px;line-height:2;color:#0f172a;">'
        . '    <div style="display:flex;"><div style="width:200px;font-weight:600;color:#334155;">Company</div><div>: ' . e($companyName) . '</div></div>'
        . '    <div style="display:flex;"><div style="width:200px;font-weight:600;color:#334155;">Peminjam</div><div>: ' . e($loan['borrower_name'] . ($loan['borrower_nik'] ? ' (' . $loan['borrower_nik'] . ')' : '')) . '</div></div>'
        . '    <div style="display:flex;"><div style="width:200px;font-weight:600;color:#334155;">Tanggal Peminjam</div><div>: ' . e(date('d F Y', strtotime($loan['loan_date']))) . '</div></div>'
        . '    <div style="display:flex;"><div style="width:200px;font-weight:600;color:#334155;">Estimasi Pengembalian</div><div>: ' . (!empty($loan['expected_return_date']) ? e(date('d F Y', strtotime($loan['expected_return_date']))) : '-') . '</div></div>'
        . '  </div>'

        . '  <table style="width:100%;border-collapse:collapse;margin-bottom:60px;font-size:14px;table-layout:fixed;border:1px solid #cbd5e1;">'
        . '    <thead>'
        . '      <tr style="background:#f1f5f9;color:#0f172a;">'
        . '        <th style="border:1px solid #cbd5e1;padding:12px 8px;text-align:center;width:8%;">No</th>'
        . '        <th style="border:1px solid #cbd5e1;padding:12px 15px;text-align:left;width:35%;">Kode Aset yang Dipinjam</th>'
        . '        <th style="border:1px solid #cbd5e1;padding:12px 15px;text-align:left;width:57%;">Nama Barang</th>'
        . '      </tr>'
        . '    </thead>'
        . '    <tbody>';

    foreach ($items as $idx => $it) {
        $newContent .= "\n      <tr>"
            . "        <td style=\"border:1px solid #cbd5e1;padding:12px 8px;text-align:center;color:#0f172a;\">" . ($idx + 1) . "</td>"
            . "        <td style=\"border:1px solid #cbd5e1;padding:12px 15px;font-family:monospace;font-size:15px;color:#0369a1;font-weight:600;\">" . e($it['asset_code']) . "</td>"
            . "        <td style=\"border:1px solid #cbd5e1;padding:12px 15px;color:#0f172a;\">" . e($it['asset_name'] ?: $it['model']) . "</td>"
            . "      </tr>";
    }

    $newContent .= "\n    </tbody>"
        . "  </table>"
        
        . "  <div style=\"display:flex;justify-content:space-between;text-align:center;margin-top:20px;font-size:15px;\">"
        . "    <div style=\"width:45%;\">"
        . "      <p style=\"margin:0 0 90px 0;color:#334155;\">Peminjam,</p>"
        . "      <div style=\"font-weight:bold;color:#0f172a;\">( .................................... )</div>"
        . "    </div>"
        . "    <div style=\"width:45%;\">"
        . "      <p style=\"margin:0 0 90px 0;color:#334155;\">Pemberi / Petugas,</p>"
        . "      <div style=\"font-weight:bold;color:#0f172a;\">( .................................... )</div>"
        . "    </div>"
        . "  </div>"
        . "\n</div>\n";

    // Reconstruct lines
    $newLines = array_slice($lines, 0, $startIndex);
    $newLines[] = $newContent;
    $newLines = array_merge($newLines, array_slice($lines, $endIndex + 1));
    
    file_put_contents($file, implode("", $newLines));
    echo "Successfully refactored layout!\n";
} else {
    echo "Could not find boundaries.\n";
}
?>
