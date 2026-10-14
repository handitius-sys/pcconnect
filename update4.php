<?php
$file = 'app/modules/maintenance.php';
$content = file_get_contents($file);

$find10 = <<<EOT
    if (\ > 0) {
        \[] = 's.technician_id=?';
        \[] = \;
    }
EOT;
$replace10 = <<<EOT
    if (\ > 0) {
        \[] = 's.technician_id=?';
        \[] = \;
    }
    \ = trim((string)(\['start_date'] ?? ''));
    \ = trim((string)(\['end_date'] ?? ''));
    if (\ !== '') {
        \[] = 's.scheduled_date >= ?';
        \[] = \;
    }
    if (\ !== '') {
        \[] = 's.scheduled_date <= ?';
        \[] = \;
    }
EOT;
$content = str_replace($find10, $replace10, $content);

$find11 = <<<EOT
    if (\ > 0) {
        \['technician_id'] = \;
    }
EOT;
$replace11 = <<<EOT
    if (\ > 0) {
        \['technician_id'] = \;
    }
    \ = trim((string)(\['start_date'] ?? ''));
    \ = trim((string)(\['end_date'] ?? ''));
    if (\ !== '') \['start_date'] = \;
    if (\ !== '') \['end_date'] = \;
EOT;
$content = str_replace($find11, $replace11, $content);

$find12 = <<<EOT
    echo '</select></label><label>&nbsp;<button class="btn primary">Filter</button></label></div></form></section>';
EOT;
$replace12 = <<<EOT
    echo '</select></label>';
    echo '<label>Mulai Tanggal<input type="date" name="start_date" value="' . e(\) . '"></label>';
    echo '<label>Sampai Tanggal<input type="date" name="end_date" value="' . e(\) . '"></label>';
    echo '<label>&nbsp;<button class="btn primary">Filter</button></label></div></form></section>';
EOT;
$content = str_replace($find12, $replace12, $content);

file_put_contents($file, $content);
echo "Done maintenance_status_report.\n";
