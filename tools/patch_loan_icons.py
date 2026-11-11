with open('app/modules/asset_loan.php', 'r', encoding='utf-8') as f:
    content = f.read()

old_loan_btn = """        . '  <div class="actions no-print">'
        . '    <a class="btn" href="' . route_url('export_excel', $exportParams) . '">Export Excel</a>'
        . '    <a class="btn primary" href="' . e(route_url('report_asset_loans', array_merge($_GET, ['print' => 1]))) . '" target="_blank" style="padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;text-decoration:none;">PRINT</a>'
        . '  </div>'"""

new_loan_btn = """        . '  <div class="actions no-print" style="display:inline-flex;gap:6px;align-items:center;">'
        . '    ' . btn_icon_excel(route_url('export_excel', $exportParams), 'Export Excel Peminjaman Aset')
        . '    ' . btn_icon_print(route_url('report_asset_loans', array_merge($_GET, ['print' => 1])), 'Print Report Peminjaman Aset')
        . '  </div>'"""

print("Found old_loan_btn:", old_loan_btn in content)

if old_loan_btn in content:
    content = content.replace(old_loan_btn, new_loan_btn, 1)
    with open('app/modules/asset_loan.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print("SUCCESS: asset_loan.php updated!")
else:
    print("FAILED to update asset_loan.php")
