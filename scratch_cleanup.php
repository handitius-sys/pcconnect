<?php
$files = [
    'c:\Users\USER\Documents\PcConnect\app\modules\asset_loan.php',
    'c:\Users\USER\Documents\PcConnect\app\modules\corrective.php',
    'c:\Users\USER\Documents\PcConnect\app\modules\field_service.php',
    'c:\Users\USER\Documents\PcConnect\app\modules\maintenance.php',
    'c:\Users\USER\Documents\PcConnect\app\modules\asset.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Remove more than 2 consecutive blank lines
    $content = preg_replace("/\n{3,}/", "\n\n", $content);
    
    // Add comments to common SQL processes if not already there
    $content = preg_replace("/(\\$stmt\\s*=\\s*\\$pdo->prepare\\(.*?(SELECT|INSERT|UPDATE|DELETE).*?\\);)/i", "// Eksekusi query database\n    $1", $content);
    // Clean up duplicate comments we just made (lazy approach)
    $content = preg_replace("/(\\/\\/ Eksekusi query database\\s*)+/", "// Eksekusi query database\n    ", $content);
    
    file_put_contents($file, $content);
    echo "Cleaned $file\n";
}
?>
