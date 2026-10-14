<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\asset_loan.php';
$lines = file($file);

for ($i = 1000; $i < count($lines); $i++) {
    if (strpos($lines[$i], '<p style="margin-bottom:90px;color:#334155;">Peminjam,</p>') !== false) {
        // Line $i+1 is the signature block for Peminjam
        $lines[$i+1] = "        . '        <div style=\"font-weight:bold;color:#0f172a;text-transform:uppercase;text-decoration:underline;\">( ' . e(trim(\$loan['borrower_name']) !== '' ? \$loan['borrower_name'] : '....................................') . ' )</div>'\n";
    }
    if (strpos($lines[$i], '<p style="margin-bottom:90px;color:#334155;">Pemberi,</p>') !== false) {
        $lines[$i] = "        . '        <p style=\"margin-bottom:90px;color:#334155;\">Pemberi / Petugas,</p>'\n";
        // Line $i+1 is the signature block for Pemberi
        $lines[$i+1] = "        . '        <div style=\"font-weight:bold;color:#0f172a;text-transform:uppercase;text-decoration:underline;\">( ' . e(trim(\$loan['officer_name'] ?? '') !== '' ? \$loan['officer_name'] : '....................................') . ' )</div>'\n";
        break;
    }
}

file_put_contents($file, implode("", $lines));
echo "Signatures fixed!\n";
?>
