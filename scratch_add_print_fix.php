<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\asset_loan.php';
$lines = file($file);

for ($i = 0; $i < count($lines); $i++) {
    if (strpos($lines[$i], '                . \'<a href="\' . route_url(\'asset_loan_form\', [\'id\' => $l[\'id\']]) . \'" style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; border-radius:6px; text-decoration:none; vertical-align:middle; margin-right:4px;"><svg') !== false) {
        $lines[$i] = str_replace(
            '. \'<a href="\' . route_url(\'asset_loan_form\', [\'id\' => $l[\'id\']]) . \'" style="display:inline-flex;',
            '. \'<a href="\' . route_url(\'asset_loan_detail\', [\'id\' => $l[\'id\'], \'autoprint\' => 1]) . \'" target="_blank" style="display:inline-flex; align-items:center; justify-content:center; padding:4px 8px; font-size:12px; background:#f8fafc; color:#0f172a; border:1px solid #cbd5e1; border-radius:6px; text-decoration:none; vertical-align:middle; margin-right:4px; font-weight:600;" title="Cetak Tanda Terima">&#128424; Cetak</a>\' . \'<a href="\' . route_url(\'asset_loan_form\', [\'id\' => $l[\'id\']]) . \'" style="display:inline-flex;',
            $lines[$i]
        );
        echo "Button added on line $i\n";
        break;
    }
}
file_put_contents($file, implode("", $lines));
?>
