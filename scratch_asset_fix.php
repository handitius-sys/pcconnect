<?php
// Restore and modify asset.php properly
$f = __DIR__ . '/app/modules/asset.php';

// First check out a clean copy from git
exec('git checkout app/modules/asset.php');

$c = file_get_contents($f);

// 1. Remove attach_maintenance and detach_maintenance options
$c = str_replace('<option value="attach_maintenance">Pasang asset item ke Maintenance Asset ID</option>', '', $c);
$c = str_replace('<option value="detach_maintenance">Lepas asset item dari Maintenance Asset ID</option>', '', $c);

// 2. Remove the Maintenance Asset ID field in the transaction form
// <label>Maintenance Asset ID<select name="maintenance_asset_id">...
$c = preg_replace('/<label>Maintenance Asset ID<select name="maintenance_asset_id">.*?<\/select><\/label>/i', '', $c);

// 3. Remove the handling blocks
$block_attach = <<<'EOD'
        if ($type === 'attach_maintenance') {
            $maintenanceAssetId = (int)($_POST['maintenance_asset_id'] ?? 0);
            if ($maintenanceAssetId <= 0) {
                flash('Pilih Maintenance Asset ID tujuan.', 'err');
                return;
            }
            if (function_exists('link_maintenance_asset_item')) {
                link_maintenance_asset_item($pdo, $maintenanceAssetId, $assetItemId, $role, $date);
            }
            $pdo->prepare('INSERT INTO asset_movements (asset_item_id, movement_date, reason, pic) VALUES (?, ?, ?, ?)')->execute([$assetItemId, $date, $reason ?: 'Pasang ke Maintenance Asset ID #' . $maintenanceAssetId, $pic]);
            flash('Asset item berhasil dipasang ke Maintenance Asset ID.');
            return;
        }
EOD;

$block_detach = <<<'EOD'
        if ($type === 'detach_maintenance') {
            $maintenanceAssetId = (int)($_POST['maintenance_asset_id'] ?? 0);
            $detachedCount = 0;
            if (function_exists('detach_asset_item_from_maintenance_assets')) {
                $detachedCount = detach_asset_item_from_maintenance_assets($pdo, $assetItemId, $maintenanceAssetId > 0 ? $maintenanceAssetId : null, $date, $reason ?: 'Lepas dari Maintenance Asset ID');
            }
            if ($detachedCount <= 0) {
                flash('Relasi aktif ke Maintenance Asset ID tidak ditemukan untuk asset item ini.', 'err');
                return;
            }
            $pdo->prepare('INSERT INTO asset_movements (asset_item_id, movement_date, reason, pic) VALUES (?, ?, ?, ?)')->execute([$assetItemId, $date, $reason ?: 'Lepas dari Maintenance Asset ID', $pic]);
            flash('Asset item berhasil dilepas dari Maintenance Asset ID.');
            return;
        }
EOD;

$c = str_replace($block_attach, '', $c);
$c = str_replace($block_detach, '', $c);

// 4. Remove Maintenance Assets stat
$c = preg_replace('/\'maintenance\' => \(int\)\$pdo->query\(\'SELECT COUNT\(\*\) FROM maintenance_assets\'\)->fetchColumn\(\),\s*/i', '', $c);
$c = preg_replace('/<div class="stat"><strong>\' \. e\(\$stats\[\'maintenance\'\]\) \. \'<\/strong><span>Maintenance Assets<\/span><\/div>/i', '', $c);

// 5. Check if we missed any occurrences of Maintenance Asset in UI text
$c = str_replace('Layer identitas aset fisik, company holding, Maintenance Asset ID/QR, mutasi, dan repair history.', 'Layer identitas aset fisik, company holding, mutasi, dan repair history.', $c);

file_put_contents($f, $c);
echo "Cleaned asset.php\n";

