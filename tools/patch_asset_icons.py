with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update in handle_route_report_asset_items:
old_asset_items_btn = """    echo '  <div class="actions no-print">';
    echo '    <a class="btn primary" href="' . e($exportUrl) . '">Export Excel</a>';
    $printUrl = route_url('report_asset_items', array_merge($_GET, ['print' => 1]));
    echo '    <a class="btn primary" href="' . e($printUrl) . '" target="_blank" style="padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;text-decoration:none;">PRINT</a>';
    echo '  </div>';"""

new_asset_items_btn = """    $printUrl = route_url('report_asset_items', array_merge($_GET, ['print' => 1]));
    echo '  <div class="actions no-print" style="display:inline-flex;gap:6px;align-items:center;">';
    echo '    ' . btn_icon_excel($exportUrl, 'Export Excel Unit Aset');
    echo '    ' . btn_icon_print($printUrl, 'Print Report Unit Aset');
    echo '  </div>';"""

# 2. Update in handle_route_asset_movements:
old_asset_mov_btn = """        . '  <div class="actions no-print">'
        . '    <a class="btn" href="' . route_url('export_excel', $exportParams) . '">Export Excel</a>'
        . '    <a class="btn primary" href="' . e(route_url('asset_movements', array_merge($_GET, ['print' => 1]))) . '" target="_blank" style="padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;text-decoration:none;">PRINT</a>'
        . '  </div>'"""

new_asset_mov_btn = """        . '  <div class="actions no-print" style="display:inline-flex;gap:6px;align-items:center;">'
        . '    ' . btn_icon_excel(route_url('export_excel', $exportParams), 'Export Excel Mutasi Aset')
        . '    ' . btn_icon_print(route_url('asset_movements', array_merge($_GET, ['print' => 1])), 'Print Report Mutasi Aset')
        . '  </div>'"""

print("Found old_asset_items_btn:", old_asset_items_btn in content)
print("Found old_asset_mov_btn:", old_asset_mov_btn in content)

if old_asset_items_btn in content and old_asset_mov_btn in content:
    content = content.replace(old_asset_items_btn, new_asset_items_btn, 1)
    content = content.replace(old_asset_mov_btn, new_asset_mov_btn, 1)
    with open('app/modules/asset.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print("SUCCESS: asset.php updated!")
else:
    print("FAILED to update asset.php")
