<?php
$file = 'c:\Users\USER\Documents\PcConnect\app\modules\corrective.php';
$content = file_get_contents($file);

$oldStr = "render_header('Laporan Reparasi & Sparepart', \$user);
    ?>
    <section class=\"panel\">
        <div class=\"split\">
            <div>
                <h1>Laporan Reparasi & Penggantian Sparepart</h1>
                <p class=\"muted\">Laporan komprehensif tindakan corrective maintenance dan sparepart yang diganti.</p>
            </div>";

$newStr = "render_header('Report Aset Corrective', \$user);
    ?>
    <section class=\"panel\">
        <div class=\"split\">
            <div>
                <h1>Report Aset Corrective</h1>
                <p class=\"muted\">Laporan komprehensif riwayat perbaikan masalah (corrective) dan penggantian sparepart.</p>
            </div>";

$content = str_replace($oldStr, $newStr, $content);

$oldTableStr = "                <thead>
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
                    <?php if (!\$repairsWithParts): ?>
                        <tr><td colspan=\"6\" class=\"muted\" style=\"text-align:center;padding:24px;\">Tidak ada riwayat perbaikan yang sesuai kriteria.</td></tr>
                    <?php else: ?>
                        <?php foreach (\$repairsWithParts as \$r): ?>
                            <tr>
                                <td>
                                    <?= e(date('d M Y H:i', strtotime(\$r['repaired_at']))) ?><br>
                                    <a href=\"<?= route_url('ticket_detail', ['id' => \$r['ticket_id']]) ?>\" style=\"color:#0284c7;font-weight:600;\"><?= e(\$r['ticket_code']) ?></a>
                                </td>
                                <td>
                                    <?= ticket_category_badge(\$r['asset_category']) ?><br>
                                    <?= e(\$r['maintenance_asset_code'] ?: '-') ?><br>
                                    <small class=\"muted\"><?= e(mb_strimwidth((string)\$r['maintenance_asset_name'], 0, 35, '...')) ?></small>
                                </td>
                                <td>
                                    <?= e(\$r['technician_name']) ?><br>
                                    <?= \$r['vendor_name'] ? '<span class=\"muted\">Vendor: ' . e(\$r['vendor_name']) . '</span>' : '' ?>
                                </td>
                                <td>
                                    <span class=\"badge ok\"><?= e(\$r['action_type']) ?></span><br>
                                    <?= e(mb_strimwidth((string)\$r['solution_details'], 0, 90, '...')) ?>
                                </td>";

$newTableStr = "                <thead>
                    <tr>
                        <th>Tanggal & Tiket</th>
                        <th>Maintenance Asset</th>
                        <th>Masalah</th>
                        <th>Di Tangani Oleh</th>
                        <th>Tindakan & Solusi</th>
                        <th>Sparepart Diganti</th>
                        <th>Biaya Jasa Servis</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!\$repairsWithParts): ?>
                        <tr><td colspan=\"7\" class=\"muted\" style=\"text-align:center;padding:24px;\">Tidak ada riwayat perbaikan yang sesuai kriteria.</td></tr>
                    <?php else: ?>
                        <?php foreach (\$repairsWithParts as \$r): ?>
                            <tr>
                                <td>
                                    <?= e(date('d M Y H:i', strtotime(\$r['repaired_at']))) ?><br>
                                    <a href=\"<?= route_url('ticket_detail', ['id' => \$r['ticket_id']]) ?>\" style=\"color:#0284c7;font-weight:600;\"><?= e(\$r['ticket_code']) ?></a>
                                </td>
                                <td>
                                    <?= ticket_category_badge(\$r['asset_category']) ?><br>
                                    <?= e(\$r['maintenance_asset_code'] ?: '-') ?><br>
                                    <small class=\"muted\"><?= e(mb_strimwidth((string)\$r['maintenance_asset_name'], 0, 35, '...')) ?></small>
                                </td>
                                <td>
                                    <strong style=\"color:#b91c1c;\"><?= e(\$r['subject']) ?></strong>
                                </td>
                                <td>
                                    <?= e(\$r['technician_name']) ?><br>
                                    <?= \$r['vendor_name'] ? '<span class=\"muted\">Vendor: ' . e(\$r['vendor_name']) . '</span>' : '' ?>
                                </td>
                                <td>
                                    <span class=\"badge ok\"><?= e(\$r['action_type']) ?></span><br>
                                    <?= e(mb_strimwidth((string)\$r['solution_details'], 0, 90, '...')) ?>
                                </td>";

$content = str_replace($oldTableStr, $newTableStr, $content);
file_put_contents($file, $content);
echo "Done replacing.\n";
?>
