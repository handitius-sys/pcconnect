<?php
// Script to replace handle_route_corrective_repairs in corrective.php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\corrective.php';
$content = file_get_contents($file);

$startStr = "function handle_route_corrective_repairs(PDO \$pdo): void\n{";
$endStr = "}\n\n/**\n * Modul: Daftar Walkaround";

$startPos = strpos($content, $startStr);
$endPos = strpos($content, $endStr, $startPos);

if ($startPos === false || $endPos === false) {
    echo "Could not find boundaries.\n";
    exit(1);
}

$newFunc = <<<'EOD'
function handle_route_corrective_repairs(PDO $pdo): void
{
    $user = require_role(['admin', 'maintenance_admin', 'technician', 'corrective_maintenance']);
    ensure_corrective_maintenance_schema($pdo);

    $startDate = trim((string)($_GET['start_date'] ?? ''));
    $endDate = trim((string)($_GET['end_date'] ?? ''));
    $catFilter = trim((string)($_GET['category'] ?? ''));
    
    $where = ["1=1"];
    $params = [];
    if ($startDate !== '') {
        $where[] = "DATE(cr.repaired_at) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $where[] = "DATE(cr.repaired_at) <= ?";
        $params[] = $endDate;
    }
    if ($catFilter !== '') {
        $where[] = "t.asset_category = ?";
        $params[] = $catFilter;
    }

    $sql = "SELECT cr.*, t.ticket_code, t.subject, t.asset_category, t.location_label,
                   ma.maintenance_asset_code, ma.name AS maintenance_asset_name
            FROM corrective_repairs cr
            JOIN corrective_tickets t ON t.id = cr.ticket_id
            LEFT JOIN maintenance_assets ma ON ma.id = t.maintenance_asset_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY cr.repaired_at DESC, cr.id DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalRepairs = count($repairs);
    $totalServiceCost = 0;
    $totalPartsCost = 0;
    
    // We need to fetch parts to calculate total parts cost
    $repairsWithParts = [];
    $pStmt = $pdo->prepare("SELECT * FROM corrective_repair_parts WHERE repair_id = ?");
    
    foreach ($repairs as $r) {
        $totalServiceCost += (float)$r['repair_cost'];
        $pStmt->execute([(int)$r['id']]);
        $parts = $pStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($parts as $p) {
            $totalPartsCost += (float)$p['part_cost'];
        }
        $r['parts'] = $parts;
        $repairsWithParts[] = $r;
    }

    render_header('Laporan Reparasi & Sparepart', $user);
    ?>
    <section class="panel">
        <div class="split">
            <div>
                <h1>Laporan Reparasi & Penggantian Sparepart</h1>
                <p class="muted">Laporan komprehensif tindakan corrective maintenance dan sparepart yang diganti.</p>
            </div>
            <div class="actions">
                <a class="btn primary" href="<?= route_url('tickets') ?>">Daftar Tiket Aktif</a>
            </div>
        </div>

        <form method="get" style="margin-top:20px;">
            <input type="hidden" name="route" value="corrective_repairs">
            <div class="grid four">
                <label>Kategori Aset
                    <select name="category">
                        <option value="">Semua Kategori</option>
                        <option value="IT Comp" <?= $catFilter === 'IT Comp' ? 'selected' : '' ?>>IT Comp</option>
                        <option value="Vehicle" <?= $catFilter === 'Vehicle' ? 'selected' : '' ?>>Vehicle</option>
                        <option value="Facility" <?= $catFilter === 'Facility' ? 'selected' : '' ?>>Facility</option>
                    </select>
                </label>
                <label>Mulai Tanggal
                    <input type="date" name="start_date" value="<?= e($startDate) ?>">
                </label>
                <label>Sampai Tanggal
                    <input type="date" name="end_date" value="<?= e($endDate) ?>">
                </label>
                <label>&nbsp;
                    <button class="btn primary">Filter Report</button>
                </label>
            </div>
        </form>
    </section>

    <div class="grid three" style="margin-bottom:20px;">
        <div class="panel" style="text-align:center;padding:15px;margin-bottom:0;">
            <h3>Total Perbaikan</h3>
            <div style="font-size:2em;font-weight:bold;color:#1e293b;"><?= number_format($totalRepairs) ?></div>
        </div>
        <div class="panel" style="text-align:center;padding:15px;margin-bottom:0;border-bottom:3px solid #10b981;">
            <h3>Total Biaya Jasa</h3>
            <div style="font-size:2em;font-weight:bold;color:#10b981;">Rp <?= number_format($totalServiceCost, 0, ',', '.') ?></div>
        </div>
        <div class="panel" style="text-align:center;padding:15px;margin-bottom:0;border-bottom:3px solid #f59e0b;">
            <h3>Total Biaya Sparepart</h3>
            <div style="font-size:2em;font-weight:bold;color:#f59e0b;">Rp <?= number_format($totalPartsCost, 0, ',', '.') ?></div>
        </div>
    </div>

    <section class="panel">
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal & Tiket</th>
                        <th>Maintenance Asset</th>
                        <th>Teknisi / Vendor</th>
                        <th>Tindakan & Solusi</th>
                        <th>Sparepart Diganti</th>
                        <th>Biaya Jasa Servis</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$repairsWithParts): ?>
                        <tr><td colspan="6" class="muted" style="text-align:center;padding:24px;">Tidak ada riwayat perbaikan yang sesuai kriteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($repairsWithParts as $r): ?>
                            <tr>
                                <td>
                                    <?= e(date('d M Y H:i', strtotime($r['repaired_at']))) ?><br>
                                    <a href="<?= route_url('ticket_detail', ['id' => $r['ticket_id']]) ?>" style="color:#0284c7;font-weight:600;"><?= e($r['ticket_code']) ?></a>
                                </td>
                                <td>
                                    <?= ticket_category_badge($r['asset_category']) ?><br>
                                    <?= e($r['maintenance_asset_code'] ?: '-') ?><br>
                                    <small class="muted"><?= e(mb_strimwidth((string)$r['maintenance_asset_name'], 0, 35, '...')) ?></small>
                                </td>
                                <td>
                                    <?= e($r['technician_name']) ?><br>
                                    <?= $r['vendor_name'] ? '<span class="muted">Vendor: ' . e($r['vendor_name']) . '</span>' : '' ?>
                                </td>
                                <td>
                                    <span class="badge ok"><?= e($r['action_type']) ?></span><br>
                                    <?= e(mb_strimwidth((string)$r['solution_details'], 0, 90, '...')) ?>
                                </td>
                                <td>
                                    <?php if (!$r['parts']): ?>
                                        <span class="muted">-</span>
                                    <?php else: ?>
                                        <?php foreach ($r['parts'] as $p): ?>
                                            • <?= e($p['new_part_name']) ?> (Rp <?= number_format((float)$p['part_cost'], 0, ',', '.') ?>)<br>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    Rp <?= number_format((float)$r['repair_cost'], 0, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
    render_footer();
EOD;

$newContent = substr($content, 0, $startPos) . $newFunc . "\n" . substr($content, $endPos);
file_put_contents($file, $newContent);
echo "Replaced successfully.\n";
?>
