import os

f = 'app/lib/bootstrap.php'
with open(f, 'r', encoding='utf-8') as file:
    c = file.read()

# 1. Transaksi Aset Accordion
search_trans = """        // 1. Transaksi Aset Accordion (Unit Aset, Pendataan PC, Pinjam, Mutasi)
        $transRoutes = ['asset_items', 'pcs', 'asset_loans', 'mobile_asset_loans'];
        $isTransOpen = in_array($curRoute, $transRoutes, true);
        echo '<details class="nav-accordion"' . ($isTransOpen ? ' open' : '') . '>';
        echo '  <summary class="nav-item" title="Transaksi Aset"><span class="nav-icon">' . $svgBox . '</span><span class="nav-label">Transaksi Aset</span><span class="nav-chevron">▶</span></summary>';
        echo '  <div class="nav-submenu">';
        if ($isAdmin || has_regulation('asset_items', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'asset_items' ? ' active' : '') . '" href="' . route_url('asset_items') . '"><span class="nav-icon">' . $svgBox . '</span><span class="nav-label">Unit Aset</span></a>';
        }
        if ($isAdmin || has_regulation('pcs', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'pcs' ? ' active' : '') . '" href="' . route_url('pcs') . '"><span class="nav-icon">' . $svgPc . '</span><span class="nav-label">Pendataan PC</span></a>';
        }
        if ($isAdmin || has_regulation('asset_loans', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'asset_loans' ? ' active' : '') . '" href="' . route_url('asset_loans') . '"><span class="nav-icon">' . $svgLoan . '</span><span class="nav-label">Peminjaman Aset</span></a>';
            echo '<a class="nav-item" href="' . route_url('mobile_asset_loans') . '" target="_blank"><span class="nav-icon">' . $svgMobile . '</span><span class="nav-label">📱 Mobile Pinjam</span><span class="nav-badge">PWA</span></a>';
        }

        if ($isAdmin || has_regulation('asset_mutations', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'asset_mutations' ? ' active' : '') . '" href="' . route_url('asset_mutations') . '"><span class="nav-icon">' . $svgMove . '</span><span class="nav-label">Mutasi Aset</span></a>';
        }

        echo '  </div>';
        echo '</details>';"""

replace_trans = """        // 1. Transaksi Aset Accordion (Unit Aset, Pinjam, Mutasi)
        $transRoutes = ['asset_items', 'asset_loans', 'mobile_asset_loans', 'asset_mutations'];
        $isTransOpen = in_array($curRoute, $transRoutes, true);
        echo '<details class="nav-accordion"' . ($isTransOpen ? ' open' : '') . '>';
        echo '  <summary class="nav-item" title="Transaksi Aset"><span class="nav-icon">' . $svgBox . '</span><span class="nav-label">Transaksi Aset</span><span class="nav-chevron">▶</span></summary>';
        echo '  <div class="nav-submenu">';
        if ($isAdmin || has_regulation('asset_items', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'asset_items' ? ' active' : '') . '" href="' . route_url('asset_items') . '"><span class="nav-icon">' . $svgBox . '</span><span class="nav-label">Unit Aset</span></a>';
        }
        if ($isAdmin || has_regulation('asset_loans', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'asset_loans' ? ' active' : '') . '" href="' . route_url('asset_loans') . '"><span class="nav-icon">' . $svgLoan . '</span><span class="nav-label">Peminjaman Aset</span></a>';
            echo '<a class="nav-item" href="' . route_url('mobile_asset_loans') . '" target="_blank"><span class="nav-icon">' . $svgMobile . '</span><span class="nav-label">📱 Mobile Pinjam</span><span class="nav-badge">PWA</span></a>';
        }
        if ($isAdmin || has_regulation('asset_mutations', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'asset_mutations' ? ' active' : '') . '" href="' . route_url('asset_mutations') . '"><span class="nav-icon">' . $svgMove . '</span><span class="nav-label">Mutasi Aset</span></a>';
        }

        echo '  </div>';
        echo '</details>';"""

if search_trans in c:
    c = c.replace(search_trans, replace_trans)
    print("Replaced Transaksi Aset")
else:
    print("ERROR: Transaksi Aset search not found")

# 2. Reports Accordion
search_reports = """        // 3. Reports & QR_Label Accordion
        $reportRoutes = ['labels', 'report_asset_loans'];
        $isReportOpen = in_array($curRoute, $reportRoutes, true);
        echo '<details class="nav-accordion"' . ($isReportOpen ? ' open' : '') . '>';
        echo '  <summary class="nav-item" title="Reports & QR_Label"><span class="nav-icon">' . $svgChart . '</span><span class="nav-label">Reports & QR_Label</span><span class="nav-chevron">▶</span></summary>';
        echo '  <div class="nav-submenu">';
        if ($isAdmin || has_regulation('labels', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'labels' ? ' active' : '') . '" href="' . route_url('labels') . '"><span class="nav-icon">' . $svgQr . '</span><span class="nav-label">QR Label Unit</span></a>';
        }
        if ($isAdmin || has_regulation('asset_movements', 'view', $user)) {
              echo '<a class="nav-item' . ($curRoute === 'asset_movements' ? ' active' : '') . '" href="' . route_url('asset_movements') . '"><span class="nav-icon">' . $svgChart . '</span><span class="nav-label">Report Mutasi</span></a>';
          }
          
        
        
        echo '<a class="nav-item' . ($curRoute === 'report_asset_loans' ? ' active' : '') . '" href="' . route_url('report_asset_loans') . '"><span class="nav-icon">' . $svgChart . '</span><span class="nav-label">Report Peminjaman</span></a>';
        echo '  </div>';
        echo '</details>';"""

replace_reports = """        // 3. Reports Accordion
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

if search_reports in c:
    c = c.replace(search_reports, replace_reports)
    print("Replaced Reports Accordion")
else:
    print("ERROR: Reports Accordion search not found")

# 3. Data Master Accordion
search_master = """        // 4. Data Master Accordion (Posisinya di bawah Reports & QR_Label)
        $masterRoutes = ['asset_groups', 'asset_types', 'asset_brands', 'asset_master_items', 'asset_identifiers', 'asset_specifications', 'asset_locations', 'asset_companies', 'master_pengguna'];
        $isMasterOpen = in_array($curRoute, $masterRoutes, true);
        echo '<details class="nav-accordion"' . ($isMasterOpen ? ' open' : '') . '>';
        echo '  <summary class="nav-item" title="Data Master"><span class="nav-icon">' . $svgLayers . '</span><span class="nav-label">Data Master</span><span class="nav-chevron">▶</span></summary>';
        echo '  <div class="nav-submenu">';"""

replace_master = """        // 4. Data Master Accordion
        $masterRoutes = ['pcs', 'asset_groups', 'asset_types', 'asset_brands', 'asset_master_items', 'asset_identifiers', 'asset_specifications', 'asset_locations', 'asset_companies', 'master_pengguna'];
        $isMasterOpen = in_array($curRoute, $masterRoutes, true);
        echo '<details class="nav-accordion"' . ($isMasterOpen ? ' open' : '') . '>';
        echo '  <summary class="nav-item" title="Data Master"><span class="nav-icon">' . $svgLayers . '</span><span class="nav-label">Data Master</span><span class="nav-chevron">▶</span></summary>';
        echo '  <div class="nav-submenu">';
        if ($isAdmin || has_regulation('pcs', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'pcs' ? ' active' : '') . '" href="' . route_url('pcs') . '"><span class="nav-icon">' . $svgPc . '</span><span class="nav-label">Pendataan PC</span></a>';
        }"""

if search_master in c:
    c = c.replace(search_master, replace_master)
    print("Replaced Data Master Accordion")
else:
    print("ERROR: Data Master Accordion search not found")

# 4. Setup Accordion
search_setup = """        // 5. Setup Accordion
        if ($isAdmin || has_regulation('users', 'view', $user)) {
            $setupRoutes = ['users', 'setup_regulations', 'employee_source'];
            $isSetupOpen = in_array($curRoute, $setupRoutes, true);
            echo '<details class="nav-accordion"' . ($isSetupOpen ? ' open' : '') . '>';
            echo '  <summary class="nav-item" title="Pengaturan & Setup"><span class="nav-icon">' . $svgCog . '</span><span class="nav-label">Pengaturan & Setup</span><span class="nav-chevron">▶</span></summary>';
            echo '  <div class="nav-submenu">';
            if ($isAdmin || has_regulation('users', 'view', $user)) {
                echo '<a class="nav-item' . ($curRoute === 'users' ? ' active' : '') . '" href="' . route_url('users') . '"><span class="nav-icon">' . $svgUsers . '</span><span class="nav-label">Akses Users</span></a>';
            }"""

replace_setup = """        // 5. Setup Accordion
        if ($isAdmin || has_regulation('users', 'view', $user) || has_regulation('labels', 'view', $user)) {
            $setupRoutes = ['labels', 'users', 'setup_regulations', 'employee_source'];
            $isSetupOpen = in_array($curRoute, $setupRoutes, true);
            echo '<details class="nav-accordion"' . ($isSetupOpen ? ' open' : '') . '>';
            echo '  <summary class="nav-item" title="Pengaturan & Setup"><span class="nav-icon">' . $svgCog . '</span><span class="nav-label">Pengaturan & Setup</span><span class="nav-chevron">▶</span></summary>';
            echo '  <div class="nav-submenu">';
            if ($isAdmin || has_regulation('labels', 'view', $user)) {
                echo '<a class="nav-item' . ($curRoute === 'labels' ? ' active' : '') . '" href="' . route_url('labels') . '"><span class="nav-icon">' . $svgQr . '</span><span class="nav-label">QR Label Unit</span></a>';
            }
            if ($isAdmin || has_regulation('users', 'view', $user)) {
                echo '<a class="nav-item' . ($curRoute === 'users' ? ' active' : '') . '" href="' . route_url('users') . '"><span class="nav-icon">' . $svgUsers . '</span><span class="nav-label">Akses Users</span></a>';
            }"""

if search_setup in c:
    c = c.replace(search_setup, replace_setup)
    print("Replaced Setup Accordion")
else:
    print("ERROR: Setup Accordion search not found")

with open(f, 'w', encoding='utf-8') as file:
    file.write(c)

print("Done updating bootstrap.php")

