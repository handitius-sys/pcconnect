<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\asset_loan.php';
$lines = file($file);

for ($i = 1000; $i < count($lines); $i++) {
    if (strpos($lines[$i], '<p style="margin-bottom:90px;color:#334155;">Peminjam,</p>') !== false) {
        $lines[$i+1] = "        . '        <div style=\"font-weight:bold;color:#0f172a;\">( .................................... )</div>'\n";
    }
    if (strpos($lines[$i], '<p style="margin-bottom:90px;color:#334155;">Pemberi / Petugas,</p>') !== false) {
        $lines[$i+1] = "        . '        <div style=\"font-weight:bold;color:#0f172a;\">( .................................... )</div>'\n";
        break;
    }
}

file_put_contents($file, implode("", $lines));
echo "Signatures reverted to dotted lines!\n";
?>
