import re

# 1. Update corrective.php
with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    corr_code = f.read()

# Let's locate handle_route_corrective_repairs
start_target = 'function handle_route_corrective_repairs(PDO $pdo): void'
idx_start = corr_code.find(start_target)
if idx_start == -1:
    raise Exception("Function handle_route_corrective_repairs not found in corrective.php")

# Find the end of this function (it ends right before function handle_route_walkarounds)
idx_end = corr_code.find('function handle_route_walkarounds', idx_start)
if idx_end == -1:
    raise Exception("function handle_route_walkarounds not found in corrective.php")

new_func = """function handle_route_corrective_repairs(PDO $pdo): void
{
    $user = require_role(['admin', 'maintenance_admin', 'technician', 'corrective_maintenance']);
    ensure_corrective_maintenance_schema($pdo);

    $startDate = trim((string)($_GET['start_date'] ?? ''));
    $endDate = trim((string)($_GET['end_date'] ?? ''));
    $where = ["1=1"];
    $params = [];
    if ($startDate !== '') {
        $where[] = "DATE(COALESCE(cr.repaired_at, t.created_at)) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $where[] = "DATE(COALESCE(cr.repaired_at, t.created_at)) <= ?";
        $params[] = $endDate;
    }
    $whereSql = implode(' AND ', $where);

    $sql = "SELECT t.id AS ticket_id, t.ticket_code, t.subject, t.asset_category, t.location_label, 
                   t.status AS ticket_status, t.created_at AS ticket_created_at,
                   t.invoice_photo AS ticket_invoice_photo,
                   t.assigned_technician_name,
                   COALESCE(ai.asset_code, ma.maintenance_asset_code, t.pc_id) AS maintenance_asset_code,
                   COALESCE(ai.asset_name, ma.name, t.pc_id) AS maintenance_asset_name,
                   ai.alias_code AS legacy_code,
                   cr.id AS repair_id,
                   COALESCE(cr.repaired_at, t.created_at) AS repaired_at,
                   COALESCE(cr.technician_name, t.assigned_technician_name, '-') AS technician_name,
                   cr.vendor_name,
                   COALESCE(cr.action_type, (SELECT cta.action_title FROM corrective_ticket_actions cta WHERE cta.ticket_id = t.id LIMIT 1), 'Perbaikan Tiket') AS action_taken,
                   COALESCE(cr.solution_details, t.description, '-') AS solution_details,
                   COALESCE(cr.repair_cost, 0) AS service_cost,
                   cr.invoice_photo AS repair_invoice_photo
            FROM corrective_tickets t
            LEFT JOIN corrective_repairs cr ON cr.ticket_id = t.id
            LEFT JOIN asset_items ai ON ai.id = t.asset_item_id
            LEFT JOIN maintenance_assets ma ON ma.id = t.maintenance_asset_id
            WHERE {$whereSql}
            ORDER BY COALESCE(cr.repaired_at, t.created_at) DESC, t.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($_GET['print'])) {
        $headers = ['No', 'Tanggal & Tiket', 'Status', 'Kode Aset', 'Kode Lama', 'Teknisi / Vendor', 'Tindakan & Solusi', 'Sparepart Diganti', 'Biaya Jasa Servis'];
        $printRows = [];
        $no = 1;
        foreach ($repairs as $r) {
            $partsTxt = '-';
            if (!empty($r['repair_id'])) {
                $pStmt = $pdo->prepare("SELECT * FROM corrective_repair_parts WHERE repair_id = ?");
                $pStmt->execute([(int)$r['repair_id']]);
                $parts = $pStmt->fetchAll(PDO::FETCH_ASSOC);
                $partsArr = [];
                foreach ($parts as $p) {
                    $pStr = $p['new_part_name'] ?? $p['part_name'] ?? 'Part';
                    if (!empty($p['part_code'])) $pStr .= ' (' . $p['part_code'] . ')';
                    $partsArr[] = $pStr;
                }
                if (!empty($partsArr)) $partsTxt = implode('; ', $partsArr);
            }

            $ticketTxt = date('d/m/Y H:i', strtotime($r['repaired_at'])) . ' | ' . $r['ticket_code'] . ' - ' . $r['subject'];
            $statusTxt = strtoupper((string)($r['ticket_status'] ?? '-'));
            $assetTxt = !empty($r['maintenance_asset_code']) ? $r['maintenance_asset_code'] : '-';
            $legacyTxt = !empty($r['legacy_code']) ? $r['legacy_code'] : '-';
            $techTxt = !empty($r['vendor_name']) ? $r['vendor_name'] . ' (Vendor)' : (!empty($r['technician_name']) ? $r['technician_name'] : '-');
            $actionTxt = !empty($r['action_taken']) ? $r['action_taken'] : '-';
            $costTxt = 'Rp ' . number_format((float)($r['service_cost'] ?? 0), 0, ',', '.');

            $printRows[] = [
                $no++,
                $ticketTxt,
                $statusTxt,
                $assetTxt,
                $legacyTxt,
                $techTxt,
                $actionTxt,
                $partsTxt,
                $costTxt
            ];
        }
        $periodLabel = format_report_period_label($startDate, $endDate);
        render_report_print_standalone_page('Laporan Corrective Maintenance (Reparasi & Sparepart)', $periodLabel, $headers, $printRows, $pdo);
    }

    render_header('Riwayat Reparasi & Sparepart', $user);
    $periodLabel = format_report_period_label($startDate, $endDate);
    render_report_document_header('Laporan Corrective Maintenance (Reparasi & Sparepart)', $periodLabel, $pdo);
    ?>
    <section class="panel">
        <div class="split">
            <div>
                <h1>Riwayat Reparasi & Penggantian Sparepart</h1>
                <p class="muted">Daftar seluruh tindakan corrective maintenance, tiket kendala unit, dan sparepart yang pernah diganti.</p>
            </div>
            <div class="actions no-print" style="display:inline-flex;gap:6px;align-items:center;">
                <a class="btn primary" href="<?= route_url('tickets') ?>">Daftar Tiket Aktif</a>
                <?php
    $exportParams = ['type' => 'corrective_repairs'];
    if ($startDate !== '') $exportParams['start_date'] = $startDate;
    if ($endDate !== '') $exportParams['end_date'] = $endDate;
    ?>
                <?= btn_icon_excel(route_url('export_excel', $exportParams), 'Export Excel Reparasi & Sparepart') ?>
                <?= btn_icon_print(route_url('corrective_repairs', array_merge($_GET, ['print' => 1])), 'Print Report Reparasi & Sparepart') ?>
            </div>
        </div>
        <?php render_report_print_settings_modal(); ?>
        <?php
        $todayStr = date('Y-m-d');
        $valStart = $startDate !== '' ? $startDate : $todayStr;
        $valEnd = $endDate !== '' ? $endDate : $todayStr;
        ?>
        <form method="get" class="actions no-print" style="margin:16px 0 16px 0;align-items:flex-end;gap:12px;">
            <input type="hidden" name="route" value="corrective_repairs">
            <label style="margin:0;">Rentang Tanggal
                <div style="display:inline-flex;align-items:center;gap:6px;margin-top:4px;">
                    <input type="date" name="start_date" value="<?= e($valStart) ?>" style="height:38px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;">
                    <span style="font-weight:600;color:#64748b;">s/d</span>
                    <input type="date" name="end_date" value="<?= e($valEnd) ?>" style="height:38px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;">
                </div>
            </label>
            <button class="btn primary" style="height:38px;">Filter</button>
            <?php if ($startDate !== '' || $endDate !== ''): ?>
                <a class="btn" href="<?= route_url('corrective_repairs') ?>" style="height:38px;line-height:24px;">Reset</a>
            <?php endif; ?>
        </form>

        <div style="overflow-x:auto;">
        <table class="report-table-plain report-target-table sortable-table" style="font-size:12px;">
            <thead>
                <tr>
                    <th>Tanggal & Tiket</th>
                    <th>Status</th>
                    <th>Kode Aset</th>
                    <th>Kode Lama (Legacy)</th>
                    <th>Teknisi / Vendor</th>
                    <th>Tindakan & Solusi</th>
                    <th>Sparepart Diganti</th>
                    <th>Biaya Jasa Servis</th>
                    <th style="text-align:center;" class="no-print">Foto Kwitansi / Invoice</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$repairs): ?>
                    <tr><td colspan="9" class="muted" style="text-align:center;padding:24px;">Belum ada riwayat perbaikan.</td></tr>
                <?php else: ?>
                    <?php foreach ($repairs as $r): ?>
                        <?php
                        $parts = [];
                        if (!empty($r['repair_id'])) {
                            $pStmt = $pdo->prepare("SELECT * FROM corrective_repair_parts WHERE repair_id = ?");
                            $pStmt->execute([(int)$r['repair_id']]);
                            $parts = $pStmt->fetchAll(PDO::FETCH_ASSOC);
                        }
                        $invPhoto = $r['repair_invoice_photo'] ?: ($r['ticket_invoice_photo'] ?? null);
                        $isPdf = $invPhoto && str_ends_with(strtolower($invPhoto), '.pdf');
                        ?>
                        <tr>
                            <td>
                                <?= e(date('d M Y H:i', strtotime($r['repaired_at']))) ?><br>
                                <a href="<?= route_url('ticket_detail', ['id' => $r['ticket_id']]) ?>" style="color:#0284c7;font-weight:600;"><?= e($r['ticket_code']) ?></a>
                            </td>
                            <td>
                                <?= ticket_status_badge($r['ticket_status']) ?>
                            </td>
                            <td>
                                <strong><?= e($r['maintenance_asset_code'] ?: '-') ?></strong>
                            </td>
                            <td>
                                <?= !empty($r['legacy_code']) ? e($r['legacy_code']) : '-' ?>
                            </td>
                            <td>
                                <?= e($r['technician_name'] ?: '-') ?><?= !empty($r['vendor_name']) ? ' (Vendor: ' . e($r['vendor_name']) . ')' : '' ?>
                            </td>
                            <td>
                                <strong><?= e($r['action_taken']) ?></strong><br>
                                <span class="muted" style="font-size:11px;"><?= e(mb_strimwidth((string)$r['solution_details'], 0, 90, '...')) ?></span>
                            </td>
                            <td>
                                <?php if (!$parts): ?>
                                    <span class="muted">-</span>
                                <?php else: ?>
                                    <?php foreach ($parts as $p): ?>
                                        • <?= e($p['new_part_name'] ?? $p['part_name'] ?? 'Part') ?> (Rp <?= number_format((float)($p['part_cost'] ?? $p['price'] ?? 0), 0, ',', '.') ?>)<br>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                Rp <?= number_format((float)$r['service_cost'], 0, ',', '.') ?>
                            </td>
                            <td style="text-align:center;" class="no-print">
                                <?php if (!$invPhoto): ?>
                                    <span class="muted">-</span>
                                <?php elseif ($isPdf): ?>
                                    <a href="<?= e(url($invPhoto)) ?>" target="_blank" class="btn secondary" style="font-size:11px;padding:3px 8px;text-decoration:none;">
                                        📄 PDF
                                    </a>
                                <?php else: ?>
                                    <a href="<?= e(url($invPhoto)) ?>" target="_blank" onclick="openInvoiceImageViewer(this.href); return false;">
                                        <img src="<?= e(url($invPhoto)) ?>" alt="Invoice" style="max-height:36px;border-radius:4px;border:1px solid #cbd5e1;cursor:pointer;">
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>
    <?php
    render_invoice_image_viewer_modal();
    render_footer();
}

"""

new_corr_code = corr_code[:idx_start] + new_func + corr_code[idx_end:]
with open('app/modules/corrective.php', 'w', encoding='utf-8') as f:
    f.write(new_corr_code)

print("Updated corrective.php successfully.")

# 2. Update maintenance.php export
with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    maint_code = f.read()

target_maint = "$type === 'corrective_repairs'"
idx_m = maint_code.find(target_maint)
if idx_m != -1:
    idx_m_end = maint_code.find("output_tsv(['Tanggal Reparasi'", idx_m)
    if idx_m_end != -1:
        idx_exit = maint_code.find("exit;", idx_m_end) + 5
        
        new_maint_part = """$type === 'corrective_repairs') {
        $startDate = trim((string)($_GET['start_date'] ?? ''));
        $endDate = trim((string)($_GET['end_date'] ?? ''));
        $category = trim((string)($_GET['category'] ?? ''));
        $q = trim((string)($_GET['q'] ?? ''));

        $where = ["1=1"];
        $params = [];
        if ($startDate !== '') {
            $where[] = "DATE(COALESCE(cr.repaired_at, t.created_at)) >= ?";
            $params[] = $startDate;
        }
        if ($endDate !== '') {
            $where[] = "DATE(COALESCE(cr.repaired_at, t.created_at)) <= ?";
            $params[] = $endDate;
        }
        if ($category !== '') {
            $where[] = "t.asset_category = ?";
            $params[] = $category;
        }
        if ($q !== '') {
            $where[] = "(t.ticket_code LIKE ? OR ai.asset_code LIKE ? OR ma.maintenance_asset_code LIKE ? OR ma.name LIKE ? OR cr.technician_name LIKE ? OR cr.vendor_name LIKE ? OR cr.action_type LIKE ? OR cr.solution_details LIKE ?)";
            $kw = '%' . $q . '%';
            $params = array_merge($params, [$kw, $kw, $kw, $kw, $kw, $kw, $kw, $kw]);
        }

        $sql = "SELECT t.ticket_code, t.subject, t.asset_category, t.status AS ticket_status,
                       t.location_label, t.invoice_photo AS ticket_invoice_photo,
                       COALESCE(ai.asset_code, ma.maintenance_asset_code, t.pc_id) AS maintenance_asset_code,
                       ai.alias_code AS legacy_code,
                       COALESCE(ai.asset_name, ma.name, t.pc_id) AS maintenance_asset_name,
                       COALESCE(cr.repaired_at, t.created_at) AS repaired_at,
                       COALESCE(cr.technician_name, t.assigned_technician_name, '-') AS technician_name,
                       cr.vendor_name,
                       COALESCE(cr.action_type, (SELECT cta.action_title FROM corrective_ticket_actions cta WHERE cta.ticket_id = t.id LIMIT 1), 'Perbaikan Tiket') AS action_taken,
                       COALESCE(cr.solution_details, t.description, '-') AS solution_details,
                       COALESCE(cr.repair_cost, 0) AS repair_cost,
                       cr.invoice_photo AS repair_invoice_photo,
                       cr.id AS repair_id
                FROM corrective_tickets t
                LEFT JOIN corrective_repairs cr ON cr.ticket_id = t.id
                LEFT JOIN asset_items ai ON ai.id = t.asset_item_id
                LEFT JOIN maintenance_assets ma ON ma.id = t.maintenance_asset_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY COALESCE(cr.repaired_at, t.created_at) DESC, t.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $exportRows = [];
        foreach ($repairs as $r) {
            $partsArr = [];
            if (!empty($r['repair_id'])) {
                $pStmt = $pdo->prepare("SELECT * FROM corrective_repair_parts WHERE repair_id = ?");
                $pStmt->execute([(int)$r['repair_id']]);
                $parts = $pStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($parts as $p) {
                    $pName = $p['new_part_name'] ?? $p['part_name'] ?? 'Part';
                    $pCost = (float)($p['part_cost'] ?? $p['price'] ?? 0);
                    $partsArr[] = $pName . ' (Rp ' . number_format($pCost, 0, ',', '.') . ')';
                }
            }
            $partsStr = !empty($partsArr) ? implode('; ', $partsArr) : '-';
            $invPhoto = $r['repair_invoice_photo'] ?: ($r['ticket_invoice_photo'] ?? null);

            $exportRows[] = [
                $r['repaired_at'] ? date('d/m/Y H:i', strtotime($r['repaired_at'])) : '-',
                $r['ticket_code'],
                strtoupper((string)$r['ticket_status']),
                $r['asset_category'] ?: '-',
                $r['maintenance_asset_code'] ?: '-',
                $r['legacy_code'] ?: '-',
                $r['maintenance_asset_name'] ?: '-',
                $r['technician_name'] ?: '-',
                $r['vendor_name'] ?: '-',
                $r['action_taken'] ?: '-',
                $r['solution_details'] ?: '-',
                $partsStr,
                $r['repair_cost'],
                $invPhoto ? url($invPhoto) : '-',
            ];
        }

        output_tsv(['Tanggal', 'Nomor Tiket', 'Status', 'Kategori Aset', 'Kode Aset', 'Kode Lama (Legacy)', 'Nama Unit Aset', 'Teknisi', 'Vendor', 'Jenis Tindakan', 'Solusi & Catatan Pengerjaan', 'Sparepart Diganti', 'Biaya Jasa Servis (Rp)', 'Link Foto/Scan Kwitansi'], $exportRows);
        exit;"""

        new_maint_code = maint_code[:idx_m] + new_maint_part + maint_code[idx_exit:]
        with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
            f.write(new_maint_code)
        print("Updated maintenance.php export successfully.")
