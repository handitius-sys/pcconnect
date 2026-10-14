import os

file_path = "app/modules/pc.php"
with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

# 1. pc_asset_link_detail_html modifications (Removing Maintenance Asset ID from view)
search1 = """    $html = '<section class="panel"><div class="split"><div><h2>Tautan Manajemen Aset</h2><p class="muted">Relasi PC ke Unit Aset dan Maintenance Asset ID.</p></div><a class="btn" href="' . route_url('pc_form', ['pc_id' => $pc['pc_id'] ?? '']) . '">Edit Relasi</a></div>';
    $html .= '<div class="grid two"><div class="mini-card"><strong>Maintenance Asset ID</strong><br>';
    if ($itemId <= 0) {
        $html .= '<span class="badge danger">Belum Sync ke Unit Aset</span><p class="muted">Maintenance Asset & QR hanya dibuat setelah PC disinkronkan ke Unit Aset.</p>';
    } else {
        $html .= '<span class="badge ok">' . e($maintenanceAssetCode !== '' ? $maintenanceAssetCode : 'Tersinkronisasi') . '</span>';
    }
    $html .= '</div>';
    if ($item) {"""

replace1 = """    $html = '<section class="panel"><div class="split"><div><h2>Tautan Manajemen Aset</h2><p class="muted">Relasi PC ke Unit Aset.</p></div><a class="btn" href="' . route_url('pc_form', ['pc_id' => $pc['pc_id'] ?? '']) . '">Edit Relasi</a></div>';
    $html .= '<div class="grid one">';
    if ($item) {"""

content = content.replace(search1, replace1)

# 2. pc_form_html modifications (Removing Maintenance Asset ID from top panel)
search2 = """    $html .= '<section class="panel"><div class="split"><div><h1>' . ($editing ? 'Edit ' . e($pcTitle) : 'Tambah PC Baru') . '</h1><p>' . e($pcSubtitle) . '</p>' . ($assetCode !== '' ? '<p><span class="badge">Maintenance Asset ID ' . e($assetCode) . '</span></p>' : (!$hasSync && $editing ? '<p><span class="badge danger">Belum Tersinkron ke Asset Item</span></p>' : '')) . '</div><div class="actions"><a class="btn" href="' . route_url('pcs') . '">Kembali ke Data PC</a>';"""

replace2 = """    $html .= '<section class="panel"><div class="split"><div><h1>' . ($editing ? 'Edit ' . e($pcTitle) : 'Tambah PC Baru') . '</h1><p>' . e($pcSubtitle) . '</p>' . (!$hasSync && $editing ? '<p><span class="badge danger">Belum Tersinkron ke Asset Item</span></p>' : '') . '</div><div class="actions"><a class="btn" href="' . route_url('pcs') . '">Kembali ke Data PC</a>';"""

content = content.replace(search2, replace2)

# 3. Add 'Upload JSON Analisa' button to pc_form_html
search3 = """        . '<div class="actions">'
        . '<a class="btn good" href="' . e($runnerDownloadUrl) . '">📥 Download PcNalisa-run-' . e($effectivePcId) . '</a>'
        . '<a class="btn" href="' . e($agentDownloadUrl) . '">📄 Script PS1</a>'
        . '</div>'"""

replace3 = """        . '<div class="actions">'
        . '<a class="btn good" href="' . e($runnerDownloadUrl) . '">📥 Download PcNalisa-run-' . e($effectivePcId) . '</a>'
        . '<a class="btn" href="' . e($agentDownloadUrl) . '">📄 Script PS1</a>'
        . '<a class="btn" href="' . route_url('upload_analysis', ['pc_id' => $effectivePcId]) . '">📤 Upload JSON Analisa</a>'
        . '</div>'"""

content = content.replace(search3, replace3)

# 4. Remove download buttons from pc_detail
search4 = """    echo '<a class="btn good" href="' . route_url('download_runner_cmd', ['pc_id' => $pcId, 'token' => (string)config_value('agent_token')]) . '">📥 Download PcNalisa-run-' . e($pcId) . '</a><a class="btn" href="' . route_url('download_agent', ['pc_id' => $pcId, 'token' => (string)config_value('agent_token')]) . '">📄 Script PS1</a><a class="btn" href="' . route_url('pc_location', ['pc_id' => $pcId]) . '">Set Lokasi GPS</a><a class="btn" href="' . route_url('upload_analysis', ['pc_id' => $pcId]) . '">Upload JSON Analisa</a>';"""

replace4 = """    echo '<a class="btn" href="' . route_url('pc_location', ['pc_id' => $pcId]) . '">Set Lokasi GPS</a>';"""

content = content.replace(search4, replace4)

# 5. Remove Corrective Tickets Panel from pc_detail
# Search for the block starting with // Corrective Tickets & Repairs Panel up to }
search5 = """    // Corrective Tickets & Repairs Panel
    if (db_table_exists($pdo, 'corrective_tickets')) {
        $tStmt = $pdo->prepare("SELECT t.*, 
            (SELECT COUNT(*) FROM corrective_repairs cr WHERE cr.ticket_id = t.id) AS repair_count,
            (SELECT COALESCE(SUM(cr.repair_cost), 0) FROM corrective_repairs cr WHERE cr.ticket_id = t.id) AS total_repair_cost,
            (SELECT COALESCE(SUM(crp.part_cost), 0) FROM corrective_repairs cr JOIN corrective_repair_parts crp ON crp.repair_id = cr.id WHERE cr.ticket_id = t.id) AS total_part_cost
            FROM corrective_tickets t
            WHERE t.pc_id = ? OR (t.asset_item_id IS NOT NULL AND t.asset_item_id = ?)
            ORDER BY t.created_at DESC");
        $tStmt->execute([$pcId, (int)($pc['asset_item_id'] ?? 0)]);
        $pcTickets = $tStmt->fetchAll(PDO::FETCH_ASSOC);

        echo '<section class="panel"><div class="split"><h2>Riwayat Tiket & Reparasi (Corrective Maintenance)</h2><a class="btn primary" href="' . route_url('ticket_form', ['pc_id' => $pcId]) . '">+ Laporkan Kerusakan PC Ini</a></div>';
        if (!$pcTickets) {
            echo '<p class="muted">Belum ada tiket atau perbaikan yang tercatat untuk PC ini.</p>';
        } else {
            echo '<div style="overflow-x:auto;"><table class="analysis-table"><thead><tr><th>Waktu Laporan</th><th>Pelapor</th><th>Keluhan</th><th>Status</th><th>Total Biaya</th></tr></thead><tbody>';
            foreach ($pcTickets as $ticket) {
                $statusHtml = '';
                if ($ticket['status'] === 'open') {
                    $statusHtml = '<span class="badge danger">Open</span>';
                } elseif ($ticket['status'] === 'in_progress') {
                    $statusHtml = '<span class="badge" style="background:#fef08a;color:#854d0e;">In Progress</span>';
                } elseif ($ticket['status'] === 'resolved') {
                    $statusHtml = '<span class="badge ok">Resolved</span>';
                } elseif ($ticket['status'] === 'closed') {
                    $statusHtml = '<span class="badge muted">Closed</span>';
                } else {
                    $statusHtml = e($ticket['status']);
                }
                
                $totalRp = number_format((float)($ticket['total_repair_cost'] + $ticket['total_part_cost']), 0, ',', '.');
                $complaint = substr((string)$ticket['complaint_desc'], 0, 80) . (strlen((string)$ticket['complaint_desc']) > 80 ? '...' : '');
                
                echo '<tr>';
                echo '<td><a href="' . route_url('ticket_form', ['id' => $ticket['id']]) . '"><strong>' . e($ticket['created_at']) . '</strong></a></td>';
                echo '<td>' . e($ticket['reporter_name']) . '</td>';
                echo '<td>' . e($complaint) . '</td>';
                echo '<td>' . $statusHtml . '</td>';
                echo '<td>Rp ' . $totalRp . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section>';
    }"""

content = content.replace(search5, "")

with open(file_path, "w", encoding="utf-8") as f:
    f.write(content)

print("Done replacing.")

