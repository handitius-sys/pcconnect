# Fix maintenance.php
with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    c = f.read()

# 1. Clean maintenance_status_rows: remove the wrongly placed print block and revert $allReports back to clean execution
target_wrong_block = """    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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
    }
    $rows = [];"""

rep_clean_block = """    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = [];"""

assert target_wrong_block in c, "target_wrong_block not found in maintenance.php"
c = c.replace(target_wrong_block, rep_clean_block, 1)

# 2. In handle_route_reports: after $stmt->execute($params); fetch $allReports and handle &print=1 properly
target_exec_rep = """    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pcs = $pdo->query('SELECT pc_id, owner_name FROM pcs ORDER BY pc_id')->fetchAll();"""

rep_exec_rep = """    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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
        $periodLabel = format_report_period_label('', '');
        render_report_print_standalone_page('Laporan Preventive Maintenance', $periodLabel, $headers, $printRows, $pdo);
    }
    $pcs = $pdo->query('SELECT pc_id, owner_name FROM pcs ORDER BY pc_id')->fetchAll();"""

assert target_exec_rep in c, "target_exec_rep not found in maintenance.php"
c = c.replace(target_exec_rep, rep_exec_rep, 1)

with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
    f.write(c)

print("Fixed maintenance.php successfully.")
