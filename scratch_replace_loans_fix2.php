<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\asset_loan.php';
$content = file_get_contents($file);

$startStr = "function handle_route_report_asset_loans";
$startPos = strpos($content, $startStr);
$endPos = strpos($content, "\nfunction ", $startPos + 100);
if ($endPos === false) {
    $endPos = strlen($content);
}

if ($startPos === false) {
    echo "Could not find startPos.\n";
    exit(1);
}

$scratchContent = file_get_contents('c:\Users\USER\Documents\PcConnect\scratch_replace_loans.php');
preg_match('/\$newFunc = <<<[\'"]EOD[\'"]\n(.*?)\nEOD;/s', $scratchContent, $matches);
if (isset($matches[1])) {
    $newFunc = $matches[1];
    $newContent = substr($content, 0, $startPos) . $newFunc . substr($content, $endPos);
    file_put_contents($file, $newContent);
    echo "Replaced correctly!\n";
} else {
    echo "Could not extract newFunc.\n";
}
?>
