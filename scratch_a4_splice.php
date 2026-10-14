<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\asset_loan.php';
$lines = file($file);

for ($i = 1170; $i < 1200; $i++) {
    if (strpos($lines[$i], 'echo \'<style>') !== false) {
        $start = $i;
        $end = 0;
        for ($j = $i; $j < $i + 20; $j++) {
            if (strpos($lines[$j], '</style>\';') !== false) {
                $end = $j;
                break;
            }
        }
        
        $newStyle = "    echo '<style>\n"
                  . "    @page { size: A4; margin: 15mm; }\n"
                  . "    @media print {\n"
                  . "        body { background:#fff !important; color:#000 !important; margin: 0; padding: 0; }\n"
                  . "        .nav, .actions, .noprint, .btn { display:none !important; }\n"
                  . "        .panel { border:none !important; box-shadow:none !important; padding:0 !important; margin:0 !important; }\n"
                  . "        .print-receipt { border:none !important; padding:0 !important; margin:0 !important; box-shadow:none !important; width:100% !important; max-width:100% !important; }\n"
                  . "    }\n"
                  . "    </style>';\n";
                  
        array_splice($lines, $start, $end - $start + 1, [$newStyle]);
        echo "Successfully updated styles to A4!\n";
        break;
    }
}
file_put_contents($file, implode("", $lines));
?>
