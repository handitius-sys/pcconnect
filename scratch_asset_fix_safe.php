<?php
$f = __DIR__ . '/app/modules/asset.php';
$c = file_get_contents($f);

// 1. Remove attach_maintenance and detach_maintenance options from select
$c = str_replace('<option value="attach_maintenance">Pasang asset item ke Maintenance Asset ID</option>', '', $c);
$c = str_replace('<option value="detach_maintenance">Lepas asset item dari Maintenance Asset ID</option>', '', $c);

// 2. Remove the Maintenance Asset ID field in the transaction form
// <label>Maintenance Asset ID<select name="maintenance_asset_id">...
$c = preg_replace('/<label>Maintenance Asset ID<select name="maintenance_asset_id">.*?<\/select><\/label>/i', '', $c);

// 3. Remove Maintenance Assets stat from dashboard
$c = preg_replace('/\'maintenance\' => \(int\)\$pdo->query\(\'SELECT COUNT\(\*\) FROM maintenance_assets\'\)->fetchColumn\(\),/i', '', $c);
$c = preg_replace('/<div class="stat"><strong>\' \. e\(\$stats\[\'maintenance\'\]\) \. \'<\/strong><span>Maintenance Assets<\/span><\/div>/i', '', $c);

// 4. Update string explanation
$c = str_replace('Layer identitas aset fisik, company holding, Maintenance Asset ID/QR, mutasi, dan repair history.', 'Layer identitas aset fisik, company holding, mutasi, dan repair history.', $c);

// NOTE: Leaving the PHP logic block for attach/detach_maintenance alone to avoid {} syntax errors. 
// It is unreachable now since the options are removed from the form.

file_put_contents($f, $c);
echo "Cleaned asset.php UI safely\n";

