import re

with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update table header in asset_items_table
old_th_pattern = r"""    \$html \.= '<div style="overflow-x:auto;"><table id="assetItemsTable" class="sortable-table">';\n    \$html \.= '<thead><tr>';\n    \$html \.= '<th class="sortable" onclick="sortAssetTable\(0\)" style="cursor:pointer;" title="Klik untuk mengurutkan">Kode Aset <span class="sort-ind">.*?</span></th>';\n    \$html \.= '<th class="sortable" onclick="sortAssetTable\(1\)" style="cursor:pointer;" title="Klik untuk mengurutkan">Kode Lama \(Legacy\) <span class="sort-ind">.*?</span></th>';\n    \$html \.= '<th class="sortable" onclick="sortAssetTable\(2\)" style="cursor:pointer;" title="Klik untuk mengurutkan">Company <span class="sort-ind">.*?</span></th>';\n    \$html \.= '<th class="sortable" onclick="sortAssetTable\(3\)" style="cursor:pointer;" title="Klik untuk mengurutkan">Pengguna <span class="sort-ind">.*?</span></th>';\n    \$html \.= '<th class="sortable" onclick="sortAssetTable\(4\)" style="cursor:pointer;" title="Klik untuk mengurutkan">Kategori <span class="sort-ind">.*?</span></th>';\n    \$html \.= '<th class="sortable" onclick="sortAssetTable\(5\)" style="cursor:pointer;" title="Klik untuk mengurutkan">Mode Asset <span class="sort-ind">.*?</span></th>';\n    \$html \.= '<th class="sortable" onclick="sortAssetTable\(6\)" style="cursor:pointer;" title="Klik untuk mengurutkan">Nama Barang <span class="sort-ind">.*?</span></th>';\n    \$html \.= '<th class="sortable" onclick="sortAssetTable\(7\)" style="cursor:pointer;" title="Klik untuk mengurutkan">Status <span class="sort-ind">.*?</span></th>';\n    \$html \.= '<th class="sortable" onclick="sortAssetTable\(8\)" style="cursor:pointer;" title="Klik untuk mengurutkan">Tanggal Input <span class="sort-ind">.*?</span></th>';\n    \$html \.= '<th style="text-align:center;width:124px;">Aksi</th>';\n    \$html \.= '</tr></thead><tbody>';"""

new_th = """    $html .= '<div style="overflow-x:auto;"><table id="assetItemsTable" class="sortable-table">';
    $html .= '<thead><tr>';
    $html .= '<th>Kode Aset</th>';
    $html .= '<th>Kode Lama (Legacy)</th>';
    $html .= '<th>Company</th>';
    $html .= '<th>Pengguna</th>';
    $html .= '<th>Kategori</th>';
    $html .= '<th>Mode Asset</th>';
    $html .= '<th>Nama Barang</th>';
    $html .= '<th>Status</th>';
    $html .= '<th>Tanggal Input</th>';
    $html .= '<th style="text-align:center;width:124px;" data-nosort="1">Aksi</th>';
    $html .= '</tr></thead><tbody>';"""

match_th = re.search(old_th_pattern, content)
print("Found old_th:", bool(match_th))

# 2. Update Kode Aset cell
old_cell = "$html .= '<td><a href=\"' . route_url('asset_item_form', ['id' => $row['id'], 'view' => 1]) . '\" style=\"color:#0f172a;text-decoration:none;\" title=\"Klik untuk melihat detail unit aset (Read-Only)\"><strong>' . e($row['asset_code']) . '</strong></a><br><span class=\"muted\">SN: ' . e($row['serial_number'] ?: '-') . '</span>' . $sourcePcBadge . $idfHtml . '</td>';"
new_cell = "$html .= '<td><a href=\"' . route_url('asset_item_form', ['id' => $row['id'], 'view' => 1]) . '\" style=\"color:#0f172a;text-decoration:none;\" title=\"Klik untuk melihat detail unit aset (Read-Only)\"><strong>' . e($row['asset_code']) . '</strong></a><br><span class=\"muted\">SN: ' . e($row['serial_number'] ?: '-') . '</span></td>';"
print("Found old_cell:", old_cell in content)

# 3. Update sortAssetTable script removal
old_script_pattern = r"""    // Client-side Sorting Script\n    \$html \.= '<script>\n    function sortAssetTable\(colIndex\) \{.*?</script>';"""
match_script = re.search(old_script_pattern, content, flags=re.DOTALL)
print("Found old_script:", bool(match_script))

if match_th and old_cell in content and match_script:
    content = content[:match_th.start()] + new_th + content[match_th.end():]
    content = content.replace(old_cell, new_cell, 1)
    # Find match_script again in modified content
    match_script = re.search(old_script_pattern, content, flags=re.DOTALL)
    content = content[:match_script.start()] + content[match_script.end():]
    
    with open('app/modules/asset.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print("SUCCESS: asset.php updated successfully!")
else:
    print("FAILED: some targets not found")
