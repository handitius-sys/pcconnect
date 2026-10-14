import os
import re

f = 'app/modules/pc.php'
with open(f, 'r', encoding='utf-8') as file:
    c = file.read()

# 1. Remove "+ Buat Tiket / Reparasi" from view
c = c.replace('<a class="btn primary" href="\' . route_url(\'ticket_form\', [\'pc_id\' => $pcId]) . \'">+ Buat Tiket / Reparasi</a>', '')

# 2. Remove "hapus PC" button from view
c = re.sub(r'if \(has_regulation\(\'pcs\', \'delete\'\)\) \{\s*echo \'<form id="deletePcForm".*?Hapus PC<\/button><\/form>\';\s*\}', 'if (has_regulation(\'pcs\', \'delete\')) {\n        echo \'\';\n    }', c, flags=re.DOTALL)

# 3. Remove "Mobile QR Url" from view
c = re.sub(r'<a class="btn" target="_blank" rel="noopener" href="\' \. e\(\$pc\[\'mobile_service_url\'\] \?\? \'\'\) \. \'">Mobile QR URL<\/a>', '', c)

# 4. Remove "Maintenance Asset ID" and "Maintenance Asset ID MNT-kode" badges
c = re.sub(r'<\?php if \(\$hasSync && \$assetCode !== \'\'\) \{ echo \'<span class="badge">Maintenance Asset ID \' \. e\(\$assetCode\) \. \'</span> \'; \} \?>', '', c)
c = re.sub(r'\(.*?\$hasSync && \$assetCode !== \'\' \? \'<span class="badge">Maintenance Asset ID \' \. e\(\$assetCode\) \. \'<\/span> \' : \'<span class="badge danger">Belum Sinkron ke Asset Item<\/span> \'\)', '(!$hasSync ? \'<span class="badge danger">Belum Sinkron ke Asset Item</span> \' : \'\')', c)

# 5. Remove "Riwayat Tiket & Reparasi" panel from view
c = re.sub(r'// Corrective Tickets & Repairs Panel.*?if \(db_table_exists\(\$pdo, \'corrective_tickets\'\)\).*?echo \'<\/table><\/div><\/section>\';\s*\}', '// Corrective Tickets & Repairs Panel\n    if (db_table_exists($pdo, \'corrective_tickets\')) {\n        $tStmt = $pdo->prepare("SELECT t.*, \n            (SELECT COUNT(*) FROM corrective_repairs cr WHERE cr.ticket_id = t.id) AS repair_count,\n            (SELECT COALESCE(SUM(cr.repair_cost), 0) FROM corrective_repairs cr WHERE cr.ticket_id = t.id) AS total_repair_cost,\n            (SELECT COALESCE(SUM(crp.part_cost), 0) FROM corrective_repairs cr JOIN corrective_repair_parts crp ON crp.repair_id = cr.id WHERE cr.ticket_id = t.id) AS total_part_cost\n            FROM corrective_tickets t\n            WHERE t.pc_id = ? OR (t.asset_item_id IS NOT NULL AND t.asset_item_id = ?)\n            ORDER BY t.created_at DESC");\n        $tStmt->execute([$pcId, (int)($pc[\'asset_item_id\'] ?? 0)]);\n        $pcTickets = $tStmt->fetchAll(PDO::FETCH_ASSOC);\n\n        \n    }', c, flags=re.DOTALL)

# 6. Remove "Upload JSON Analisa" from data PC main list (handle_route_pcs)
c = re.sub(r'<a class="btn" href="\' \. route_url\(\'upload_analysis\'\) \. \'">Upload JSON Analisa<\/a>', '', c)

# 7. Rename Download Runner .CMD to Download PcNalisa-run-{PcId} in view
c = c.replace('📥 Download Runner .CMD', '📥 Download PcNalisa-run-\' . e($pcId) . \'')

# 8. Check edit buttons rename again just in case
c = c.replace('<a class="btn primary" href="\' . e($runnerDownloadUrl) . \'" style="background:#0284c7;border-color:#0284c7;">📥 Download PcNalisa-Run-\' . e($effectivePcId) . \'.cmd</a>', '<a class="btn good" href="\' . e($runnerDownloadUrl) . \'">📥 Download PcNalisa-run-\' . e($effectivePcId) . \'</a>')
c = c.replace('<a class="btn" href="\' . e($agentDownloadUrl) . \'">📄 Script PcNalisa.ps1</a>', '<a class="btn" href="\' . e($agentDownloadUrl) . \'">📄 Script PS1</a>')

with open(f, 'w', encoding='utf-8') as file:
    file.write(c)
print("Restored all cleanups successfully")

