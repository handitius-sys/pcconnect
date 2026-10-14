<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\asset_loan.php';
$content = file_get_contents($file);

$oldStr = "        . '  <table style=\"width:100%;text-align:center;margin-top:20px;font-size:15px;\">'\n        . '    <tr>'\n        . '      <td style=\"width:50%;vertical-align:top;\">'\n        . '        <p style=\"margin-bottom:90px;color:#334155;\">Peminjam,</p>'\n        . '        <div style=\"font-weight:bold;color:#0f172a;\">( .................................... )</div>'\n        . '      </td>'\n        . '      <td style=\"width:50%;vertical-align:top;\">'\n        . '        <p style=\"margin-bottom:90px;color:#334155;\">Pemberi,</p>'\n        . '        <div style=\"font-weight:bold;color:#0f172a;\">( .................................... )</div>'\n        . '      </td>'\n        . '    </tr>'\n        . '  </table>'\n        . '</div>'\n        . '</section>';";

$newStr = "        . '  <table style=\"width:100%;text-align:center;margin-top:20px;font-size:15px;\">'\n        . '    <tr>'\n        . '      <td style=\"width:50%;vertical-align:top;\">'\n        . '        <p style=\"margin-bottom:90px;color:#334155;\">Peminjam,</p>'\n        . '        <div style=\"font-weight:bold;color:#0f172a;text-transform:uppercase;text-decoration:underline;\">' . e(trim(\$loan['borrower_name']) !== '' ? \$loan['borrower_name'] : '....................................') . '</div>'\n        . '      </td>'\n        . '      <td style=\"width:50%;vertical-align:top;\">'\n        . '        <p style=\"margin-bottom:90px;color:#334155;\">Pemberi / Petugas,</p>'\n        . '        <div style=\"font-weight:bold;color:#0f172a;text-transform:uppercase;text-decoration:underline;\">' . e(trim(\$loan['officer_name'] ?? '') !== '' ? \$loan['officer_name'] : '....................................') . '</div>'\n        . '      </td>'\n        . '    </tr>'\n        . '  </table>'\n        . '</div>'\n        . '</section>';";

$content = str_replace($oldStr, $newStr, $content);
file_put_contents($file, $content);
echo "Done fixing signatures\n";
?>
