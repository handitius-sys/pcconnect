<?php
$file = 'app/modules/maintenance.php';
$content = file_get_contents($file);

$find3 = <<<EOT
    \ = trim(\['pc_id'] ?? '');
    \ = (int)(\['technician_id'] ?? 0);
EOT;
$replace3 = <<<EOT
    \ = trim(\['pc_id'] ?? '');
    \ = (int)(\['technician_id'] ?? 0);
    \ = trim((string)(\['start_date'] ?? ''));
    \ = trim((string)(\['end_date'] ?? ''));
EOT;
$content = str_replace($find3, $replace3, $content);

$find4 = <<<EOT
    if (\ > 0) {
        \ .= ' AND s.technician_id = ?';
        \[] = \;
    }
EOT;
$replace4 = <<<EOT
    if (\ > 0) {
        \ .= ' AND s.technician_id = ?';
        \[] = \;
    }
    if (\ !== '') {
        \ .= ' AND s.scheduled_date >= ?';
        \[] = \;
    }
    if (\ !== '') {
        \ .= ' AND s.scheduled_date <= ?';
        \[] = \;
    }
EOT;
$content = str_replace($find4, $replace4, $content);

$find5 = <<<EOT
    if (\ > 0) {
        \['technician_id'] = \;
    }
EOT;
$replace5 = <<<EOT
    if (\ > 0) {
        \['technician_id'] = \;
    }
    if (\ !== '') \['start_date'] = \;
    if (\ !== '') \['end_date'] = \;
EOT;
$content = str_replace($find5, $replace5, $content);

$find6 = <<<EOT
    echo '</select></label><label>&nbsp;<button class="btn primary">Filter Report</button></label></div></form></section><section class="panel"><table><tr><th>Tanggal</th><th>PcID</th><th>Owner</th><th>Teknisi</th><th>Summary Maintenance</th><th>Aksi</th></tr>';
EOT;
$replace6 = <<<EOT
    echo '</select></label>';
    echo '<label>Mulai Tanggal<input type="date" name="start_date" value="' . e(\) . '"></label>';
    echo '<label>Sampai Tanggal<input type="date" name="end_date" value="' . e(\) . '"></label>';
    echo '<label>&nbsp;<button class="btn primary">Filter Report</button></label></div></form></section><section class="panel"><table><tr><th>Tanggal</th><th>PcID</th><th>Owner</th><th>Teknisi</th><th>Summary Maintenance</th><th>Aksi</th></tr>';
EOT;
$content = str_replace($find6, $replace6, $content);

file_put_contents($file, $content);
echo "Done reports.\n";
