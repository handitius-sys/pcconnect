# Script to patch asset.php, maintenance.php, and corrective.php

def patch_asset():
    with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
        c = f.read()

    # 1. Update filter form in handle_route_report_asset_items
    target_form = """// 9. Form Filter Pencarian
    echo '<section class="panel no-print" style="padding:14px 18px;margin-bottom:20px;">';
    echo '  <form method="get" class="actions" style="gap:12px;align-items:flex-end;">';
    echo '    <input type="hidden" name="route" value="report_asset_items">';
    echo '    <label style="margin:0;">Tanggal Mulai<input type="date" name="start_date" value="' . e($startDate) . '"></label>';
    echo '    <span style="padding-bottom:10px;font-weight:600;color:#64748b;">s/d</span>';
    echo '    <label style="margin:0;">Tanggal Akhir<input type="date" name="end_date" value="' . e($endDate) . '"></label>';
    echo '    <label style="margin:0;flex:1;min-width:180px;">Komoditas (Grup Aset)<select name="group_id" onchange="this.form.submit()">' . asset_group_options($pdo, $groupId, true, 'Semua Komoditas') . '</select></label>';
    echo '    <label style="margin:0;flex:1;min-width:180px;">Kategori Aset (Tipe)<select name="type_id" onchange="this.form.submit()">' . asset_type_options($pdo, $typeId, $groupId, true, 'Semua Kategori (Tipe)', true) . '</select></label>';
    echo '    <label style="margin:0;flex:1;min-width:160px;">Company<select name="company_id" onchange="this.form.submit()">' . company_options($pdo, $companyId, true, 'Semua Company') . '</select></label>';
    echo '    <button class="btn primary" type="submit">Filter</button>';
    echo '    <a class="btn" href="' . route_url('report_asset_items') . '">Reset</a>';
    echo '  </form>';
    echo '</section>';"""

    rep_form = """// 9. Form Filter Pencarian
    $todayStr = date('Y-m-d');
    $valStart = $startDate !== '' ? $startDate : $todayStr;
    $valEnd = $endDate !== '' ? $endDate : $todayStr;

    echo '<section class="panel no-print" style="padding:14px 18px;margin-bottom:20px;">';
    echo '  <form method="get" class="actions" style="gap:12px;align-items:flex-end;">';
    echo '    <input type="hidden" name="route" value="report_asset_items">';
    echo '    <label style="margin:0;flex:1;min-width:180px;">Komoditas<select name="group_id" onchange="this.form.submit()">' . asset_group_options($pdo, $groupId, true, 'Semua Komoditas') . '</select></label>';
    echo '    <label style="margin:0;flex:1;min-width:180px;">Kategori<select name="type_id" onchange="this.form.submit()">' . asset_type_options($pdo, $typeId, $groupId, true, 'Semua Kategori', true) . '</select></label>';
    echo '    <div style="display:inline-flex;align-items:center;gap:6px;">';
    echo '      <input type="date" name="start_date" value="' . e($valStart) . '" style="height:38px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;">';
    echo '      <span style="font-weight:600;color:#64748b;">s/d</span>';
    echo '      <input type="date" name="end_date" value="' . e($valEnd) . '" style="height:38px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;">';
    echo '    </div>';
    echo '    <button class="btn primary" type="submit">Filter</button>';
    echo '    <a class="btn" href="' . route_url('report_asset_items') . '">Reset</a>';
    echo '  </form>';
    echo '</section>';"""

    assert target_form in c, 'target_form not found in asset.php'
    c = c.replace(target_form, rep_form, 1)

    with open('app/modules/asset.php', 'w', encoding='utf-8') as f:
        f.write(c)
    print("asset.php patched successfully.")


def patch_maintenance():
    with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
        c = f.read()

    # 1. In handle_route_reports: extract start_date and end_date
    target_params = """    $pcFilter = trim($_GET['pc_id'] ?? '');
    $techFilter = (int)($_GET['technician_id'] ?? 0);"""

    rep_params = """    $pcFilter = trim($_GET['pc_id'] ?? '');
    $techFilter = (int)($_GET['technician_id'] ?? 0);
    $startDate = trim((string)($_GET['start_date'] ?? ''));
    $endDate = trim((string)($_GET['end_date'] ?? ''));"""

    assert target_params in c, 'target_params not found in maintenance.php'
    c = c.replace(target_params, rep_params, 1)

    # 2. Add start_date and end_date to query where clause in handle_route_reports
    target_query_params = """    if ($user['role'] === 'technician') {
        $sql .= ' AND s.technician_id = ?';
        $params[] = $user['id'];
    }
    $sql .= ' ORDER BY s.scheduled_date DESC, s.id DESC';"""

    rep_query_params = """    if ($user['role'] === 'technician') {
        $sql .= ' AND s.technician_id = ?';
        $params[] = $user['id'];
    }
    if ($startDate !== '') {
        $sql .= ' AND s.scheduled_date >= ?';
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql .= ' AND s.scheduled_date <= ?';
        $params[] = $endDate;
    }
    $sql .= ' ORDER BY s.scheduled_date DESC, s.id DESC';"""

    assert target_query_params in c, 'target_query_params not found in maintenance.php'
    c = c.replace(target_query_params, rep_query_params, 1)

    # 3. Update exportParams in handle_route_reports
    target_exp = """    $exportParams = ['type' => 'reports'];
    if ($pcFilter !== '') {
        $exportParams['pc_id'] = $pcFilter;
    }
    if ($techFilter > 0) {
        $exportParams['technician_id'] = $techFilter;
    }"""

    rep_exp = """    $exportParams = ['type' => 'reports'];
    if ($pcFilter !== '') {
        $exportParams['pc_id'] = $pcFilter;
    }
    if ($techFilter > 0) {
        $exportParams['technician_id'] = $techFilter;
    }
    if ($startDate !== '') {
        $exportParams['start_date'] = $startDate;
    }
    if ($endDate !== '') {
        $exportParams['end_date'] = $endDate;
    }"""

    assert target_exp in c, 'target_exp not found in maintenance.php'
    c = c.replace(target_exp, rep_exp, 1)

    # 4. Update doc headers period in handle_route_reports
    target_hdr = "render_report_document_header('Laporan Preventive Maintenance', 'Per ' . date('F Y'), $pdo);"
    rep_hdr = "$periodLabel = format_report_period_label($startDate, $endDate);\n    render_report_document_header('Laporan Preventive Maintenance', $periodLabel, $pdo);"
    assert target_hdr in c, 'target_hdr not found in maintenance.php'
    c = c.replace(target_hdr, rep_hdr, 1)

    # Standalone print period
    target_print_label = "$periodLabel = format_report_period_label('', '');"
    rep_print_label = "$periodLabel = format_report_period_label($startDate, $endDate);"
    assert target_print_label in c, 'target_print_label not found in maintenance.php'
    c = c.replace(target_print_label, rep_print_label, 1)

    # 5. Update form in handle_route_reports: add date inputs
    target_form_maint = """<form method="get" class="no-print"><input type="hidden" name="route" value="reports"><div class="grid three"><label>Per PC / Printer"""
    rep_form_maint = """<?php
    $todayStr = date('Y-m-d');
    $valStart = $startDate !== '' ? $startDate : $todayStr;
    $valEnd = $endDate !== '' ? $endDate : $todayStr;
    ?>
    <form method="get" class="no-print"><input type="hidden" name="route" value="reports"><div class="grid" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;"><label style="margin:0;">Rentang Tanggal<div style="display:inline-flex;align-items:center;gap:6px;margin-top:4px;"><input type="date" name="start_date" value="<?= e($valStart) ?>" style="height:38px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;"><span style="font-weight:600;color:#64748b;">s/d</span><input type="date" name="end_date" value="<?= e($valEnd) ?>" style="height:38px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;"></div></label><label style="margin:0;min-width:180px;">Per PC / Printer"""

    assert target_form_maint in c, 'target_form_maint not found in maintenance.php'
    c = c.replace(target_form_maint, rep_form_maint, 1)

    # Reset button in form handle_route_reports
    target_btn_maint = """<label>&nbsp;<button class="btn primary">Filter Report</button></label></div></form></section>"""
    rep_btn_maint = """<label style="margin:0;">&nbsp;<button class="btn primary" style="height:38px;">Filter Report</button></label><label style="margin:0;">&nbsp;<a class="btn" href="<?= route_url('reports') ?>" style="height:38px;line-height:24px;">Reset</a></label></div></form></section>"""
    assert target_btn_maint in c, 'target_btn_maint not found in maintenance.php'
    c = c.replace(target_btn_maint, rep_btn_maint, 1)

    # 6. In export_excel for type === 'reports': add start_date and end_date
    target_exp_rep_exec = """        $pcFilter = trim((string)($_GET['pc_id'] ?? ''));
        $techFilter = (int)($_GET['technician_id'] ?? 0);"""

    rep_exp_rep_exec = """        $pcFilter = trim((string)($_GET['pc_id'] ?? ''));
        $techFilter = (int)($_GET['technician_id'] ?? 0);
        $startDate = trim((string)($_GET['start_date'] ?? ''));
        $endDate = trim((string)($_GET['end_date'] ?? ''));"""

    assert target_exp_rep_exec in c, 'target_exp_rep_exec not found in maintenance.php'
    c = c.replace(target_exp_rep_exec, rep_exp_rep_exec, 1)

    target_exp_rep_where = """        if (($user['role'] ?? '') === 'technician') {
            $sql .= ' AND s.technician_id = ?';
            $params[] = $user['id'];
        }
        $sql .= ' ORDER BY s.scheduled_date DESC, s.id DESC';"""

    rep_exp_rep_where = """        if (($user['role'] ?? '') === 'technician') {
            $sql .= ' AND s.technician_id = ?';
            $params[] = $user['id'];
        }
        if ($startDate !== '') {
            $sql .= ' AND s.scheduled_date >= ?';
            $params[] = $startDate;
        }
        if ($endDate !== '') {
            $sql .= ' AND s.scheduled_date <= ?';
            $params[] = $endDate;
        }
        $sql .= ' ORDER BY s.scheduled_date DESC, s.id DESC';"""

    assert target_exp_rep_where in c, 'target_exp_rep_where not found in maintenance.php'
    c = c.replace(target_exp_rep_where, rep_exp_rep_where, 1)

    with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
        f.write(c)
    print("maintenance.php patched successfully.")


def patch_corrective():
    with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
        c = f.read()

    # 1. In handle_route_corrective_repairs: extract date filter and use in WHERE
    target_corr_sql = """    $sql = "SELECT cr.*, t.ticket_code, t.subject, t.asset_category, t.location_label, t.invoice_photo AS ticket_invoice_photo,
                   ma.maintenance_asset_code, ma.name AS maintenance_asset_name, ai.alias_code AS legacy_code
            FROM corrective_repairs cr
            JOIN corrective_tickets t ON t.id = cr.ticket_id
            LEFT JOIN asset_items ai ON ai.id = t.asset_item_id
            LEFT JOIN maintenance_assets ma ON ma.id = t.maintenance_asset_id
            ORDER BY cr.repaired_at DESC, cr.id DESC";
    $repairs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);"""

    rep_corr_sql = """    $startDate = trim((string)($_GET['start_date'] ?? ''));
    $endDate = trim((string)($_GET['end_date'] ?? ''));
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
    $whereSql = implode(' AND ', $where);

    $sql = "SELECT cr.*, t.ticket_code, t.subject, t.asset_category, t.location_label, t.invoice_photo AS ticket_invoice_photo,
                   ma.maintenance_asset_code, ma.name AS maintenance_asset_name, ai.alias_code AS legacy_code
            FROM corrective_repairs cr
            JOIN corrective_tickets t ON t.id = cr.ticket_id
            LEFT JOIN asset_items ai ON ai.id = t.asset_item_id
            LEFT JOIN maintenance_assets ma ON ma.id = t.maintenance_asset_id
            WHERE {$whereSql}
            ORDER BY cr.repaired_at DESC, cr.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);"""

    assert target_corr_sql in c, 'target_corr_sql not found in corrective.php'
    c = c.replace(target_corr_sql, rep_corr_sql, 1)

    # 2. Update print and doc headers in handle_route_corrective_repairs
    target_corr_print = "render_report_print_standalone_page('Laporan Corrective Maintenance (Reparasi & Sparepart)', 'Per ' . date('F Y'), $headers, $printRows, $pdo);"
    rep_corr_print = "$periodLabel = format_report_period_label($startDate, $endDate);\n        render_report_print_standalone_page('Laporan Corrective Maintenance (Reparasi & Sparepart)', $periodLabel, $headers, $printRows, $pdo);"
    assert target_corr_print in c, 'target_corr_print not found in corrective.php'
    c = c.replace(target_corr_print, rep_corr_print, 1)

    target_corr_doc = "render_report_document_header('Laporan Corrective Maintenance (Reparasi & Sparepart)', 'Per ' . date('F Y'), $pdo);"
    rep_corr_doc = "$periodLabel = format_report_period_label($startDate, $endDate);\n    render_report_document_header('Laporan Corrective Maintenance (Reparasi & Sparepart)', $periodLabel, $pdo);"
    assert target_corr_doc in c, 'target_corr_doc not found in corrective.php'
    c = c.replace(target_corr_doc, rep_corr_doc, 1)

    # 3. Update export link in corrective.php to include date params
    target_corr_exp_btn = """<a class="btn" href="<?= route_url('export_excel', ['type' => 'corrective_repairs']) ?>">Export Excel</a>"""
    rep_corr_exp_btn = """<?php
    $exportParams = ['type' => 'corrective_repairs'];
    if ($startDate !== '') $exportParams['start_date'] = $startDate;
    if ($endDate !== '') $exportParams['end_date'] = $endDate;
    ?>
                <a class="btn" href="<?= route_url('export_excel', $exportParams) ?>">Export Excel</a>"""
    assert target_corr_exp_btn in c, 'target_corr_exp_btn not found in corrective.php'
    c = c.replace(target_corr_exp_btn, rep_corr_exp_btn, 1)

    # 4. Add filter form to corrective_repairs
    target_modal_place = """        <?php render_report_print_settings_modal(); ?>"""
    rep_modal_place = """        <?php render_report_print_settings_modal(); ?>
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
        </form>"""

    assert target_modal_place in c, 'target_modal_place not found in corrective.php'
    c = c.replace(target_modal_place, rep_modal_place, 1)

    with open('app/modules/corrective.php', 'w', encoding='utf-8') as f:
        f.write(c)
    print("corrective.php patched successfully.")

patch_asset()
patch_maintenance()
patch_corrective()
