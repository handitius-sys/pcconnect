<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\asset_loan.php';
$content = file_get_contents($file);

$oldStr = "    echo '<style>\n    @media print {\n        body { background:#fff !important; color:#000 !important; }\n        .nav, .actions, .noprint, .btn { display:none !important; }\n        .panel { border:none !important; box-shadow:none !important; padding:0 !important; }\n        .print-receipt { border:1px solid #333 !important; padding:20px !important; }\n    }\n    </style>';";

$newStr = "    echo '<style>\n    @page { size: A4; margin: 20mm; }\n    @media print {\n        body { background:#fff !important; color:#000 !important; margin: 0; padding: 0; }\n        .nav, .actions, .noprint, .btn { display:none !important; }\n        .panel { border:none !important; box-shadow:none !important; padding:0 !important; margin: 0 !important; }\n        .print-receipt { border:none !important; padding:0 !important; margin:0 !important; box-shadow:none !important; width:100% !important; max-width:100% !important; }\n    }\n    </style>';";

if (strpos($content, $oldStr) !== false) {
    $content = str_replace($oldStr, $newStr, $content);
    file_put_contents($file, $content);
    echo "Successfully updated print styles to A4!\n";
} else {
    echo "Could not find the exact string to replace.\n";
}
?>
