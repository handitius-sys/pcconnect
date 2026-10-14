<?php
$directory = new RecursiveDirectoryIterator('c:\Users\USER\Documents\PcConnect\app');
$iterator = new RecursiveIteratorIterator($directory);
$regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

$count = 0;
foreach ($regex as $file => $regexInfo) {
    $content = file_get_contents($file);
    
    // Remove more than 2 consecutive blank lines
    $content = preg_replace("/\n{3,}/", "\n\n", $content);
    
    // Add comments to common SQL processes if not already there
    $content = preg_replace("/(\\$stmt\\s*=\\s*\\$pdo->prepare\\(.*?(SELECT|INSERT|UPDATE|DELETE).*?\\);)/i", "// Eksekusi query database\n    $1", $content);
    // Clean up duplicate comments we just made
    $content = preg_replace("/(\\/\\/ Eksekusi query database\\s*)+/", "// Eksekusi query database\n    ", $content);
    
    file_put_contents($file, $content);
    $count++;
}
echo "Cleaned $count files across the entire system.\n";
?>
