<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\asset_loan.php';
$content = file_get_contents($file);

$oldStr = '        . ''</form>'';

    if (empty($rows)) {';

$newStr = '        . ''</form>'';

    $totalLoans = count($rows);
    $activeLoans = 0;
    $returnedLoans = 0;
    $overdueLoans = 0;
    $nowStr = date(''Y-m-d H:i:s'');
    foreach ($rows as $r) {
        if ($r[''status''] === ''active'') {
            $activeLoans++;
            if (!empty($r[''target_return_date'']) && $r[''target_return_date''] < $nowStr) {
                $overdueLoans++;
            }
        } else {
            $returnedLoans++;
        }
    }

    echo ''<div class="grid four" style="margin-bottom:20px;">''
       . ''<div class="panel" style="text-align:center;padding:15px;margin-bottom:0;"><h3>Total Peminjaman</h3><div style="font-size:2em;font-weight:bold;color:#1e293b;">'' . $totalLoans . ''</div></div>''
       . ''<div class="panel" style="text-align:center;padding:15px;margin-bottom:0;border-bottom:3px solid #3b82f6;"><h3>Aktif (Dipinjam)</h3><div style="font-size:2em;font-weight:bold;color:#3b82f6;">'' . $activeLoans . ''</div></div>''
       . ''<div class="panel" style="text-align:center;padding:15px;margin-bottom:0;border-bottom:3px solid #10b981;"><h3>Dikembalikan</h3><div style="font-size:2em;font-weight:bold;color:#10b981;">'' . $returnedLoans . ''</div></div>''
       . ''<div class="panel" style="text-align:center;padding:15px;margin-bottom:0;border-bottom:3px solid #ef4444;"><h3>Terlambat (Overdue)</h3><div style="font-size:2em;font-weight:bold;color:#ef4444;">'' . $overdueLoans . ''</div></div>''
       . ''</div>'';

    if (empty($rows)) {';

$content = str_replace($oldStr, $newStr, $content);
file_put_contents($file, $content);
echo "Replaced in asset_loan.php\n";
?>
