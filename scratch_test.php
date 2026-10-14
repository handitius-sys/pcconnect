<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\asset_loan.php';
$content = file_get_contents($file);
$oldStr = "        . '    <div style=\"display:inline-flex;gap:4px;flex-wrap:wrap;justify-content:center;\">'                . '<a href=\"' . route_url('asset_loan_form', ['id' => \$l['id']]) . '\" style=\"display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; border-radius:6px; text-decoration:none; vertical-align:middle; margin-right:4px;\">";
var_dump(strpos($content, $oldStr));
?>
