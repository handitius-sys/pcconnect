<?php
$file = 'app/modules/corrective.php';
$content = file_get_contents($file);

// Reverse all is_active=1 back to status='active'
$content = str_replace("is_active=1", "status='active'", $content);

// Now apply only for asset_groups
$content = str_replace("SELECT id, group_code, group_name FROM asset_groups WHERE status='active' ORDER BY group_name", "SELECT id, group_code, group_name FROM asset_groups WHERE is_active=1 ORDER BY group_name", $content);

// Apply for asset_types
$content = str_replace("SELECT id, type_code, type_name FROM asset_types WHERE asset_group_id = ? AND status='active' ORDER BY type_name", "SELECT id, type_code, type_name FROM asset_types WHERE asset_group_id = ? AND is_active=1 ORDER BY type_name", $content);

file_put_contents($file, $content);
echo "Surgically fixed is_active.\n";
