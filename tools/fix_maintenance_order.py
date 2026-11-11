with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Target in handle_route_reports
target_old = """    $user = require_login();
    ensure_maintenance_work_schema($pdo);
    cancel_expired_maintenance_schedules($pdo);
    render_header('Report Maintenance', $user);
    $periodLabel = format_report_period_label($startDate, $endDate);
    render_report_document_header('Laporan Preventive Maintenance', $periodLabel, $pdo);
    $printerReady = printer_schema_ready($pdo);
    if (!$printerReady) {
        echo printer_schema_warning();
    }
    $hasExpiredDate = db_column_exists($pdo, 'maintenance_schedules', 'expired_date');
    $expSql = $hasExpiredDate ? "s.expired_date" : "DATE_ADD(s.scheduled_date, INTERVAL 3 DAY) AS expired_date";
    $hasCancelReason = db_column_exists($pdo, 'maintenance_schedules', 'cancellation_reason');
    $cancelSql = $hasCancelReason ? "s.cancellation_reason" : "NULL AS cancellation_reason";

    $pcFilter = trim($_GET['pc_id'] ?? '');
    $techFilter = (int)($_GET['technician_id'] ?? 0);
    $startDate = trim((string)($_GET['start_date'] ?? ''));
    $endDate = trim((string)($_GET['end_date'] ?? ''));"""

target_new = """    $user = require_login();
    ensure_maintenance_work_schema($pdo);
    cancel_expired_maintenance_schedules($pdo);

    $pcFilter = trim($_GET['pc_id'] ?? '');
    $techFilter = (int)($_GET['technician_id'] ?? 0);
    $startDate = trim((string)($_GET['start_date'] ?? ''));
    $endDate = trim((string)($_GET['end_date'] ?? ''));

    render_header('Report Maintenance', $user);
    $periodLabel = format_report_period_label($startDate, $endDate);
    render_report_document_header('Laporan Preventive Maintenance', $periodLabel, $pdo);
    $printerReady = printer_schema_ready($pdo);
    if (!$printerReady) {
        echo printer_schema_warning();
    }
    $hasExpiredDate = db_column_exists($pdo, 'maintenance_schedules', 'expired_date');
    $expSql = $hasExpiredDate ? "s.expired_date" : "DATE_ADD(s.scheduled_date, INTERVAL 3 DAY) AS expired_date";
    $hasCancelReason = db_column_exists($pdo, 'maintenance_schedules', 'cancellation_reason');
    $cancelSql = $hasCancelReason ? "s.cancellation_reason" : "NULL AS cancellation_reason";"""

if target_old in content:
    content = content.replace(target_old, target_new, 1)
    with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print("SUCCESS: maintenance.php updated successfully!")
else:
    print("FAILED: target_old not found in maintenance.php")
