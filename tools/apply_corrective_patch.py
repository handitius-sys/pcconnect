# -*- coding: utf-8 -*-
with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update handle_route_ticket_detail agar mendukung mode view (&view=1 atau readonly):
# Pada parameter view, sembunyikan form input teknisi dan tombol hapus
old_view_check = """    $id = (int)($_GET['id'] ?? 0);"""
new_view_check = """    $id = (int)($_GET['id'] ?? 0);
    $isViewOnly = !empty($_GET['view']);"""

if old_view_check in content:
    content = content.replace(old_view_check, new_view_check, 1)
    print("Added $isViewOnly check to ticket_detail.")

# Modifikasi banner/tombol hapus di detail jika view only:
old_detail_actions = """            <div class="actions">
                <a class="btn" href="<?= route_url('tickets') ?>">← Kembali ke Daftar</a>
                <?php if (has_regulation('tickets', 'delete')): ?>
                    <form method="post" action="<?= route_url('tickets') ?>" style="display:inline;" onsubmit="return confirm('Hapus tiket <?= e($ticket['ticket_code']) ?> beserta seluruh data perbaikan terkait?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_ticket">
                        <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
                        <button type="submit" class="btn danger">Hapus Tiket</button>
                    </form>
                <?php endif; ?>
            </div>"""

new_detail_actions = """            <div class="actions">
                <a class="btn" href="<?= route_url('tickets') ?>">← Kembali ke Daftar</a>
                <?php if (empty($isViewOnly) && has_regulation('tickets', 'delete')): ?>
                    <form method="post" action="<?= route_url('tickets') ?>" style="display:inline;" onsubmit="return confirm('Hapus tiket <?= e($ticket['ticket_code']) ?> beserta seluruh data perbaikan terkait?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_ticket">
                        <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
                        <button type="submit" class="btn danger">Hapus Tiket</button>
                    </form>
                <?php endif; ?>
            </div>"""

if old_detail_actions in content:
    content = content.replace(old_detail_actions, new_detail_actions, 1)
    print("Updated detail actions (hide delete in view only).")

# Sembunyikan form eksekusi teknisi jika $isViewOnly
old_form_check = """    <!-- Form Eksekusi Teknisi -->
    <?php if ($ticket['status'] !== 'closed' && in_array($user['role'], ['admin', 'maintenance_admin', 'technician', 'corrective_maintenance'], true)): ?>"""

new_form_check = """    <!-- Form Eksekusi Teknisi -->
    <?php if (empty($isViewOnly) && $ticket['status'] !== 'closed' && in_array($user['role'], ['admin', 'maintenance_admin', 'technician', 'corrective_maintenance'], true)): ?>"""

if old_form_check in content:
    content = content.replace(old_form_check, new_form_check, 1)
    print("Updated form check (hide technician action form in view only).")

# 2. Modifikasi renderCmCard di handle_route_tickets agar lebih ramping & compact
old_render_cm_card = """        return '<div class="stat" style="background-color: ' . $t[0] . '; color: ' . $t[1] . '; border-left: 5px solid ' . $t[2] . '; position: relative; overflow: hidden; padding: 16px 18px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 0; min-width: 0; flex: 1 1 0;">'
             . '<div style="position: absolute; right: -15px; top: -15px; width: 110px; height: 110px; opacity: 0.15; background-image: url(\'' . $bgImage . '\'); background-size: contain; background-repeat: no-repeat; transform: rotate(-10deg);"></div>'
             . '<div style="position: relative; z-index: 1;">'
             . '<strong style="font-size: 30px; display: block; margin-bottom: 2px; line-height: 1; color: ' . $t[1] . ';">' . e($number) . '</strong>'
             . '<span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; opacity: 0.85; color: ' . $t[1] . ';">' . e($label) . '</span>'
             . '</div></div>';"""

new_render_cm_card = """        return '<div class="stat" style="background-color: ' . $t[0] . '; color: ' . $t[1] . '; border-left: 4px solid ' . $t[2] . '; position: relative; overflow: hidden; padding: 10px 14px; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); margin-bottom: 0; min-width: 0; flex: 1 1 0;">'
             . '<div style="position: absolute; right: -10px; top: -10px; width: 70px; height: 70px; opacity: 0.12; background-image: url(\'' . $bgImage . '\'); background-size: contain; background-repeat: no-repeat; transform: rotate(-10deg);"></div>'
             . '<div style="position: relative; z-index: 1;">'
             . '<strong style="font-size: 20px; display: block; margin-bottom: 2px; line-height: 1.1; color: ' . $t[1] . ';">' . e($number) . '</strong>'
             . '<span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; opacity: 0.85; color: ' . $t[1] . ';">' . e($label) . '</span>'
             . '</div></div>';"""

if old_render_cm_card in content:
    content = content.replace(old_render_cm_card, new_render_cm_card, 1)
    print("Updated renderCmCard to compact size.")

# Ubah gap grid block stats
content = content.replace("style=\"display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px;\"", "style=\"display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 8px;\"")

# 3. Ubah tabel tiket:
# - Kolom Unit Aset: HANYA berisi kode aset (e.g. <strong>e($assetCode)</strong>)
# - Kolom Kendala / Subjek: HANYA berisi judul / subjek kendala (e.g. <strong>e($t['subject'])</strong>) tanpa deskripsi panjang
# - Aksi: Icon view (mata), icon penanganan / detail edit (pensil/setting), icon hapus berbaris horizontal (inline-flex, gap:4px)

old_table_section = """                        <?php
                        $assetLabel = '-';
                        if (!empty($t['maintenance_asset_code'])) {
                            $aliasBadge = !empty($t['item_alias_code']) ? '<br><span style="display:inline-block;padding:1px 6px;border-radius:4px;background:#e0f2fe;color:#0284c7;font-size:11px;font-weight:700;margin-top:2px;">Lama: ' . e($t['item_alias_code']) . '</span>' : '';
                            $assetLabel = '<strong>' . e($t['maintenance_asset_code']) . '</strong>' . $aliasBadge . '<br><small class="muted">' . e(mb_strimwidth((string)$t['maintenance_asset_name'], 0, 35, '...')) . '</small>';
                        } elseif (!empty($t['pc_id'])) {
                            $assetLabel = 'PC: <strong>' . e($t['pc_id']) . '</strong>';
                        }
                        $totalCost = (float)$t['total_repair_cost'] + (float)$t['total_part_cost'];

                        $handledDisplay = 'Belum Ditentukan';
                        if ($t['handled_by_type'] === 'workshop' && !empty($t['handled_workshop_name'])) {
                            $handledDisplay = '🏢 Bengkel: <strong>' . e($t['handled_workshop_name']) . '</strong>';
                        } elseif (!empty($t['handled_user_name'])) {
                            $handledDisplay = '👨‍🔧 Internal: <strong>' . e($t['handled_user_name']) . '</strong>';
                        } elseif (!empty($t['assigned_technician_name'])) {
                            $handledDisplay = '👨‍🔧 Internal: <strong>' . e($t['assigned_technician_name']) . '</strong>';
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?= e($t['ticket_code']) ?></strong><br>
                                <span class="muted" style="font-size:12px;"><?= e(date('d M Y H:i', strtotime($t['created_at']))) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($t['commodity_name'])): ?>
                                    <span class="badge" style="background:#e0f2fe;color:#0369a1;"><?= e(($t['commodity_code'] ? $t['commodity_code'] . ' - ' : '') . $t['commodity_name']) ?></span><br>
                                <?php else: ?>
                                    <?= ticket_category_badge($t['asset_category']) ?><br>
                                <?php endif; ?>
                                <div style="margin-top:4px;"><?= ticket_priority_badge($t['priority']) ?></div>
                            </td>
                            <td><?= $assetLabel ?></td>
                            <td><?= !empty($t['item_alias_code']) ? '<span style="display:inline-block;padding:2px 7px;border-radius:4px;background:#fef3c7;color:#92400e;font-size:12px;font-weight:700;border:1px solid #fde68a;font-family:monospace;">' . e($t['item_alias_code']) . '</span>' : '<span class="muted">-</span>' ?></td>
                            <td>
                                <strong><?= e($t['subject']) ?></strong><br>
                                <span class="muted" style="font-size:12px;"><?= e(mb_strimwidth((string)$t['description'], 0, 60, '...')) ?></span>
                            </td>
                            <td>
                                <strong><?= e($t['reporter_name']) ?></strong> <?= $t['reporter_nik'] ? '<span class="muted">(' . e($t['reporter_nik']) . ')</span>' : '' ?><br>
                                <span class="muted" style="font-size:12px;"><?= e($t['location_label'] ?: '-') ?></span>
                            </td>
                            <td>
                                <?= ticket_status_badge($t['status']) ?><br>
                                <small class="muted" style="display:block; margin-top:3px;"><?= $handledDisplay ?></small>
                            </td>
                            <td>
                                <?php if ($totalCost > 0): ?>
                                    <span style="font-weight:600;color:#0f766e;">Rp <?= number_format($totalCost, 0, ',', '.') ?></span><br>
                                    <small class="muted"><?= (int)$t['repair_count'] ?> tindakan</small>
                                <?php else: ?>
                                    <span class="muted">Rp 0</span>
                                <?php endif; ?>
                                <?php if (!empty($t['invoice_photo'])): ?>
                                    <br><a href="<?= e(url($t['invoice_photo'])) ?>" target="_blank" style="font-size:11px; text-decoration:none; color:#0284c7; font-weight:600; display:inline-flex; align-items:center; gap:3px; margin-top:3px;">🧾 Kwitansi</a>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;" class="no-print">
                                <a href="<?= route_url('ticket_detail', ['id' => $t['id']]) ?>" title="Detail Tiket & Penanganan" style="display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; border-radius:6px; text-decoration:none; vertical-align:middle; margin-right:4px;"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a>
                                <?php if (has_regulation('tickets', 'delete')): ?>
                                    <form method="post" action="<?= route_url('tickets') ?>" style="display:inline;" onsubmit="return confirm('Batalkan tiket <?= e($t['ticket_code']) ?> beserta seluruh data perbaikan terkait?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_ticket">
                                        <input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
                                        <button type="submit" title="Hapus Tiket" style="display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; background:#fef2f2; color:#ef4444; border:1px solid #fecaca; border-radius:6px; cursor:pointer; vertical-align:middle;"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>"""

new_table_section = """                        <?php
                        $assetCodeOnly = !empty($t['maintenance_asset_code']) ? $t['maintenance_asset_code'] : (!empty($t['pc_id']) ? $t['pc_id'] : '-');
                        $totalCost = (float)$t['total_repair_cost'] + (float)$t['total_part_cost'];

                        $handledDisplay = 'Belum Ditentukan';
                        if ($t['handled_by_type'] === 'workshop' && !empty($t['handled_workshop_name'])) {
                            $handledDisplay = '🏢 Bengkel: <strong>' . e($t['handled_workshop_name']) . '</strong>';
                        } elseif (!empty($t['handled_user_name'])) {
                            $handledDisplay = '👨‍🔧 Internal: <strong>' . e($t['handled_user_name']) . '</strong>';
                        } elseif (!empty($t['assigned_technician_name'])) {
                            $handledDisplay = '👨‍🔧 Internal: <strong>' . e($t['assigned_technician_name']) . '</strong>';
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?= e($t['ticket_code']) ?></strong><br>
                                <span class="muted" style="font-size:12px;"><?= e(date('d M Y H:i', strtotime($t['created_at']))) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($t['commodity_name'])): ?>
                                    <span class="badge" style="background:#e0f2fe;color:#0369a1;"><?= e(($t['commodity_code'] ? $t['commodity_code'] . ' - ' : '') . $t['commodity_name']) ?></span><br>
                                <?php else: ?>
                                    <?= ticket_category_badge($t['asset_category']) ?><br>
                                <?php endif; ?>
                                <div style="margin-top:4px;"><?= ticket_priority_badge($t['priority']) ?></div>
                            </td>
                            <td><strong><?= e($assetCodeOnly) ?></strong></td>
                            <td><?= !empty($t['item_alias_code']) ? '<span style="display:inline-block;padding:2px 7px;border-radius:4px;background:#fef3c7;color:#92400e;font-size:12px;font-weight:700;border:1px solid #fde68a;font-family:monospace;">' . e($t['item_alias_code']) . '</span>' : '<span class="muted">-</span>' ?></td>
                            <td>
                                <strong><?= e($t['subject']) ?></strong>
                            </td>
                            <td>
                                <strong><?= e($t['reporter_name']) ?></strong> <?= $t['reporter_nik'] ? '<span class="muted">(' . e($t['reporter_nik']) . ')</span>' : '' ?><br>
                                <span class="muted" style="font-size:12px;"><?= e($t['location_label'] ?: '-') ?></span>
                            </td>
                            <td>
                                <?= ticket_status_badge($t['status']) ?><br>
                                <small class="muted" style="display:block; margin-top:3px;"><?= $handledDisplay ?></small>
                            </td>
                            <td>
                                <?php if ($totalCost > 0): ?>
                                    <span style="font-weight:600;color:#0f766e;">Rp <?= number_format($totalCost, 0, ',', '.') ?></span><br>
                                    <small class="muted"><?= (int)$t['repair_count'] ?> tindakan</small>
                                <?php else: ?>
                                    <span class="muted">Rp 0</span>
                                <?php endif; ?>
                                <?php if (!empty($t['invoice_photo'])): ?>
                                    <br><a href="<?= e(url($t['invoice_photo'])) ?>" target="_blank" style="font-size:11px; text-decoration:none; color:#0284c7; font-weight:600; display:inline-flex; align-items:center; gap:3px; margin-top:3px;">🧾 Kwitansi</a>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;" class="no-print">
                                <div style="display:inline-flex; align-items:center; justify-content:center; gap:4px;">
                                    <a href="<?= route_url('ticket_detail', ['id' => $t['id'], 'view' => 1]) ?>" title="Lihat Detail Tiket (View Only)" style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; background:#e0f2fe; color:#0284c7; border:1px solid #bae6fd; border-radius:6px; text-decoration:none;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></a>
                                    <?php if ($t['status'] !== 'closed' && in_array($user['role'], ['admin', 'maintenance_admin', 'technician', 'corrective_maintenance'], true)): ?>
                                        <a href="<?= route_url('ticket_detail', ['id' => $t['id']]) ?>" title="Penanganan / Tindakan Perbaikan" style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; border-radius:6px; text-decoration:none;"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a>
                                    <?php endif; ?>
                                    <?php if (has_regulation('tickets', 'delete')): ?>
                                        <form method="post" action="<?= route_url('tickets') ?>" style="display:inline; margin:0;" onsubmit="return confirm('Batalkan tiket <?= e($t['ticket_code']) ?> beserta seluruh data perbaikan terkait?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_ticket">
                                            <input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
                                            <button type="submit" title="Hapus Tiket" style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; background:#fef2f2; color:#ef4444; border:1px solid #fecaca; border-radius:6px; cursor:pointer;"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>"""

if old_table_section in content:
    content = content.replace(old_table_section, new_table_section, 1)
    print("Updated table rows: asset code only, subject only, view icon + horizontal actions.")
else:
    print("WARNING: old_table_section not found!")

# 4. Juga update tabel pada Riwayat Reparasi (handle_route_corrective_repairs) agar selaras
old_rep_asset = """                            <td>
                                <?= ticket_category_badge($r['asset_category']) ?><br>
                                <?= e($r['maintenance_asset_code'] ?: '-') ?><br>
                                <small class="muted"><?= e(mb_strimwidth((string)$r['maintenance_asset_name'], 0, 35, '...')) ?></small>
                            </td>"""

new_rep_asset = """                            <td>
                                <strong><?= e($r['maintenance_asset_code'] ?: '-') ?></strong>
                            </td>"""

if old_rep_asset in content:
    content = content.replace(old_rep_asset, new_rep_asset, 1)
    print("Updated corrective_repairs asset column to code only.")

with open('app/modules/corrective.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Saved app/modules/corrective.php successfully.")
