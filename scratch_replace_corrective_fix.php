<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\corrective.php';
$content = file_get_contents($file);

$startStr = "function handle_route_corrective_repairs(PDO \$pdo): void\n{";
$endStr = "\nfunction ";

$startPos = strpos($content, $startStr);
// Find the next function definition after handle_route_corrective_repairs
$endPos = strpos($content, $endStr, $startPos + 100);

if ($startPos === false || $endPos === false) {
    echo "Could not find boundaries.\n";
    exit(1);
}

// ... read the newFunc from scratch_replace_corrective.php and inject it
$scratchContent = file_get_contents('c:\Users\USER\Documents\PcConnect\scratch_replace_corrective.php');
preg_match('/\$newFunc = <<<[\'"]EOD[\'"]\n(.*?)\nEOD;/s', $scratchContent, $matches);
if (isset($matches[1])) {
    $newFunc = $matches[1];
    $newContent = substr($content, 0, $startPos) . $newFunc . "\n" . substr($content, $endPos);
    file_put_contents($file, $newContent);
    echo "Replaced correctly!\n";
} else {
    echo "Could not extract newFunc.\n";
}
?>
