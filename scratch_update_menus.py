import os

# 1. Update public/index.php
idx_file = 'public/index.php'
with open(idx_file, 'r', encoding='utf-8') as f:
    idx_content = f.read()

# Fix case 'asset_mutations'
idx_content = idx_content.replace(
"""    case 'asset_mutations':
        handle_route_asset_mutations($pdo);
        break;""",
"""    case 'asset_mutations':
    case 'asset_movements':
        handle_route_asset_movements($pdo);
        break;"""
)

with open(idx_file, 'w', encoding='utf-8') as f:
    f.write(idx_content)
print("Updated public/index.php successfully")


# 2. Update app/lib/bootstrap.php
boot_file = 'app/lib/bootstrap.php'
with open(boot_file, 'r', encoding='utf-8') as f:
    boot_content = f.read()

# Update get_regulated_menus()
old_regulated = """function get_regulated_menus(): array
{
    return [
        'Transaksi Aset' => [
            'asset_items' => 'Unit Aset',
            'pcs' => 'Pendataan PC',
            'asset_loans' => 'Peminjaman Aset',
            'asset_mutations' => 'Mutasi Aset',
        ],
        'Maintenance Aset' => [
            'maintenance' => 'Schedule PM',
            'tickets' => 'Tiket Corrective',
            'walkarounds' => 'Patroli Walkaround',
            'jobs' => 'Job Desk PM',
            'corrective_job_desks' => 'Job Desk Corrective',
            'mobile_service' => 'Mobile Service',
        ],
        'Reports & QR_Label' => [
            'asset_movements' => 'Report Mutasi Aset',
            'labels' => 'QR Label Unit',
        ],
        'Data Master' => [
            'asset_groups' => 'Master Komoditas',
            'asset_types' => 'Master Kategori',
            'asset_brands' => 'Master Brand / Merk',
            'asset_master_items' => 'Master Barang (Katalog Model)',
            'asset_identifiers' => 'Identifier Aset',
            'asset_specifications' => 'Spesifikasi Aset',
            'asset_locations' => 'Master Lokasi',
            'asset_companies' => 'Company',
            'master_pengguna' => 'Master Pengguna',
        ],
        'Pengaturan & Setup' => [
            'users' => 'Akses Users',
        ]
    ];
}"""

new_regulated = """function get_regulated_menus(): array
{
    return [
        'Transaksi Aset' => [
            'asset_items' => 'Unit Aset',
            'asset_loans' => 'Peminjaman Aset',
            'asset_mutations' => 'Mutasi Aset',
        ],
        'Maintenance Aset' => [
            'maintenance' => 'Schedule PM',
            'tickets' => 'Tiket Corrective',
            'walkarounds' => 'Patroli Walkaround',
            'jobs' => 'Job Desk PM',
            'corrective_job_desks' => 'Job Desk Corrective',
            'mobile_service' => 'Mobile Service',
        ],
        'Reports' => [
            'reports' => 'Report Preventive Maintenance',
            'corrective_repairs' => 'Report Corrective Maintenance',
            'asset_movements' => 'Report Mutasi Aset',
            'report_asset_loans' => 'Report Peminjaman Aset',
        ],
        'Data Master' => [
            'pcs' => 'Pendataan PC',
            'asset_groups' => 'Master Komoditas',
            'asset_types' => 'Master Kategori',
            'asset_brands' => 'Master Brand / Merk',
            'asset_master_items' => 'Master Barang (Katalog Model)',
            'asset_identifiers' => 'Identifier Aset',
            'asset_specifications' => 'Spesifikasi Aset',
            'asset_locations' => 'Master Lokasi',
            'asset_companies' => 'Company',
            'master_pengguna' => 'Master Pengguna',
        ],
        'Pengaturan & Setup' => [
            'labels' => 'QR Label Unit',
            'users' => 'Akses Users',
        ]
    ];
}"""

if old_regulated in boot_content:
    boot_content = boot_content.replace(old_regulated, new_regulated)

# Replace sidebar navigation rendering in render_header
# Transaksi Aset Accordion: remove Pendataan PC
old_trans_acc = """        // 1. Transaksi Aset Accordion (Unit Aset, Pendataan PC, Pinjam, Mutasi)
        $transRoutes = ['asset_items', 'pcs', 'asset_loans', 'mobile_asset_loans'];
        $isTransOpen = in_array($curRoute, $transRoutes, true);
        echo '<details class="nav-accordion"' . ($isTransOpen ? ' open' : '') . '>';
        echo '  <summary class="nav-item" title="Transaksi Aset"><span class="nav-icon">' . $svgBox . '</span><span class="nav-label">Transaksi Aset</span><span class="nav-chevron">▼</span></summary>';
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

new_trans_acc = """        // 1. Transaksi Aset Accordion (Unit Aset, Pinjam, Mutasi)
        $transRoutes = ['asset_items', 'asset_loans', 'mobile_asset_loans', 'asset_mutations'];
        $isTransOpen = in_array($curRoute, $transRoutes, true);
        echo '<details class="nav-accordion"' . ($isTransOpen ? ' open' : '') . '>';
        echo '  <summary class="nav-item" title="Transaksi Aset"><span class="nav-icon">' . $svgBox . '</span><span class="nav-label">Transaksi Aset</span><span class="nav-chevron">▼</span></summary>';
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

if old_trans_acc in boot_content:
    boot_content = boot_content.replace(old_trans_acc, new_trans_acc)

# Reports Accordion: rename to Reports, add Preventive, Corrective, Mutasi, Peminjaman reports
old_report_acc = """        // 3. Reports & QR_Label Accordion
        $reportRoutes = ['labels', 'report_asset_loans'];
        $isReportOpen = in_array($curRoute, $reportRoutes, true);
        echo '<details class="nav-accordion"' . ($isReportOpen ? ' open' : '') . '>';
        echo '  <summary class="nav-item" title="Reports & QR_Label"><span class="nav-icon">' . $svgChart . '</span><span class="nav-label">Reports & QR_Label</span><span class="nav-chevron">▼</span></summary>';
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

new_report_acc = """        // 3. Reports Accordion
        $reportRoutes = ['reports', 'corrective_repairs', 'asset_movements', 'asset_mutations', 'report_asset_loans'];
        $isReportOpen = in_array($curRoute, $reportRoutes, true);
        echo '<details class="nav-accordion"' . ($isReportOpen ? ' open' : '') . '>';
        echo '  <summary class="nav-item" title="Reports"><span class="nav-icon">' . $svgChart . '</span><span class="nav-label">Reports</span><span class="nav-chevron">▼</span></summary>';
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

if old_report_acc in boot_content:
    boot_content = boot_content.replace(old_report_acc, new_report_acc)

# Data Master Accordion: Add Pendataan PC at top
old_master_acc = """        // 4. Data Master Accordion (Posisinya di bawah Reports & QR_Label)
        $masterRoutes = ['asset_groups', 'asset_types', 'asset_brands', 'asset_master_items', 'asset_identifiers', 'asset_specifications', 'asset_locations', 'asset_companies', 'master_pengguna'];
        $isMasterOpen = in_array($curRoute, $masterRoutes, true);
        echo '<details class="nav-accordion"' . ($isMasterOpen ? ' open' : '') . '>';
        echo '  <summary class="nav-item" title="Data Master"><span class="nav-icon">' . $svgLayers . '</span><span class="nav-label">Data Master</span><span class="nav-chevron">▼</span></summary>';
        echo '  <div class="nav-submenu">';"""

new_master_acc = """        // 4. Data Master Accordion
        $masterRoutes = ['pcs', 'asset_groups', 'asset_types', 'asset_brands', 'asset_master_items', 'asset_identifiers', 'asset_specifications', 'asset_locations', 'asset_companies', 'master_pengguna'];
        $isMasterOpen = in_array($curRoute, $masterRoutes, true);
        echo '<details class="nav-accordion"' . ($isMasterOpen ? ' open' : '') . '>';
        echo '  <summary class="nav-item" title="Data Master"><span class="nav-icon">' . $svgLayers . '</span><span class="nav-label">Data Master</span><span class="nav-chevron">▼</span></summary>';
        echo '  <div class="nav-submenu">';
        if ($isAdmin || has_regulation('pcs', 'view', $user)) {
            echo '<a class="nav-item' . ($curRoute === 'pcs' ? ' active' : '') . '" href="' . route_url('pcs') . '"><span class="nav-icon">' . $svgPc . '</span><span class="nav-label">Pendataan PC</span></a>';
        }"""

if old_master_acc in boot_content:
    boot_content = boot_content.replace(old_master_acc, new_master_acc)

# Setup Accordion: Add QR Label Unit (labels)
old_setup_acc = """        // 5. Setup Accordion
        if ($isAdmin || has_regulation('users', 'view', $user)) {
            $setupRoutes = ['users', 'setup_regulations', 'employee_source'];
            $isSetupOpen = in_array($curRoute, $setupRoutes, true);
            echo '<details class="nav-accordion"' . ($isSetupOpen ? ' open' : '') . '>';
            echo '  <summary class="nav-item" title="Pengaturan & Setup"><span class="nav-icon">' . $svgCog . '</span><span class="nav-label">Pengaturan & Setup</span><span class="nav-chevron">▼</span></summary>';
            echo '  <div class="nav-submenu">';
            if ($isAdmin || has_regulation('users', 'view', $user)) {
                echo '<a class="nav-item' . ($curRoute === 'users' ? ' active' : '') . '" href="' . route_url('users') . '"><span class="nav-icon">' . $svgUsers . '</span><span class="nav-label">Akses Users</span></a>';
            }"""

new_setup_acc = """        // 5. Setup Accordion
        if ($isAdmin || has_regulation('users', 'view', $user) || has_regulation('labels', 'view', $user)) {
            $setupRoutes = ['labels', 'users', 'setup_regulations', 'employee_source'];
            $isSetupOpen = in_array($curRoute, $setupRoutes, true);
            echo '<details class="nav-accordion"' . ($isSetupOpen ? ' open' : '') . '>';
            echo '  <summary class="nav-item" title="Pengaturan & Setup"><span class="nav-icon">' . $svgCog . '</span><span class="nav-label">Pengaturan & Setup</span><span class="nav-chevron">▼</span></summary>';
            echo '  <div class="nav-submenu">';
            if ($isAdmin || has_regulation('labels', 'view', $user)) {
                echo '<a class="nav-item' . ($curRoute === 'labels' ? ' active' : '') . '" href="' . route_url('labels') . '"><span class="nav-icon">' . $svgQr . '</span><span class="nav-label">QR Label Unit</span></a>';
            }
            if ($isAdmin || has_regulation('users', 'view', $user)) {
                echo '<a class="nav-item' . ($curRoute === 'users' ? ' active' : '') . '" href="' . route_url('users') . '"><span class="nav-icon">' . $svgUsers . '</span><span class="nav-label">Akses Users</span></a>';
            }"""

if old_setup_acc in boot_content:
    boot_content = boot_content.replace(old_setup_acc, new_setup_acc)

with open(boot_file, 'w', encoding='utf-8') as f:
    f.write(boot_content)
print("Updated app/lib/bootstrap.php successfully")

