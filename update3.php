<?php
$file = 'app/modules/maintenance.php';
$content = file_get_contents($file);

$find7 = <<<EOT
    if (\ === 'reports') {
EOT;
$replace7 = <<<EOT
    if (\ === 'reports') {
        \ = trim((string)(\['start_date'] ?? ''));
        \ = trim((string)(\['end_date'] ?? ''));
EOT;
$content = str_replace($find7, $replace7, $content);

$find8 = <<<EOT
        if ((\['role'] ?? '') === 'technician') {
            \ .= ' AND s.technician_id = ?';
            \[] = \['id'];
        }
EOT;
$replace8 = <<<EOT
        if ((\['role'] ?? '') === 'technician') {
            \ .= ' AND s.technician_id = ?';
            \[] = \['id'];
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
$content = str_replace($find8, $replace8, $content);

$find9 = <<<EOT
    if (\ === 'maintenance') {
        \ = \->query("SELECT s.scheduled_date, 
EOT;
$replace9 = <<<EOT
    if (\ === 'maintenance') {
        \ = trim((string)(\['start_date'] ?? ''));
        \ = trim((string)(\['end_date'] ?? ''));
        \ = 'WHERE 1=1';
        \ = [];
        if (\ !== '') {
            \ .= ' AND s.scheduled_date >= ?';
            \[] = \;
        }
        if (\ !== '') {
            \ .= ' AND s.scheduled_date <= ?';
            \[] = \;
        }
        
        \ = \->prepare("SELECT s.scheduled_date, 
            COALESCE(s.pc_id, s.printer_id, ma.maintenance_asset_code) asset_id, 
            s.asset_type, 
            COALESCE(ag.group_name, 'IT Asset') asset_group,
            COALESCE(p.owner_name, pr.printer_name, ma.owner_name) owner_name, 
            u.name technician, s.status, s.notes 
        FROM maintenance_schedules s 
        LEFT JOIN maintenance_assets ma ON ma.id = s.maintenance_asset_id
        LEFT JOIN asset_groups ag ON ag.id = COALESCE(s.asset_group_id, ma.asset_group_id)
        LEFT JOIN pcs p ON p.pc_id COLLATE utf8mb4_unicode_ci = s.pc_id COLLATE utf8mb4_unicode_ci 
        LEFT JOIN printers pr ON pr.prn_id COLLATE utf8mb4_unicode_ci = s.printer_id COLLATE utf8mb4_unicode_ci 
        LEFT JOIN users u ON u.id = s.technician_id 
        \ 
        ORDER BY s.scheduled_date DESC");
        \->execute(\);
        \ = \->fetchAll();
EOT;
$content = str_replace(<<<EOT
    if (\ === 'maintenance') {
        \ = \->query("SELECT s.scheduled_date, 
            COALESCE(s.pc_id, s.printer_id, ma.maintenance_asset_code) asset_id, 
            s.asset_type, 
            COALESCE(ag.group_name, 'IT Asset') asset_group,
            COALESCE(p.owner_name, pr.printer_name, ma.owner_name) owner_name, 
            u.name technician, s.status, s.notes 
        FROM maintenance_schedules s 
        LEFT JOIN maintenance_assets ma ON ma.id = s.maintenance_asset_id
        LEFT JOIN asset_groups ag ON ag.id = COALESCE(s.asset_group_id, ma.asset_group_id)
        LEFT JOIN pcs p ON p.pc_id COLLATE utf8mb4_unicode_ci = s.pc_id COLLATE utf8mb4_unicode_ci 
        LEFT JOIN printers pr ON pr.prn_id COLLATE utf8mb4_unicode_ci = s.printer_id COLLATE utf8mb4_unicode_ci 
        LEFT JOIN users u ON u.id = s.technician_id 
        ORDER BY s.scheduled_date DESC")->fetchAll();
EOT, $replace9, $content);

file_put_contents($file, $content);
echo "Done export_excel.\n";
