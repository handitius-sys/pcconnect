# Patch asset.php, asset_loan.php, maintenance.php, corrective.php

def patch_asset():
    with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
        c = f.read()

    # In handle_route_report_asset_items:
    target_params = """    $companyId = (int)($_GET['company_id'] ?? 0);
    $locationId = (int)($_GET['location_id'] ?? 0);
    $status = trim((string)($_GET['status'] ?? ''));
    $q = trim((string)($_GET['q'] ?? ''));"""

    rep_params = """    $companyId = (int)($_GET['company_id'] ?? 0);
    $locationId = (int)($_GET['location_id'] ?? 0);
    $status = trim((string)($_GET['status'] ?? ''));
    $q = trim((string)($_GET['q'] ?? ''));
    $startDate = trim((string)($_GET['start_date'] ?? ''));
    $endDate = trim((string)($_GET['end_date'] ?? ''));"""

    assert target_params in c, 'target_params not found in asset.php'
    c = c.replace(target_params, rep_params, 1)

    # In WHERE clause of handle_route_report_asset_items:
    target_where = """    if ($status !== '') {
        $where[] = "ai.status = ?";
        $params[] = $status;
    } else {
        $where[] = "LOWER(ai.status) <> 'deleted'";
    }"""

    rep_where = """    if ($status !== '') {
        $where[] = "ai.status = ?";
        $params[] = $status;
    } else {
        $where[] = "LOWER(ai.status) <> 'deleted'";
    }
    if ($startDate !== '') {
        $where[] = "DATE(ai.created_at) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $where[] = "DATE(ai.created_at) <= ?";
        $params[] = $endDate;
    }"""

    assert target_where in c, 'target_where not found in asset.php'
    c = c.replace(target_where, rep_where, 1)

    # Replace print call and render headers in handle_route_report_asset_items:
    target_print_call = "render_report_print_standalone_page('Laporan Inventaris Unit Aset', 'Per ' . date('F Y'), $headers, $printRows, $pdo);"
    rep_print_call = "$periodLabel = format_report_period_label($startDate, $endDate);\n        render_report_print_standalone_page('Laporan Unit Aset', $periodLabel, $headers, $printRows, $pdo);"
    assert target_print_call in c, 'target_print_call not found in asset.php'
    c = c.replace(target_print_call, rep_print_call, 1)

    # Replace render_header & render_report_document_header in handle_route_report_asset_items:
    target_doc_headers = """    // 6. Rendering Header Halaman
    render_header('Report Unit Aset Terpilah per Komoditas', $user);
    render_report_document_header('Laporan Inventaris Unit Aset', 'Per ' . date('F Y'), $pdo);"""

    rep_doc_headers = """    // 6. Rendering Header Halaman
    $periodLabel = format_report_period_label($startDate, $endDate);
    render_header('Report Unit Aset', $user);
    render_report_document_header('Laporan Unit Aset', $periodLabel, $pdo);"""

    assert target_doc_headers in c, 'target_doc_headers not found in asset.php'
    c = c.replace(target_doc_headers, rep_doc_headers, 1)

    # Replace subtitle description in handle_route_report_asset_items:
    target_title_desc = """    echo '    <h1 style="margin:0;">Report Unit Aset</h1>';
    echo '    <p class="muted" style="margin:4px 0 0 0;">Laporan rekapitulasi & detail Unit Aset terstruktur dan terpilah berdasarkan Komoditas (Grup Aset).</p>';"""

    rep_title_desc = """    echo '    <h1 style="margin:0;">Report Unit Aset</h1>';
    echo '    <p class="muted" style="margin:4px 0 0 0;">Laporan Detail Unit Aset</p>';"""

    assert target_title_desc in c, 'target_title_desc not found in asset.php'
    c = c.replace(target_title_desc, rep_title_desc, 1)

    # Replace form in handle_route_report_asset_items:
    target_form = """    echo '    <input type="hidden" name="route" value="report_asset_items">';
    echo '    <label style="margin:0;flex:1;min-width:180px;">Komoditas (Grup Aset)<select name="group_id" onchange="this.form.submit()">' . asset_group_options($pdo, $groupId, true, 'Semua Komoditas') . '</select></label>';
    echo '    <label style="margin:0;flex:1;min-width:180px;">Kategori Aset (Tipe)<select name="type_id" onchange="this.form.submit()">' . asset_type_options($pdo, $typeId, $groupId, true, 'Semua Kategori (Tipe)', true) . '</select></label>';
    echo '    <label style="margin:0;flex:1;min-width:160px;">Company<select name="company_id" onchange="this.form.submit()">' . company_options($pdo, $companyId, true, 'Semua Company') . '</select></label>';
    // Cari (Kode/Nama/Brand/SN) ditiadakan
    echo '    <button class="btn primary" type="submit">Filter</button>';
    echo '    <a class="btn" href="' . route_url('report_asset_items') . '">Reset</a>';"""

    rep_form = """    echo '    <input type="hidden" name="route" value="report_asset_items">';
    echo '    <label style="margin:0;">Tanggal Mulai<input type="date" name="start_date" value="' . e($startDate) . '"></label>';
    echo '    <span style="padding-bottom:10px;font-weight:600;color:#64748b;">s/d</span>';
    echo '    <label style="margin:0;">Tanggal Akhir<input type="date" name="end_date" value="' . e($endDate) . '"></label>';
    echo '    <label style="margin:0;flex:1;min-width:180px;">Komoditas (Grup Aset)<select name="group_id" onchange="this.form.submit()">' . asset_group_options($pdo, $groupId, true, 'Semua Komoditas') . '</select></label>';
    echo '    <label style="margin:0;flex:1;min-width:180px;">Kategori Aset (Tipe)<select name="type_id" onchange="this.form.submit()">' . asset_type_options($pdo, $typeId, $groupId, true, 'Semua Kategori (Tipe)', true) . '</select></label>';
    echo '    <label style="margin:0;flex:1;min-width:160px;">Company<select name="company_id" onchange="this.form.submit()">' . company_options($pdo, $companyId, true, 'Semua Company') . '</select></label>';
    echo '    <button class="btn primary" type="submit">Filter</button>';
    echo '    <a class="btn" href="' . route_url('report_asset_items') . '">Reset</a>';"""

    assert target_form in c, 'target_form not found in asset.php'
    c = c.replace(target_form, rep_form, 1)

    # In handle_route_asset_movements:
    target_mov_print = "render_report_print_standalone_page('Laporan Riwayat Mutasi Aset', 'Per ' . date('F Y'), $headers, $printRows, $pdo);"
    rep_mov_print = "$periodLabel = format_report_period_label($startDate, $endDate);\n        render_report_print_standalone_page('Laporan Riwayat Mutasi Aset', $periodLabel, $headers, $printRows, $pdo);"
    assert target_mov_print in c, 'target_mov_print not found in asset.php'
    c = c.replace(target_mov_print, rep_mov_print, 1)

    target_mov_doc = "render_report_document_header('Laporan Riwayat Mutasi Aset', 'Per ' . date('F Y'), $pdo);"
    rep_mov_doc = "$periodLabel = format_report_period_label($startDate, $endDate);\n    render_report_document_header('Laporan Riwayat Mutasi Aset', $periodLabel, $pdo);"
    assert target_mov_doc in c, 'target_mov_doc not found in asset.php'
    c = c.replace(target_mov_doc, rep_mov_doc, 1)

    # Update "s/d" separator in asset_movements form:
    target_mov_form = """. '<label style="margin:0;">Tanggal Mulai<input type="date" name="start_date" value="' . e($startDate) . '"></label>'
        . '<label style="margin:0;">Tanggal Akhir<input type="date" name="end_date" value="' . e($endDate) . '"></label>'"""

    rep_mov_form = """. '<label style="margin:0;">Tanggal Mulai<input type="date" name="start_date" value="' . e($startDate) . '"></label>'
        . '<span style="padding-bottom:10px;font-weight:600;color:#64748b;">s/d</span>'
        . '<label style="margin:0;">Tanggal Akhir<input type="date" name="end_date" value="' . e($endDate) . '"></label>'"""

    assert target_mov_form in c, 'target_mov_form not found in asset.php'
    c = c.replace(target_mov_form, rep_mov_form, 1)

    with open('app/modules/asset.php', 'w', encoding='utf-8') as f:
        f.write(c)
    print("asset.php patched successfully.")


def patch_asset_loan():
    with open('app/modules/asset_loan.php', 'r', encoding='utf-8') as f:
        c = f.read()

    target_loan_print = "render_report_print_standalone_page('Laporan Peminjaman Aset', 'Per ' . date('F Y'), $headers, $printRows, $pdo);"
    rep_loan_print = "$periodLabel = format_report_period_label($startDate, $endDate);\n        render_report_print_standalone_page('Laporan Peminjaman Aset', $periodLabel, $headers, $printRows, $pdo);"
    assert target_loan_print in c, 'target_loan_print not found in asset_loan.php'
    c = c.replace(target_loan_print, rep_loan_print, 1)

    target_loan_doc = "render_report_document_header('Laporan Peminjaman Aset', 'Per ' . date('F Y'), $pdo);"
    rep_loan_doc = "$periodLabel = format_report_period_label($startDate, $endDate);\n    render_report_document_header('Laporan Peminjaman Aset', $periodLabel, $pdo);"
    assert target_loan_doc in c, 'target_loan_doc not found in asset_loan.php'
    c = c.replace(target_loan_doc, rep_loan_doc, 1)

    # In asset_loan form: update "Tgl Pinjam Dari" -> "Tanggal Mulai", "s/d", "Tanggal Akhir"
    target_loan_form = """. '  <label style="margin:0;">Tgl Pinjam Dari<input type="date" name="start_date" value="' . e($startDate) . '"></label>'
        . '  <label style="margin:0;">Tgl Pinjam Sampai<input type="date" name="end_date" value="' . e($endDate) . '"></label>'"""

    rep_loan_form = """. '  <label style="margin:0;">Tanggal Mulai<input type="date" name="start_date" value="' . e($startDate) . '"></label>'
        . '  <span style="padding-bottom:10px;font-weight:600;color:#64748b;">s/d</span>'
        . '  <label style="margin:0;">Tanggal Akhir<input type="date" name="end_date" value="' . e($endDate) . '"></label>'"""

    assert target_loan_form in c, 'target_loan_form not found in asset_loan.php'
    c = c.replace(target_loan_form, rep_loan_form, 1)

    with open('app/modules/asset_loan.php', 'w', encoding='utf-8') as f:
        f.write(c)
    print("asset_loan.php patched successfully.")


def patch_maintenance():
    with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
        c = f.read()

    # In export_excel for type === 'asset_items':
    target_exp_params = """    if ($type === 'asset_items') {
        $groupId = (int)($_GET['group_id'] ?? 0);
        $typeId = (int)($_GET['type_id'] ?? ($_GET['asset_type_id'] ?? ($_GET['category_id'] ?? 0)));
        $categoryRaw = trim((string)($_GET['category'] ?? ''));
        if ($typeId <= 0 && is_numeric($categoryRaw) && (int)$categoryRaw > 0) {
            $typeId = (int)$categoryRaw;
        }
        $companyId = (int)($_GET['company_id'] ?? 0);
        $locationId = (int)($_GET['location_id'] ?? 0);
        $status = trim((string)($_GET['status'] ?? ''));
        $q = trim((string)($_GET['q'] ?? ''));"""

    rep_exp_params = """    if ($type === 'asset_items') {
        $groupId = (int)($_GET['group_id'] ?? 0);
        $typeId = (int)($_GET['type_id'] ?? ($_GET['asset_type_id'] ?? ($_GET['category_id'] ?? 0)));
        $categoryRaw = trim((string)($_GET['category'] ?? ''));
        if ($typeId <= 0 && is_numeric($categoryRaw) && (int)$categoryRaw > 0) {
            $typeId = (int)$categoryRaw;
        }
        $companyId = (int)($_GET['company_id'] ?? 0);
        $locationId = (int)($_GET['location_id'] ?? 0);
        $status = trim((string)($_GET['status'] ?? ''));
        $q = trim((string)($_GET['q'] ?? ''));
        $startDate = trim((string)($_GET['start_date'] ?? ''));
        $endDate = trim((string)($_GET['end_date'] ?? ''));"""

    assert target_exp_params in c, 'target_exp_params not found in maintenance.php'
    c = c.replace(target_exp_params, rep_exp_params, 1)

    target_exp_where = """        if ($status !== '') { 
            $where[] = "ai.status = ?"; 
            $params[] = $status; 
        } else {
            $where[] = "LOWER(ai.status) <> 'deleted'";
        }"""

    rep_exp_where = """        if ($status !== '') { 
            $where[] = "ai.status = ?"; 
            $params[] = $status; 
        } else {
            $where[] = "LOWER(ai.status) <> 'deleted'";
        }
        if ($startDate !== '') {
            $where[] = "DATE(ai.created_at) >= ?";
            $params[] = $startDate;
        }
        if ($endDate !== '') {
            $where[] = "DATE(ai.created_at) <= ?";
            $params[] = $endDate;
        }"""

    assert target_exp_where in c, 'target_exp_where not found in maintenance.php'
    c = c.replace(target_exp_where, rep_exp_where, 1)

    with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
        f.write(c)
    print("maintenance.php patched successfully.")


patch_asset()
patch_asset_loan()
patch_maintenance()
