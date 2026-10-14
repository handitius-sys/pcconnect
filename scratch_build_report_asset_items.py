import os

# 1. Append function to app/modules/asset.php
asset_file = 'app/modules/asset.php'
with open(asset_file, 'r', encoding='utf-8') as f:
    asset_code = f.read()

new_func = """

/**
 * Modul: Report Unit Aset Terpilah per Komoditas
 * Proses: Menampilkan rekapitulasi dan tabel unit aset yang terstruktur & terpilah berdasarkan Komoditas (Asset Group).
 */
function handle_route_report_asset_items(PDO $pdo): void
{
    $user = require_login();
    if (!is_full_admin($user) && !has_regulation('asset_items', 'view', $user)) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
    ensure_asset_master_schema($pdo);

    $groupId = (int)($_GET['group_id'] ?? 0);
    $category = trim((string)($_GET['category'] ?? ''));
    $companyId = (int)($_GET['company_id'] ?? 0);
    $locationId = (int)($_GET['location_id'] ?? 0);
    $status = trim((string)($_GET['status'] ?? ''));
    $q = trim((string)($_GET['q'] ?? ''));

    $where = ["1=1"];
    $params = [];

    if ($groupId > 0) {
        $where[] = "ai.asset_group_id = ?";
        $params[] = $groupId;
    }
    if ($category !== '') {
        $where[] = "ai.asset_category = ?";
        $params[] = $category;
    }
    if ($companyId > 0) {
        $where[] = "ai.company_id = ?";
        $params[] = $companyId;
    }
    if ($locationId > 0) {
        $where[] = "ai.location_id = ?";
        $params[] = $locationId;
    }
    if ($status !== '') {
        $where[] = "ai.status = ?";
        $params[] = $status;
    }
    if ($q !== '') {
        $where[] = "(ai.asset_code LIKE ? OR ai.asset_name LIKE ? OR ai.brand_name LIKE ? OR ai.model LIKE ? OR ai.serial_number LIKE ? OR ai.owner_name LIKE ?)";
        $kw = '%' . $q . '%';
        $params = array_merge($params, [$kw, $kw, $kw, $kw, $kw, $kw]);
    }

    $whereSql = implode(' AND ', $where);

    $sql = "SELECT ai.*, 
            COALESCE(ag.group_code, 'IT') AS group_code,
            COALESCE(ag.group_name, 'IT Asset') AS group_name,
            ac.company_name,
            al.location_name
            FROM asset_items ai
            LEFT JOIN asset_groups ag ON ag.id = ai.asset_group_id
            LEFT JOIN asset_companies ac ON ac.id = ai.company_id
            LEFT JOIN asset_locations al ON al.id = ai.location_id
            WHERE {$whereSql}
            ORDER BY ag.group_code ASC, ag.group_name ASC, ai.asset_code ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [];
    $groupStats = [];
    $totalCount = count($allItems);

    foreach ($allItems as $item) {
        $gKey = $item['group_name'] . ' (' . $item['group_code'] . ')';
        if (!isset($grouped[$gKey])) {
            $grouped[$gKey] = [];
            $groupStats[$gKey] = 0;
        }
        $grouped[$gKey][] = $item;
        $groupStats[$gKey]++;
    }

    render_header('Report Unit Aset Terpilah per Komoditas', $user);

    $exportParams = array_merge(['type' => 'asset_items'], $_GET);
    $exportUrl = route_url('export_excel', $exportParams);

    echo '<div class="split" style="margin-bottom:16px;">';
    echo '  <div>';
    echo '    <h1 style="margin:0;">Report Unit Aset</h1>';
    echo '    <p class="muted" style="margin:4px 0 0 0;">Laporan rekapitulasi & detail Unit Aset terstruktur dan terpilah berdasarkan Komoditas (Grup Aset).</p>';
    echo '  </div>';
    echo '  <div class="actions no-print">';
    echo '    <a class="btn primary" href="' . e($exportUrl) . '">📥 Export Excel</a>';
    echo '    <button class="btn" onclick="window.print()">🖨️ Cetak Report</button>';
    echo '  </div>';
    echo '</div>';

    echo '<div class="grid three" style="margin-bottom:18px;">';
    echo '  <div class="stat"><h3 style="margin:0 0 4px 0;font-size:12px;color:#64748b;">TOTAL SELURUH UNIT ASET</h3><div style="font-size:28px;font-weight:800;color:#0f172a;">' . number_format($totalCount) . ' <span style="font-size:14px;font-weight:500;">Unit</span></div></div>';
    echo '  <div class="stat" style="grid-column: span 2;"><h3 style="margin:0 0 6px 0;font-size:12px;color:#64748b;">REKAPITULASI PER KOMODITAS (GRUP ASET)</h3>';
    echo '    <div style="display:flex;gap:8px;flex-wrap:wrap;">';
    if ($groupStats) {
        foreach ($groupStats as $gName => $count) {
            echo '<span class="badge ok" style="padding:6px 12px;font-size:12px;"><strong>' . e($gName) . ':</strong>&nbsp;' . $count . ' Unit</span>';
        }
    } else {
        echo '<span class="muted">Tidak ada data.</span>';
    }
    echo '    </div>';
    echo '  </div>';
    echo '</div>';

    echo '<section class="panel no-print" style="padding:14px 18px;margin-bottom:20px;">';
    echo '  <form method="get" class="actions" style="gap:12px;align-items:flex-end;">';
    echo '    <input type="hidden" name="route" value="report_asset_items">';
    echo '    <label style="margin:0;flex:1;min-width:180px;">Komoditas (Grup Aset)<select name="group_id" onchange="this.form.submit()">' . asset_group_options($pdo, $groupId, true, 'Semua Komoditas') . '</select></label>';
    echo '    <label style="margin:0;flex:1;min-width:180px;">Kategori Aset<select name="category" onchange="this.form.submit()">' . asset_category_options($pdo, $category, true, 'Semua Kategori') . '</select></label>';
    echo '    <label style="margin:0;flex:1;min-width:160px;">Company<select name="company_id" onchange="this.form.submit()">' . company_options($pdo, $companyId, true, 'Semua Company') . '</select></label>';
    echo '    <label style="margin:0;flex:1.2;min-width:200px;">Cari (Kode/Nama/Brand/SN)<input name="q" value="' . e($q) . '" placeholder="Ketik kata kunci..."></label>';
    echo '    <button class="btn primary" type="submit">Filter</button>';
    echo '    <a class="btn" href="' . route_url('report_asset_items') . '">Reset</a>';
    echo '  </form>';
    echo '</section>';

    if (empty($grouped)) {
        echo '<section class="panel"><p class="muted" style="text-align:center;padding:24px;">Tidak ada data unit aset yang sesuai dengan filter.</p></section>';
    } else {
        foreach ($grouped as $groupNameLabel => $items) {
            echo '<section class="panel" style="margin-bottom:24px;">';
            echo '  <div class="split" style="border-bottom:2px solid #3b82f6;padding-bottom:10px;margin-bottom:14px;">';
            echo '    <h2 style="margin:0;color:#1e3a8a;font-size:16px;">📦 Komoditas: ' . e($groupNameLabel) . '</h2>';
            echo '    <span class="badge ok" style="font-size:12px;">Total ' . count($items) . ' Unit</span>';
            echo '  </div>';
            echo '  <div style="overflow-x:auto;">';
            echo '    <table class="table">';
            echo '      <thead>';
            echo '        <tr>';
            echo '          <th style="width:40px;">No</th>';
            echo '          <th>Kode Unit</th>';
            echo '          <th>Nama Aset / Model</th>';
            echo '          <th>Brand / Merk</th>';
            echo '          <th>Kategori</th>';
            echo '          <th>Mode</th>';
            echo '          <th>Serial Number</th>';
            echo '          <th>Lokasi / Room</th>';
            echo '          <th>Company</th>';
            echo '          <th>Status</th>';
            echo '        </tr>';
            echo '      </thead>';
            echo '      <tbody>';
            $no = 1;
            foreach ($items as $item) {
                $rawMode = (string)($item['asset_mode'] ?? 'standalone');
                $modeLabel = $rawMode === 'group' ? 'Bundle (Parent)' : ($rawMode === 'child' ? 'Bundle (Child)' : 'Single');
                $statusBadge = ($item['status'] ?? 'available') === 'available' 
                    ? '<span class="badge ok">Tersedia</span>' 
                    : (($item['status'] ?? '') === 'borrowed' ? '<span class="badge" style="background:#fef3c7;color:#b45309;">Dipinjam</span>' : '<span class="badge">' . e($item['status'] ?? '-') . '</span>');

                echo '        <tr>';
                echo '          <td>' . $no++ . '</td>';
                echo '          <td><strong><a href="' . route_url('asset_items', ['q' => $item['asset_code']]) . '">' . e($item['asset_code']) . '</a></strong></td>';
                echo '          <td>' . e($item['asset_name'] ?: ($item['model'] ?: '-')) . '</td>';
                echo '          <td>' . e($item['brand_name'] ?: '-') . '</td>';
                echo '          <td>' . e($item['asset_category'] ?: '-') . '</td>';
                echo '          <td><span class="badge" style="font-size:10px;">' . e($modeLabel) . '</span></td>';
                echo '          <td><code>' . e($item['serial_number'] ?: '-') . '</code></td>';
                echo '          <td>' . e($item['location_name'] ?: ($item['owner_name'] ?: '-')) . '</td>';
                echo '          <td>' . e($item['company_name'] ?: '-') . '</td>';
                echo '          <td>' . $statusBadge . '</td>';
                echo '        </tr>';
            }
            echo '      </tbody>';
            echo '    </table>';
            echo '  </div>';
            echo '</section>';
        }
    }

    render_footer();
}
"""

if 'handle_route_report_asset_items' not in asset_code:
    asset_code += new_func
    with open(asset_file, 'w', encoding='utf-8') as f:
        f.write(asset_code)
    print("Appended handle_route_report_asset_items to app/modules/asset.php")

# 2. Update public/index.php
idx_file = 'public/index.php'
with open(idx_file, 'r', encoding='utf-8') as f:
    idx_code = f.read()

route_target = """    case 'report_asset_loans':
        handle_route_report_asset_loans($pdo);
        break;"""

route_replacement = """    case 'report_asset_loans':
        handle_route_report_asset_loans($pdo);
        break;
    case 'report_asset_items':
        handle_route_report_asset_items($pdo);
        break;"""

if route_target in idx_code and 'case \'report_asset_items\':' not in idx_code:
    idx_code = idx_code.replace(route_target, route_replacement)
    with open(idx_file, 'w', encoding='utf-8') as f:
        f.write(idx_code)
    print("Added case 'report_asset_items' to public/index.php")

# 3. Update app/lib/bootstrap.php
boot_file = 'app/lib/bootstrap.php'
with open(boot_file, 'r', encoding='utf-8') as f:
    boot_code = f.read()

# Update get_regulated_menus
old_reg = "'report_asset_loans' => 'Report Peminjaman Aset',"
new_reg = "'report_asset_loans' => 'Report Peminjaman Aset',\n            'report_asset_items' => 'Report Unit Aset',"

if 'report_asset_items' not in boot_code:
    boot_code = boot_code.replace(old_reg, new_reg)

# Update sidebar Reports accordion
old_acc = """        if ($isAdmin || has_regulation('asset_items', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'asset_items' ? ' active' : '') . '" href="' . route_url('asset_items') . '"><span class="nav-icon">' . $svgChart . '</span><span class="nav-label">Report Unit Aset</span></a>';
        }"""

new_acc = """        if ($isAdmin || has_regulation('asset_items', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'report_asset_items' ? ' active' : '') . '" href="' . route_url('report_asset_items') . '"><span class="nav-icon">' . $svgChart . '</span><span class="nav-label">Report Unit Aset</span></a>';
        }"""

if old_acc in boot_code:
    boot_code = boot_code.replace(old_acc, new_acc)

with open(boot_file, 'w', encoding='utf-8') as f:
    f.write(boot_code)
print("Updated bootstrap.php to link report_asset_items")

