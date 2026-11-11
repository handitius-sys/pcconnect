with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect print block in corrective.php
old_print_start = "if (!empty($_GET['print'])) {"
idx_print_start = text.find(old_print_start)
idx_print_end = text.find("render_report_print_standalone_page('Laporan Corrective Maintenance (Reparasi & Sparepart)', $periodLabel, $headers, $printRows, $pdo);", idx_print_start)
idx_print_end = text.find("}", idx_print_end) + 1

new_print_block = """if (!empty($_GET['print'])) {
        $headers = ['No', 'Tanggal', 'No Tiket', 'Status', 'Kode Aset', 'Kode Lama', 'Kendala / Subjek', 'Teknisi / Vendor', 'Tindakan & Solusi', 'Sparepart Diganti', 'Biaya Jasa Servis'];
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

            $dateTxt = date('d/m/Y H:i', strtotime($r['repaired_at']));
            $ticketCodeTxt = (string)($r['ticket_code'] ?? '-');
            $statusTxt = strtoupper((string)($r['ticket_status'] ?? '-'));
            $assetTxt = !empty($r['maintenance_asset_code']) ? $r['maintenance_asset_code'] : '-';
            $legacyTxt = !empty($r['legacy_code']) ? $r['legacy_code'] : '-';
            $subjectTxt = (string)($r['subject'] ?? '-');
            $techTxt = !empty($r['vendor_name']) ? $r['vendor_name'] . ' (Vendor)' : (!empty($r['technician_name']) ? $r['technician_name'] : '-');
            $actionTxt = !empty($r['action_taken']) ? $r['action_taken'] : '-';
            $costTxt = 'Rp ' . number_format((float)($r['service_cost'] ?? 0), 0, ',', '.');

            $printRows[] = [
                $no++,
                $dateTxt,
                $ticketCodeTxt,
                $statusTxt,
                $assetTxt,
                $legacyTxt,
                $subjectTxt,
                $techTxt,
                $actionTxt,
                $partsTxt,
                $costTxt
            ];
        }
        $periodLabel = format_report_period_label($startDate, $endDate);
        render_report_print_standalone_page('Laporan Corrective Maintenance (Reparasi & Sparepart)', $periodLabel, $headers, $printRows, $pdo);
    }"""

text = text[:idx_print_start] + new_print_block + text[idx_print_end:]

# Now let's update web table
old_tbl_start = '<table class="report-table-plain report-target-table sortable-table" style="font-size:12px;">'
idx_tbl_start = text.find(old_tbl_start)
idx_tbl_end = text.find('</table>', idx_tbl_start) + 8

new_table_block = """<table class="report-table-plain report-target-table sortable-table" style="font-size:12px;">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>No. Tiket</th>
                    <th>Status</th>
                    <th>Kode Aset</th>
                    <th>Kode Lama (Legacy)</th>
                    <th>Kendala / Subjek</th>
                    <th>Teknisi / Vendor</th>
                    <th>Tindakan & Solusi</th>
                    <th>Sparepart Diganti</th>
                    <th>Biaya Jasa Servis</th>
                    <th style="text-align:center;" class="no-print">Foto Kwitansi / Invoice</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$repairs): ?>
                    <tr><td colspan="11" class="muted" style="text-align:center;padding:24px;">Belum ada riwayat perbaikan.</td></tr>
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
                                <?= e(date('d M Y H:i', strtotime($r['repaired_at']))) ?>
                            </td>
                            <td>
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
                                <strong><?= e($r['subject'] ?: '-') ?></strong>
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
        </table>"""

text = text[:idx_tbl_start] + new_table_block + text[idx_tbl_end:]

with open('app/modules/corrective.php', 'w', encoding='utf-8') as f:
    f.write(text)

print("Updated corrective.php successfully!")

# Now let's also update maintenance.php export
with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f2:
    maint_text = f2.read()

old_export_rows_needle = "$r['repaired_at'] ? date('d/m/Y H:i', strtotime($r['repaired_at'])) : '-',\n                $r['ticket_code'],\n                strtoupper((string)$r['ticket_status']),"
idx_exp = maint_text.find(old_export_rows_needle)
if idx_exp != -1:
    # Let's find output_tsv line
    idx_tsv = maint_text.find("output_tsv(['Tanggal', 'Nomor Tiket', 'Status',", idx_exp)
    idx_tsv_end = maint_text.find("exit;", idx_tsv) + 5
    
    new_export_code = """$r['repaired_at'] ? date('d/m/Y H:i', strtotime($r['repaired_at'])) : '-',
                $r['ticket_code'],
                strtoupper((string)$r['ticket_status']),
                $r['maintenance_asset_code'] ?: '-',
                $r['legacy_code'] ?: '-',
                $r['subject'] ?: '-',
                $r['asset_category'] ?: '-',
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

        output_tsv(['Tanggal', 'Nomor Tiket', 'Status', 'Kode Aset', 'Kode Lama (Legacy)', 'Kendala / Subjek', 'Kategori Aset', 'Nama Unit Aset', 'Teknisi', 'Vendor', 'Jenis Tindakan', 'Solusi & Catatan Pengerjaan', 'Sparepart Diganti', 'Biaya Jasa Servis (Rp)', 'Link Foto/Scan Kwitansi'], $exportRows);
        exit;"""
    
    maint_text = maint_text[:idx_exp] + new_export_code + maint_text[idx_tsv_end:]
    with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f2:
        f2.write(maint_text)
    print("Updated maintenance.php export successfully!")
else:
    print("Export needle not found in maintenance.php")
