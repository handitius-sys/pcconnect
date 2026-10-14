import os

f = 'app/lib/bootstrap.php'
with open(f, 'r', encoding='utf-8') as file:
    c = file.read()

# 1. Update get_regulated_menus
old_reg = """        'Reports' => [
            'reports' => 'Report Preventive Maintenance',
            'corrective_repairs' => 'Report Corrective Maintenance',
            'asset_movements' => 'Report Mutasi Aset',
            'report_asset_loans' => 'Report Peminjaman Aset',
        ],"""

new_reg = """        'Reports' => [
            'reports' => 'Report Preventive Maintenance',
            'corrective_repairs' => 'Report Corrective Maintenance',
            'asset_movements' => 'Report Mutasi Aset',
            'report_asset_loans' => 'Report Peminjaman Aset',
            'asset_items' => 'Report Unit Aset',
        ],"""

if old_reg in c:
    c = c.replace(old_reg, new_reg)
    print("Updated get_regulated_menus")
else:
    print("WARNING: get_regulated_menus string not found")

# 2. Update Reports Accordion in render_header
old_rep_acc = """        // 3. Reports Accordion
        $reportRoutes = ['reports', 'corrective_repairs', 'asset_movements', 'asset_mutations', 'report_asset_loans'];
        $isReportOpen = in_array($curRoute, $reportRoutes, true);
        echo '<details class="nav-accordion"' . ($isReportOpen ? ' open' : '') . '>';
        echo '  <summary class="nav-item" title="Reports"><span class="nav-icon">' . $svgChart . '</span><span class="nav-label">Reports</span><span class="nav-chevron">▶</span></summary>';
        echo '  <div class="nav-submenu">';
        if ($isAdmin || has_regulation('reports', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'reports' ? ' active' : '') . '" href="' . route_url('reports') . '"><span class="nav-icon">' . $svgChart . '</span><span class="nav-label">Report Preventive Maintenance</span></a>';
        }
        if ($isAdmin || has_regulation('corrective_repairs', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'corrective_repairs' ? ' active' : '') . '" href="' . route_url('corrective_repairs') . '"><span class="nav-icon">' . $svgChart . '</span><span class="nav-label">Report Corrective Maintenance</span></a>';
        }
        if ($isAdmin || has_regulation('asset_movements', 'view', $user) || has_regulation('asset_mutations', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'asset_movements' || $curRoute === 'asset_mutations' ? ' active' : '') . '" href="' . route_url('asset_mutations') . '"><span class="nav-icon">' . $svgChart . '</span><span class="nav-label">Report Mutasi Aset</span></a>';
        }
        if ($isAdmin || has_regulation('asset_loans', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'report_asset_loans' ? ' active' : '') . '" href="' . route_url('report_asset_loans') . '"><span class="nav-icon">' . $svgChart . '</span><span class="nav-label">Report Peminjaman Aset</span></a>';
        }
        echo '  </div>';
        echo '</details>';"""

new_rep_acc = """        // 3. Reports Accordion
        $reportRoutes = ['reports', 'corrective_repairs', 'asset_movements', 'asset_mutations', 'report_asset_loans'];
        $isReportOpen = in_array($curRoute, $reportRoutes, true);
        echo '<details class="nav-accordion"' . ($isReportOpen ? ' open' : '') . '>';
        echo '  <summary class="nav-item" title="Reports"><span class="nav-icon">' . $svgChart . '</span><span class="nav-label">Reports</span><span class="nav-chevron">▶</span></summary>';
        echo '  <div class="nav-submenu">';
        if ($isAdmin || has_regulation('reports', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'reports' ? ' active' : '') . '" href="' . route_url('reports') . '"><span class="nav-icon">' . $svgChart . '</span><span class="nav-label">Report Preventive Maintenance</span></a>';
        }
        if ($isAdmin || has_regulation('corrective_repairs', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'corrective_repairs' ? ' active' : '') . '" href="' . route_url('corrective_repairs') . '"><span class="nav-icon">' . $svgChart . '</span><span class="nav-label">Report Corrective Maintenance</span></a>';
        }
        if ($isAdmin || has_regulation('asset_movements', 'view', $user) || has_regulation('asset_mutations', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'asset_movements' || $curRoute === 'asset_mutations' ? ' active' : '') . '" href="' . route_url('asset_mutations') . '"><span class="nav-icon">' . $svgChart . '</span><span class="nav-label">Report Mutasi Aset</span></a>';
        }
        if ($isAdmin || has_regulation('asset_loans', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'report_asset_loans' ? ' active' : '') . '" href="' . route_url('report_asset_loans') . '"><span class="nav-icon">' . $svgChart . '</span><span class="nav-label">Report Peminjaman Aset</span></a>';
        }
        if ($isAdmin || has_regulation('asset_items', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'asset_items' ? ' active' : '') . '" href="' . route_url('asset_items') . '"><span class="nav-icon">' . $svgChart . '</span><span class="nav-label">Report Unit Aset</span></a>';
        }
        echo '  </div>';
        echo '</details>';"""

if old_rep_acc in c:
    c = c.replace(old_rep_acc, new_rep_acc)
    print("Updated Reports Accordion")
else:
    print("WARNING: Reports Accordion string not found")

with open(f, 'w', encoding='utf-8') as file:
    file.write(c)

print("Finished updating bootstrap.php")

