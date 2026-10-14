<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\asset_loan.php';
$content = file_get_contents($file);

$oldStr = ". '  <table style=\"width:100%;margin-bottom:30px;font-size:15px;line-height:1.6;\">'
          . '    <tr><td style=\"width:200px;font-weight:bold;color:#334155;\">Company</td><td>: ' . e(\$companyName) . '</td></tr>'
          . '    <tr><td style=\"font-weight:bold;color:#334155;\">Peminjam</td><td>: ' . e(\$loan['borrower_name'] . (\$loan['borrower_nik'] ? ' (' . \$loan['borrower_nik'] . ')' : '')) . '</td></tr>'
          . '    <tr><td style=\"font-weight:bold;color:#334155;\">Tanggal Peminjam</td><td>: ' . e(date('d F Y', strtotime(\$loan['loan_date']))) . '</td></tr>'
          . '    <tr><td style=\"font-weight:bold;color:#334155;\">Estimasi Pengembalian</td><td>: ' . (!empty(\$loan['expected_return_date']) ? e(date('d F Y', strtotime(\$loan['expected_return_date']))) : '-') . '</td></tr>'
          . '  </table>'";

$newStr = ". '  <div style=\"margin-bottom:40px;font-size:15px;line-height:2;color:#0f172a;\">'
          . '    <div style=\"display:flex;\"><div style=\"width:200px;font-weight:600;color:#334155;\">Company</div><div>: ' . e(\$companyName) . '</div></div>'
          . '    <div style=\"display:flex;\"><div style=\"width:200px;font-weight:600;color:#334155;\">Peminjam</div><div>: ' . e(\$loan['borrower_name'] . (\$loan['borrower_nik'] ? ' (' . \$loan['borrower_nik'] . ')' : '')) . '</div></div>'
          . '    <div style=\"display:flex;\"><div style=\"width:200px;font-weight:600;color:#334155;\">Tanggal Peminjam</div><div>: ' . e(date('d F Y', strtotime(\$loan['loan_date']))) . '</div></div>'
          . '    <div style=\"display:flex;\"><div style=\"width:200px;font-weight:600;color:#334155;\">Estimasi Pengembalian</div><div>: ' . (!empty(\$loan['expected_return_date']) ? e(date('d F Y', strtotime(\$loan['expected_return_date']))) : '-') . '</div></div>'
          . '  </div>'";

if (strpos($content, $oldStr) !== false) {
    $content = str_replace($oldStr, $newStr, $content);
    echo "Replaced details\n";
}

$oldStrTable = ". '  <table style=\"width:100%;border-collapse:collapse;margin-bottom:50px;font-size:14px;\">'";
$newStrTable = ". '  <table style=\"width:100%;border-collapse:collapse;margin-bottom:60px;font-size:14px;table-layout:fixed;border:1px solid #cbd5e1;\">'";
$content = str_replace($oldStrTable, $newStrTable, $content);

$oldStrNo = ". '        <th style=\"border:1px solid #cbd5e1;padding:12px 8px;text-align:center;width:50px;\">No</th>'";
$newStrNo = ". '        <th style=\"border:1px solid #cbd5e1;padding:12px 8px;text-align:center;width:8%;\">No</th>'";
$content = str_replace($oldStrNo, $newStrNo, $content);

$oldStrSig = ". '  <table style=\"width:100%;text-align:center;margin-top:20px;font-size:15px;\">'\n          . '    <tr>'\n          . '      <td style=\"width:50%;vertical-align:top;\">'\n          . '        <p style=\"margin-bottom:90px;color:#334155;\">Peminjam,</p>'\n          . '        <div style=\"font-weight:bold;color:#0f172a;\">( .................................... )</div>'\n          . '      </td>'\n          . '      <td style=\"width:50%;vertical-align:top;\">'\n          . '        <p style=\"margin-bottom:90px;color:#334155;\">Pemberi / Petugas,</p>'\n          . '        <div style=\"font-weight:bold;color:#0f172a;\">( .................................... )</div>'\n          . '      </td>'\n          . '    </tr>'\n          . '  </table>'";

$newStrSig = ". '  <div style=\"display:flex;justify-content:space-between;text-align:center;margin-top:20px;font-size:15px;\">'\n          . '    <div style=\"width:45%;\">'\n          . '      <p style=\"margin:0 0 90px 0;color:#334155;\">Peminjam,</p>'\n          . '      <div style=\"font-weight:bold;color:#0f172a;\">( .................................... )</div>'\n          . '    </div>'\n          . '    <div style=\"width:45%;\">'\n          . '      <p style=\"margin:0 0 90px 0;color:#334155;\">Pemberi / Petugas,</p>'\n          . '      <div style=\"font-weight:bold;color:#0f172a;\">( .................................... )</div>'\n          . '    </div>'\n          . '  </div>'";

if (strpos($content, $oldStrSig) !== false) {
    $content = str_replace($oldStrSig, $newStrSig, $content);
    echo "Replaced signatures\n";
}

file_put_contents($file, $content);
?>
