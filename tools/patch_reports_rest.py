# Patch standalone print logic into asset_loan.php, maintenance.php, corrective.php

def patch_asset_loan():
    with open('app/modules/asset_loan.php', 'r', encoding='utf-8') as f:
        c = f.read()

    target_fetch = "$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);"
    rep_fetch = '''$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($_GET['print'])) {
        $headers = ['No', 'No. Pinjam', 'Peminjam', 'Departemen', 'Aset Dipinjam', 'Tgl Pinjam', 'Target Kembali', 'Realisasi Kembali', 'Keperluan', 'Status'];
        $printRows = [];
        $num = 1;
        $today = date('Y-m-d');
        foreach ($rows as $r) {
            $isOver = ($r['status'] === 'active' && !empty($r['expected_return_date']) && $r['expected_return_date'] < $today);
            $stLabel = $isOver ? 'Terlambat' : ($r['status'] === 'active' ? 'Sedang Dipinjam' : ($r['status'] === 'returned' ? 'Sudah Kembali' : ucfirst((string)$r['status'])));
            $realisasi = !empty($r['actual_return_date']) ? date('d/m/Y', strtotime($r['actual_return_date'])) : '-';
            $tglPinjam = !empty($r['loan_date']) ? date('d/m/Y', strtotime($r['loan_date'])) : '-';
            $targetKembali = !empty($r['expected_return_date']) ? date('d/m/Y', strtotime($r['expected_return_date'])) : '-';
            $borrower = trim((string)$r['borrower_name']) . (!empty($r['borrower_nik']) ? ' (' . $r['borrower_nik'] . ')' : '');

            $printRows[] = [
                $num++,
                (string)($r['loan_code'] ?? '-'),
                $borrower,
                (string)($r['borrower_department'] ?? '-'),
                (string)($r['asset_items_summary'] ?? '-'),
                $tglPinjam,
                $targetKembali,
                $realisasi,
                (string)($r['purpose'] ?? '-'),
                $stLabel
            ];
        }
        render_report_print_standalone_page('Laporan Peminjaman Aset', 'Per ' . date('F Y'), $headers, $printRows, $pdo);
    }'''

    assert target_fetch in c, 'target_fetch not found in asset_loan.php'
    c = c.replace(target_fetch, rep_fetch, 1)

    target_btn = ". '    <button type=\"button\" class=\"btn primary\" onclick=\"window.print()\" style=\"padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;\">PRINT</button>'"
    rep_btn = ". '    <a class=\"btn primary\" href=\"' . e(route_url('report_asset_loans', array_merge($_GET, ['print' => 1]))) . '\" target=\"_blank\" style=\"padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;text-decoration:none;\">PRINT</a>'"

    assert target_btn in c, 'target_btn not found in asset_loan.php'
    c = c.replace(target_btn, rep_btn, 1)

    with open('app/modules/asset_loan.php', 'w', encoding='utf-8') as f:
        f.write(c)
    print("asset_loan.php patched successfully.")


def patch_maintenance():
    with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
        c = f.read()

    target_exec = "$stmt->execute($params);"
    rep_exec = '''$stmt->execute($params);
    $allReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($_GET['print'])) {
        $headers = ['No', 'Tanggal', 'PcID / Asset ID', 'Kode Lama', 'Owner / Pengguna', 'Teknisi', 'Status', 'Summary Maintenance'];
        $printRows = [];
        $no = 1;
        foreach ($allReports as $rep) {
            $summary = maintenance_report_summary($pdo, $rep);
            $isCancelled = ($rep['schedule_status'] ?? '') === 'cancelled';
            $dateDisplay = (string)($rep['created_at'] ?? $rep['scheduled_date'] ?? $rep['schedule_created_at'] ?? '-');
            $statusDisplay = $isCancelled ? 'Batal (Tenggang)' : (!empty($rep['id']) ? 'Selesai' : 'Terjadwal');

            $printRows[] = [
                $no++,
                $dateDisplay,
                (string)($rep['pc_id'] ?? '-'),
                (string)($rep['alias_code'] ?? '-'),
                (string)($rep['owner_name'] ?? '-'),
                (string)($rep['technician'] ?? '-'),
                $statusDisplay,
                (string)($summary['summary_text'] ?? '-')
            ];
        }
        render_report_print_standalone_page('Laporan Preventive Maintenance', 'Per ' . date('F Y'), $headers, $printRows, $pdo);
    }'''

    assert target_exec in c, 'target_exec not found in maintenance.php'
    c = c.replace(target_exec, rep_exec, 1)

    # replace foreach ($stmt as $report) with foreach ($allReports as $report)
    target_loop = "foreach ($stmt as $report) {"
    rep_loop = "foreach ($allReports as $report) {"
    assert target_loop in c, 'target_loop not found in maintenance.php'
    c = c.replace(target_loop, rep_loop, 1)

    target_btn = '<button type="button" class="btn primary" onclick="window.print()" style="padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;">PRINT</button>'
    rep_btn = '<a class="btn primary" href="\' . e(route_url(\'reports\', array_merge($_GET, [\'print\' => 1]))) . \'" target="_blank" style="padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;text-decoration:none;">PRINT</a>'

    assert target_btn in c, 'target_btn not found in maintenance.php'
    c = c.replace(target_btn, rep_btn, 1)

    with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
        f.write(c)
    print("maintenance.php patched successfully.")


def patch_corrective():
    with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
        c = f.read()

    target_fetch = "$repairs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);"
    rep_fetch = '''$repairs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($_GET['print'])) {
        $headers = ['No', 'Tanggal & Tiket', 'Kode Aset', 'Kode Lama', 'Teknisi / Vendor', 'Tindakan & Solusi', 'Sparepart Diganti', 'Biaya Jasa Servis'];
        $printRows = [];
        $no = 1;
        foreach ($repairs as $r) {
            $pStmt = $pdo->prepare("SELECT * FROM corrective_repair_parts WHERE repair_id = ?");
            $pStmt->execute([(int)$r['id']]);
            $parts = $pStmt->fetchAll(PDO::FETCH_ASSOC);
            $partsArr = [];
            foreach ($parts as $p) {
                $pStr = $p['part_name'];
                if (!empty($p['part_code'])) $pStr .= ' (' . $p['part_code'] . ')';
                $pStr .= ' x' . $p['qty'] . ' @ Rp ' . number_format((float)$p['price'], 0, ',', '.');
                $partsArr[] = $pStr;
            }
            $partsTxt = !empty($partsArr) ? implode('; ', $partsArr) : '-';

            $ticketTxt = date('d/m/Y H:i', strtotime($r['repaired_at'])) . ' | ' . $r['ticket_code'] . ' - ' . $r['subject'];
            $assetTxt = !empty($r['maintenance_asset_code']) ? $r['maintenance_asset_code'] : '-';
            $legacyTxt = !empty($r['legacy_code']) ? $r['legacy_code'] : '-';
            $techTxt = !empty($r['vendor_name']) ? $r['vendor_name'] . ' (Vendor)' : (!empty($r['technician_name']) ? $r['technician_name'] : '-');
            $actionTxt = !empty($r['action_taken']) ? $r['action_taken'] : '-';
            $costTxt = 'Rp ' . number_format((float)($r['service_cost'] ?? 0), 0, ',', '.');

            $printRows[] = [
                $no++,
                $ticketTxt,
                $assetTxt,
                $legacyTxt,
                $techTxt,
                $actionTxt,
                $partsTxt,
                $costTxt
            ];
        }
        render_report_print_standalone_page('Laporan Corrective Maintenance (Reparasi & Sparepart)', 'Per ' . date('F Y'), $headers, $printRows, $pdo);
    }'''

    assert target_fetch in c, 'target_fetch not found in corrective.php'
    c = c.replace(target_fetch, rep_fetch, 1)

    target_btn = '<button type="button" class="btn primary" onclick="window.print()" style="padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;">PRINT</button>'
    rep_btn = '<a class="btn primary" href="<?= e(route_url(\'corrective_repairs\', array_merge($_GET, [\'print\' => 1]))) ?>" target="_blank" style="padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;text-decoration:none;">PRINT</a>'

    assert target_btn in c, 'target_btn not found in corrective.php'
    c = c.replace(target_btn, rep_btn, 1)

    with open('app/modules/corrective.php', 'w', encoding='utf-8') as f:
        f.write(c)
    print("corrective.php patched successfully.")

patch_asset_loan()
patch_maintenance()
patch_corrective()

