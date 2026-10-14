<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\asset_loan.php';
$lines = file($file);

for ($i = 0; $i < count($lines); $i++) {
    if (strpos($lines[$i], 'Kode Aset yang di Pinjam</th>') !== false) {
        $lines[$i] = "          . '        <th style=\"border:1px solid #cbd5e1;padding:12px 15px;text-align:left;width:35%;\">Kode Aset yang di Pinjam</th>'\n";
    }
    if (strpos($lines[$i], 'Nama Barang</th>') !== false) {
        $lines[$i] = "          . '        <th style=\"border:1px solid #cbd5e1;padding:12px 15px;text-align:left;width:57%;\">Nama Barang</th>'\n";
    }
}

file_put_contents($file, implode("", $lines));
?>
