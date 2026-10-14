<?php
$file = 'app/modules/maintenance.php';
$content = file_get_contents($file);

// 1. handle_route_maintenance
$find = <<<EOT
    \ = \['role'] === 'technician' ? 'WHERE s.technician_id = ' . (int)\['id'] : '';
    try {
        \ = \->query("SELECT s.*, 
EOT;
$replace = <<<EOT
    \ = \['role'] === 'technician' ? 'WHERE s.technician_id = ' . (int)\['id'] : 'WHERE 1=1';
    
    \ = trim((string)(\['start_date'] ?? ''));
    \ = trim((string)(\['end_date'] ?? ''));
    if (\ !== '') {
        \ .= ' AND s.scheduled_date >= ' . \->quote(\);
    }
    if (\ !== '') {
        \ .= ' AND s.scheduled_date <= ' . \->quote(\);
    }

    try {
        \ = \->query("SELECT s.*, 
EOT;
$content = str_replace($find, $replace, $content);

$find2 = <<<EOT
    echo '<section class="panel"><div class="split"><h1>History Preventive Maintenance</h1><div class="actions"><a class="btn" href="' . route_url('export_excel', ['type' => 'maintenance']) . '">Export Excel</a>' . \ . '</div></div></section><section class="panel"><table><tr><th>Tanggal</th><th>Asset / ID</th><th>Pengguna / Lokasi</th><th>Asset Group</th><th>Teknisi</th><th>Status</th><th>Foto</th><th>Aksi</th></tr>';
EOT;
$replace2 = <<<EOT
    \ = ['type' => 'maintenance'];
    if (\ !== '') \['start_date'] = \;
    if (\ !== '') \['end_date'] = \;
    
    echo '<section class="panel"><div class="split"><h1>History Preventive Maintenance</h1><div class="actions"><a class="btn" href="' . route_url('export_excel', \) . '">Export Excel</a>' . \ . '</div></div></section>';
    
    echo '<section class="panel"><form method="get" class="grid four" style="margin-bottom:1rem"><input type="hidden" name="route" value="maintenance"><label>Mulai Tanggal<input type="date" name="start_date" value="' . e(\) . '"></label><label>Sampai Tanggal<input type="date" name="end_date" value="' . e(\) . '"></label><label>&nbsp;<button class="btn primary">Filter</button></label></form></section>';

    echo '<section class="panel"><table><tr><th>Tanggal</th><th>Asset / ID</th><th>Pengguna / Lokasi</th><th>Asset Group</th><th>Teknisi</th><th>Status</th><th>Foto</th><th>Aksi</th></tr>';
EOT;
$content = str_replace($find2, $replace2, $content);

file_put_contents($file, $content);
echo "Done maintenance.\n";
