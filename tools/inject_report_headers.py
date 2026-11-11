# Add render_report_document_header to the 5 report pages:
# 1. app/modules/asset.php (report_asset_items & asset_movements)
# 2. app/modules/asset_loan.php (report_asset_loans)
# 3. app/modules/maintenance.php (reports)
# 4. app/modules/corrective.php (corrective_repairs)

# --- 1. app/modules/asset.php ---
with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    c = f.read()

# For report_asset_items:
# find: render_report_print_settings_modal();
target1 = "render_report_print_settings_modal();"
if target1 in c:
    # replace first occurrence or specific in report_asset_items
    c = c.replace(
        "render_header('Report Unit Aset Terpilah per Komoditas', $user);",
        "render_header('Report Unit Aset Terpilah per Komoditas', $user);\n    render_report_document_header('Laporan Inventaris Unit Aset', 'Per ' . date('F Y'), $pdo);"
    )

# For asset_movements:
c = c.replace(
    "render_header('Report Mutasi Aset', $user);",
    "render_header('Report Mutasi Aset', $user);\n    render_report_document_header('Laporan Riwayat Mutasi Aset', 'Per ' . date('F Y'), $pdo);"
)

with open('app/modules/asset.php', 'w', encoding='utf-8') as f:
    f.write(c)
print("asset.php updated with render_report_document_header")

# --- 2. app/modules/asset_loan.php ---
with open('app/modules/asset_loan.php', 'r', encoding='utf-8') as f:
    c = f.read()

c = c.replace(
    "render_header('Report Peminjaman Aset', $user);",
    "render_header('Report Peminjaman Aset', $user);\n    render_report_document_header('Laporan Peminjaman Aset', 'Per ' . date('F Y'), $pdo);"
)

with open('app/modules/asset_loan.php', 'w', encoding='utf-8') as f:
    f.write(c)
print("asset_loan.php updated with render_report_document_header")

# --- 3. app/modules/maintenance.php ---
with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    c = f.read()

c = c.replace(
    "render_header('Report Maintenance', $user);",
    "render_header('Report Maintenance', $user);\n    render_report_document_header('Laporan Preventive Maintenance', 'Per ' . date('F Y'), $pdo);"
)

with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
    f.write(c)
print("maintenance.php updated with render_report_document_header")

# --- 4. app/modules/corrective.php ---
with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    c = f.read()

c = c.replace(
    "render_header('Riwayat Reparasi & Sparepart', $user);",
    "render_header('Riwayat Reparasi & Sparepart', $user);\n    render_report_document_header('Laporan Corrective Maintenance (Reparasi & Sparepart)', 'Per ' . date('F Y'), $pdo);"
)

with open('app/modules/corrective.php', 'w', encoding='utf-8') as f:
    f.write(c)
print("corrective.php updated with render_report_document_header")

