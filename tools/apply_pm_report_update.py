# -*- coding: utf-8 -*-

with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Judul header di handle_route_reports
content = content.replace("render_header('Report Maintenance', $user);", "render_header('Report Preventive Maintenance', $user);")
content = content.replace("<h1>Report Pengerjaan Maintenance</h1>", "<h1>Report Preventive Maintenance</h1>")
content = content.replace("Export Excel Maintenance", "Export Excel Preventive Maintenance")
content = content.replace("Print Report Maintenance", "Print Report Preventive Maintenance")

# 2. Definisikan helper function untuk summarize maintenance dan analisa kerja jika belum ada
helper_code = '''
function summarize_maintenance_short(array $summary): string
{
    $doneList = [];
    $pendingList = [];
    foreach ($summary['checklist_rows'] ?? [] as $job) {
        $title = trim((string)($job['title'] ?? ''));
        if (($job['status'] ?? '') === 'Done') {
            $doneList[] = $title;
        } else {
            $pendingList[] = $title;
        }
    }
    $parts = [];
    if (!empty($doneList)) {
        $cntDone = count($doneList);
        $parts[] = $cntDone . ' Pekerjaan Maintenance Selesai';
    }
    if (!empty($pendingList)) {
        $cntPending = count($pendingList);
        $pendingDesc = implode(', ', array_slice($pendingList, 0, 3));
        if (count($pendingList) > 3) {
            $pendingDesc .= ' dkk';
        }
        $parts[] = $cntPending . ' Pekerjaan ' . $pendingDesc . ' Pending';
    }
    if (empty($parts)) {
        return (string)($summary['job_progress'] ?? '-');
    }
    return implode(', ', $parts);
}

function maintenance_work_analysis(array $summary): array
{
    $auditScore = (int)($summary['photo_evidence_score'] ?? 0);
    $auditStatus = (string)($summary['challenge_audit_status'] ?? '');
    $auditText = (string)($summary['challenge_audit_text'] ?? '');
    $realMin = $summary['real_duration_minutes'] ?? null;
    $minMin = $summary['minimum_duration_minutes'] ?? 0;

    $isPhotoOk = ($auditScore >= 80 && stripos($auditStatus, 'Lengkap') !== false);
    $photoPart = 'audit foto-foto = ' . ($isPhotoOk ? 'sesuai' : 'ada yang tidak sesuai');
    if (!empty($auditText) && !$isPhotoOk) {
        $photoPart .= ' (' . $auditText . ')';
    }

    $timePart = '';
    if ($realMin !== null) {
        if ($minMin > 0 && $realMin < $minMin) {
            $timePart = 'tidak dalam range waktu yang sesuai (' . $realMin . ' mnt < min ' . $minMin . ' mnt)';
        } else {
            $timePart = 'dalam range waktu yang sesuai (' . $realMin . ' mnt)';
        }
    } else {
        $timePart = 'waktu pengerjaan belum tercatat';
    }

    $combinedText = $photoPart . ' & ' . $timePart;
    $isWarning = (!$isPhotoOk || ($minMin > 0 && $realMin !== null && $realMin < $minMin));

    return [
        'text' => $combinedText,
        'is_warning' => $isWarning,
        'score' => $auditScore,
        'ai_note' => $summary['ai_challenge_audit']['note'] ?? null
    ];
}
'''

if 'function summarize_maintenance_short' not in content:
    idx_target = content.find('function maintenance_report_summary(')
    if idx_target != -1:
        content = content[:idx_target] + helper_code.strip() + "\n\n" + content[idx_target:]

# 3. Update $headers dan $printRows pada print standalone
old_print_block = """    if (!empty($_GET['print'])) {
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
        $periodLabel = format_report_period_label($startDate, $endDate);
        render_report_print_standalone_page('Laporan Preventive Maintenance', $periodLabel, $headers, $printRows, $pdo);
    }"""

new_print_block = """    if (!empty($_GET['print'])) {
        $headers = ['No', 'Tanggal', 'PcID / Asset ID', 'Kode Lama', 'Owner / Pengguna', 'Teknisi', 'Status', 'Summarize Maintenance', 'Analisa Kerja'];
        $printRows = [];
        $no = 1;
        foreach ($allReports as $rep) {
            $summary = maintenance_report_summary($pdo, $rep);
            $isCancelled = ($rep['schedule_status'] ?? '') === 'cancelled';
            $dateDisplay = (string)($rep['created_at'] ?? $rep['scheduled_date'] ?? $rep['schedule_created_at'] ?? '-');
            $statusDisplay = $isCancelled ? 'Batal (Tenggang)' : (!empty($rep['id']) ? 'Selesai' : 'Terjadwal');
            $shortSummary = summarize_maintenance_short($summary);
            $analysis = maintenance_work_analysis($summary);

            $printRows[] = [
                $no++,
                $dateDisplay,
                (string)($rep['pc_id'] ?? '-'),
                (string)($rep['alias_code'] ?? '-'),
                (string)($rep['owner_name'] ?? '-'),
                (string)($rep['technician'] ?? '-'),
                $statusDisplay,
                $shortSummary,
                $analysis['text']
            ];
        }
        $periodLabel = format_report_period_label($startDate, $endDate);
        render_report_print_standalone_page('Laporan Preventive Maintenance', $periodLabel, $headers, $printRows, $pdo);
    }"""

if old_print_block in content:
    content = content.replace(old_print_block, new_print_block)
    print("Replaced print block successfully.")
else:
    print("WARNING: old_print_block not found exactly!")

# 4. Update the HTML table rendering in handle_route_reports
old_table_header = '<th>Summary Maintenance</th><th data-nosort="1">Aksi</th>'
new_table_header = '<th>Summarize Maintenance</th><th>Analisa Kerja</th><th data-nosort="1">Aksi</th>'
content = content.replace(old_table_header, new_table_header)

old_table_loop = """        echo '<tr><td>' . $dateDisplay . '</td><td><strong>' . e($report['pc_id']) . '</strong></td><td>' . (!empty($report['alias_code']) ? '<span style="display:inline-block;padding:2px 7px;border-radius:4px;background:#fef3c7;color:#92400e;font-size:12px;font-weight:700;border:1px solid #fde68a;font-family:monospace;">' . e($report['alias_code']) . '</span>' : '<span class="muted">-</span>') . '</td><td>' . e($report['owner_name']) . '</td><td>' . e($report['technician'] ?: '-') . '</td><td>' . $cancelledBanner . '<table><tr><th>Pekerjaan</th><th>Status</th><th>Keterangan</th></tr>';
        if ($summary['checklist_rows']) {
            foreach ($summary['checklist_rows'] as $job) {
                echo '<tr><td>' . e($job['title']) . '</td><td>' . e($job['status']) . '</td><td>' . e($job['note'] ?: '-') . '</td></tr>';
            }
        } else {
            echo '<tr><td colspan="3" class="muted">Belum ada jobdesk dipilih untuk schedule ini.</td></tr>';
        }
        echo '<tr><th>Audit Foto</th><td colspan="2"><span class="badge' . e($summary['challenge_audit_class']) . '">' . e($summary['challenge_audit_status']) . '</span> <strong>' . e($summary['photo_evidence_score']) . '/100</strong><br><span class="muted">' . e($summary['challenge_audit_text']) . '</span>';
        if (!empty($summary['ai_challenge_audit']['note'])) {
            echo '<br><small style="color:#0369a1;font-weight:600;">🤖 AI Vision: ' . e($summary['ai_challenge_audit']['note']) . '</small>';
        }
        echo '</td></tr>';
        echo '<tr><th>Waktu Real</th><td colspan="2"><strong>' . e($summary['real_duration_text'] ?? '-') . '</strong></td></tr></table></td><td><a class="btn" href="' . route_url('report_print', ['id' => $report['schedule_id']]) . '">Detail</a></td></tr>';"""

new_table_loop = """        $shortSummary = summarize_maintenance_short($summary);
        $analysis = maintenance_work_analysis($summary);

        $analysisColor = $analysis['is_warning'] ? '#b91c1c' : '#15803d';
        $analysisBg = $analysis['is_warning'] ? '#fef2f2' : '#f0fdf4';
        $analysisBorder = $analysis['is_warning'] ? '#fecaca' : '#bbf7d0';

        $analysisHtml = '<div style="background:' . $analysisBg . ';border:1px solid ' . $analysisBorder . ';color:' . $analysisColor . ';padding:6px 10px;border-radius:6px;font-size:12px;line-height:1.4;">'
            . e($analysis['text']);
        if (!empty($analysis['ai_note'])) {
            $analysisHtml .= '<div style="margin-top:4px;font-size:11px;color:#0369a1;font-weight:600;">🤖 AI Vision: ' . e($analysis['ai_note']) . '</div>';
        }
        $analysisHtml .= '</div>';

        $actionBtn = '<a class="btn-icon view" href="' . route_url('report_print', ['id' => $report['schedule_id']]) . '" title="Lihat Detail Report" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;background:#e0f2fe;color:#0284c7;border:1px solid #bae6fd;text-decoration:none;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></a>';

        echo '<tr>'
            . '<td>' . $dateDisplay . '</td>'
            . '<td><strong>' . e($report['pc_id']) . '</strong></td>'
            . '<td>' . (!empty($report['alias_code']) ? '<span style="display:inline-block;padding:2px 7px;border-radius:4px;background:#fef3c7;color:#92400e;font-size:12px;font-weight:700;border:1px solid #fde68a;font-family:monospace;">' . e($report['alias_code']) . '</span>' : '<span class="muted">-</span>') . '</td>'
            . '<td>' . e($report['owner_name']) . '</td>'
            . '<td>' . e($report['technician'] ?: '-') . '</td>'
            . '<td>' . $cancelledBanner . '<div style="font-weight:600;color:#1e293b;line-height:1.4;">' . e($shortSummary) . '</div></td>'
            . '<td>' . $analysisHtml . '</td>'
            . '<td style="text-align:center;">' . $actionBtn . '</td>'
            . '</tr>';"""

if old_table_loop in content:
    content = content.replace(old_table_loop, new_table_loop)
    print("Replaced table loop successfully.")
else:
    print("WARNING: old_table_loop not found exactly!")

with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Saved maintenance.php.")
