<?php
header('Content-Type: text/plain');
echo "PHP Version: " . PHP_VERSION . "\n";
echo "ZipArchive exists: " . (class_exists('ZipArchive') ? 'YES' : 'NO') . "\n";
echo "Loaded extensions: " . implode(', ', get_loaded_extensions()) . "\n";
