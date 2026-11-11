# -*- coding: utf-8 -*-
with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Pindahkan "Rincian Masalah & Foto Bukti" ke nomor 2 di handle_route_ticket_form
old_sec1_start = "            <h2>1. Identitas Unit Aset & Tindakan</h2>"
old_sec2_start = "            <h2>2. Penanganan Oleh</h2>"
old_sec3_start = "            <h2>3. Daftar Tindakan & Catatan Perbaikan</h2>"
old_sec4_start = "            <h2>4. Informasi Pelapor</h2>"
old_sec5_start = "            <h2>5. Rincian Masalah & Foto Bukti</h2>"
end_of_form = '            <div class="actions" style="margin-top:20px;">'

pos1 = content.find(old_sec1_start)
pos2 = content.find(old_sec2_start)
pos3 = content.find(old_sec3_start)
pos4 = content.find(old_sec4_start)
pos5 = content.find(old_sec5_start)
pos_end = content.find(end_of_form, pos5)

if all(p != -1 for p in [pos1, pos2, pos3, pos4, pos5, pos_end]):
    # Note that right before <h2>2. Penanganan Oleh</h2> there is:
    #             <?php
    #             // List mekanik internal & workshop
    #             $mechanics = ...
    php_block_before_2 = """            <?php
            // List mekanik internal & workshop
            $mechanics = $pdo->query("SELECT id, name FROM users WHERE role='corrective_maintenance' AND is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            $workshops = $pdo->query("SELECT id, workshop_name FROM corrective_workshops ORDER BY workshop_name")->fetchAll(PDO::FETCH_ASSOC);
            ?>\n"""
    
    pos2_with_php = content.find(php_block_before_2, pos1)
    
    part_before = content[:pos2_with_php]
    part_sec2_to_4 = content[pos2_with_php:pos5]
    part_sec5 = content[pos5:pos_end]
    part_after = content[pos_end:]

    # Ubah nomor judul
    # Identitas Unit Aset
    part_before = part_before.replace("<h2>1. Identitas Unit Aset & Tindakan</h2>", "<h2>1. Identitas Unit Aset</h2>")
    
    # Rincian Masalah menjadi no 2
    part_sec5 = part_sec5.replace("<h2>5. Rincian Masalah & Foto Bukti</h2>", "<h2>2. Rincian Masalah & Foto Bukti</h2>")
    
    # Penanganan Oleh menjadi no 3
    part_sec2_to_4 = part_sec2_to_4.replace("<h2>2. Penanganan Oleh</h2>", "<h2>3. Penanganan Oleh</h2>")
    
    # Daftar Tindakan & Catatan Perbaikan menjadi no 4
    part_sec2_to_4 = part_sec2_to_4.replace("<h2>3. Daftar Tindakan & Catatan Perbaikan</h2>", "<h2>4. Daftar Tindakan & Catatan Perbaikan</h2>")
    
    # Informasi Pelapor menjadi no 5
    part_sec2_to_4 = part_sec2_to_4.replace("<h2>4. Informasi Pelapor</h2>", "<h2>5. Informasi Pelapor</h2>")

    # Susun ulang: part_before + part_sec5 + part_sec2_to_4 + part_after
    content = part_before + part_sec5 + "\n" + part_sec2_to_4 + part_after
    print("Reordered ticket_form sections successfully.")
else:
    print("WARNING: Could not find all section markers in ticket_form!")

# 2. Update query di handle_route_corrective_repairs agar terhubung dengan data aset corrective (JOIN asset_items & maintenance_assets lengkap)
old_sql_repairs = """    $sql = "SELECT cr.*, t.ticket_code, t.subject, t.asset_category, t.location_label, t.invoice_photo AS ticket_invoice_photo,
                   ma.maintenance_asset_code, ma.name AS maintenance_asset_name, ai.alias_code AS legacy_code
            FROM corrective_repairs cr
            JOIN corrective_tickets t ON t.id = cr.ticket_id
            LEFT JOIN asset_items ai ON ai.id = t.asset_item_id
            LEFT JOIN maintenance_assets ma ON ma.id = t.maintenance_asset_id
            WHERE {$whereSql}
            ORDER BY cr.repaired_at DESC, cr.id DESC";"""

new_sql_repairs = """    $sql = "SELECT cr.*, t.ticket_code, t.subject, t.asset_category, t.location_label, t.invoice_photo AS ticket_invoice_photo,
                   COALESCE(ai.asset_code, ma.maintenance_asset_code, t.pc_id) AS maintenance_asset_code,
                   COALESCE(ai.asset_name, ma.name, t.pc_id) AS maintenance_asset_name,
                   COALESCE(ai.alias_code, ma.alias_code) AS legacy_code,
                   COALESCE(cr.action_type, 'Reparasi') AS action_taken,
                   COALESCE(cr.repair_cost, 0) AS service_cost
            FROM corrective_repairs cr
            JOIN corrective_tickets t ON t.id = cr.ticket_id
            LEFT JOIN asset_items ai ON ai.id = t.asset_item_id
            LEFT JOIN maintenance_assets ma ON ma.id = t.maintenance_asset_id
            WHERE {$whereSql}
            ORDER BY cr.repaired_at DESC, cr.id DESC";"""

if old_sql_repairs in content:
    content = content.replace(old_sql_repairs, new_sql_repairs, 1)
    print("Updated handle_route_corrective_repairs SQL query successfully.")
else:
    print("WARNING: old_sql_repairs not found!")

with open('app/modules/corrective.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Saved app/modules/corrective.php successfully.")
