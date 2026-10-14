<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\asset_loan.php';
$lines = file($file);

for ($i = 0; $i < count($lines); $i++) {
    // 1. Details Table
    if (strpos($lines[$i], '<table style="width:100%;margin-bottom:30px;font-size:15px;line-height:1.6;">') !== false) {
        $lines[$i] = "          . '  <div style=\"margin-bottom:40px;font-size:15px;line-height:2;color:#0f172a;\">'\n";
        $lines[$i+1] = "          . '    <div style=\"display:flex;\"><div style=\"width:200px;font-weight:600;color:#334155;\">Company</div><div>: ' . e(\$companyName) . '</div></div>'\n";
        $lines[$i+2] = "          . '    <div style=\"display:flex;\"><div style=\"width:200px;font-weight:600;color:#334155;\">Peminjam</div><div>: ' . e(\$loan['borrower_name'] . (\$loan['borrower_nik'] ? ' (' . \$loan['borrower_nik'] . ')' : '')) . '</div></div>'\n";
        $lines[$i+3] = "          . '    <div style=\"display:flex;\"><div style=\"width:200px;font-weight:600;color:#334155;\">Tanggal Peminjam</div><div>: ' . e(date('d F Y', strtotime(\$loan['loan_date']))) . '</div></div>'\n";
        $lines[$i+4] = "          . '    <div style=\"display:flex;\"><div style=\"width:200px;font-weight:600;color:#334155;\">Estimasi Pengembalian</div><div>: ' . (!empty(\$loan['expected_return_date']) ? e(date('d F Y', strtotime(\$loan['expected_return_date']))) : '-') . '</div></div>'\n";
        $lines[$i+5] = "          . '  </div>'\n";
    }
    
    // 2. Items Table
    if (strpos($lines[$i], '<table style="width:100%;border-collapse:collapse;margin-bottom:50px;font-size:14px;">') !== false) {
        $lines[$i] = "          . '  <table style=\"width:100%;border-collapse:collapse;margin-bottom:60px;font-size:14px;table-layout:fixed;border:1px solid #cbd5e1;\">'\n";
    }
    if (strpos($lines[$i], '<th style="border:1px solid #cbd5e1;padding:12px 8px;text-align:center;width:50px;">No</th>') !== false) {
        $lines[$i] = "          . '        <th style=\"border:1px solid #cbd5e1;padding:12px 8px;text-align:center;width:8%;\">No</th>'\n";
        $lines[$i+1] = "          . '        <th style=\"border:1px solid #cbd5e1;padding:12px 15px;text-align:left;width:35%;\">Kode Aset yang di Pinjam</th>'\n";
        $lines[$i+2] = "          . '        <th style=\"border:1px solid #cbd5e1;padding:12px 15px;text-align:left;width:57%;\">Nama Barang</th>'\n";
    }
    
    // 3. Signatures Table
    if (strpos($lines[$i], '<table style="width:100%;text-align:center;margin-top:20px;font-size:15px;">') !== false) {
        $lines[$i]   = "          . '  <div style=\"display:flex;justify-content:space-between;text-align:center;margin-top:40px;font-size:15px;\">'\n";
        $lines[$i+1] = "          . '    <div style=\"width:45%;\">'\n";
        $lines[$i+2] = "          . '      <p style=\"margin:0 0 90px 0;color:#334155;\">Peminjam,</p>'\n";
        $lines[$i+3] = "          . '      <div style=\"font-weight:bold;color:#0f172a;\">( .................................... )</div>'\n";
        $lines[$i+4] = "          . '    </div>'\n";
        $lines[$i+5] = "          . '    <div style=\"width:45%;\">'\n";
        $lines[$i+6] = "          . '      <p style=\"margin:0 0 90px 0;color:#334155;\">Pemberi / Petugas,</p>'\n";
        $lines[$i+7] = "          . '      <div style=\"font-weight:bold;color:#0f172a;\">( .................................... )</div>'\n";
        $lines[$i+8] = "          . '    </div>'\n";
        $lines[$i+9] = "          . '  </div>'\n";
    }
}
file_put_contents($file, implode("", $lines));
echo "Refactoring successful!\n";
?>
