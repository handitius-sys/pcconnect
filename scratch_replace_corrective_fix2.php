<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\corrective.php';
$content = file_get_contents($file);

$startStr = "function handle_route_corrective_repairs";
$startPos = strpos($content, $startStr);
$endPos = strpos($content, "\nfunction ", $startPos + 100);

if ($startPos === false || $endPos === false) {
    echo "Could not find boundaries. startPos: " . ($startPos === false ? 'false' : $startPos) . " endPos: " . ($endPos === false ? 'false' : $endPos) . "\n";
    exit(1);
}

$scratchContent = file_get_contents('c:\Users\USER\Documents\PcConnect\scratch_replace_corrective.php');
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
