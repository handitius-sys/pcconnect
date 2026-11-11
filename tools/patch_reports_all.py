# Patch standalone print logic into asset.php, asset_loan.php, maintenance.php, corrective.php

def patch_asset():
    with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
        c = f.read()

    # 1. handle_route_report_asset_items
    target1 = '    // 5. Pengelompokan Data & Agregasi Berdasarkan Komoditas'
    rep1 = '''    // Jika mode cetak khusus aktif (&print=1), susun ulang dan tampilkan halaman cetak resmi
    if (!empty($_GET['print'])) {
        $headers = ['No', 'Komoditas', 'Kode Unit', 'Kode Lama', 'Nama Aset / Model', 'Brand / Merk', 'Kategori', 'Serial Number', 'Lokasi / Room', 'Company', 'Status'];
        $printRows = [];
        $no = 1;
        foreach ($allItems as $item) {
            $gName = trim((string)($item['group_name'] ?? ''));
            $gCode = trim((string)($item['group_code'] ?? ''));
            $komoditasLabel = $gName . ($gCode !== '' ? ' (' . $gCode . ')' : '');
            $assetName = !empty($item['asset_name']) ? $item['asset_name'] : (!empty($item['model']) ? $item['model'] : '-');
            $brandName = !empty($item['brand_name']) ? $item['brand_name'] : (!empty($item['brand']) ? $item['brand'] : '-');
            $categoryName = !empty($item['display_type_name']) ? $item['display_type_name'] : (!empty($item['asset_type']) ? $item['asset_type'] : '-');
            $locName = !empty($item['display_location']) ? $item['display_location'] : (!empty($item['location_label']) ? $item['location_label'] : '-');
            $statusLabel = ucfirst(str_replace('_', ' ', (string)($item['status'] ?? '-')));

            $printRows[] = [
                $no++,
                $komoditasLabel,
                (string)($item['asset_code'] ?? '-'),
                (string)($item['alias_code'] ?? '-'),
                $assetName,
                $brandName,
                $categoryName,
                (string)($item['serial_number'] ?? '-'),
                $locName,
                (string)($item['company_name'] ?? '-'),
                $statusLabel
            ];
        }
        render_report_print_standalone_page('Laporan Inventaris Unit Aset', 'Per ' . date('F Y'), $headers, $printRows, $pdo);
    }

    // 5. Pengelompokan Data & Agregasi Berdasarkan Komoditas'''

    assert target1 in c, 'target1 not found in asset.php'
    c = c.replace(target1, rep1, 1)

    # Button 1 in report_asset_items
    target_btn1 = "echo '    <button type=\"button\" class=\"btn primary\" onclick=\"window.print()\" style=\"padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;\">PRINT</button>';"
    rep_btn1 = "$printUrl = route_url('report_asset_items', array_merge($_GET, ['print' => 1]));\n    echo '    <a class=\"btn primary\" href=\"' . e($printUrl) . '\" target=\"_blank\" style=\"padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;text-decoration:none;\">PRINT</a>';"
    assert target_btn1 in c, 'target_btn1 not found in asset.php'
    c = c.replace(target_btn1, rep_btn1, 1)

    # 2. handle_route_asset_movements
    target_mov = "$rows = asset_movement_rows($pdo, $q, $startDate, $endDate);"
    rep_mov = '''$rows = asset_movement_rows($pdo, $q, $startDate, $endDate);

    if (!empty($_GET['print'])) {
        $headers = ['No', 'Tanggal', 'Aksi / Status Mutasi', 'Unit Aset', 'Bundle Asal', 'Bundle Tujuan', 'Company', 'Alasan / Keterangan', 'PIC / Petugas'];
        $printRows = [];
        $no = 1;
        foreach ($rows as $row) {
            $fromPid = (int)($row['from_parent_asset_item_id'] ?? 0);
            $toPid = (int)($row['to_parent_asset_item_id'] ?? 0);
            if ($fromPid > 0 && $toPid > 0) {
                $statusTxt = 'Pindah Bundle';
            } elseif ($toPid > 0) {
                $statusTxt = 'Dipasang ke Bundle';
            } elseif ($fromPid > 0) {
                $statusTxt = 'Dilepas (Kembali Single)';
            } elseif (!empty($row['to_company_id']) || !empty($row['from_company_id'])) {
                $statusTxt = 'Mutasi Perusahaan';
            } else {
                $statusTxt = 'Mutasi Standalone';
            }

            $assetTxt = trim((string)($row['asset_code'] ?? ''));
            if (!empty($row['alias_code'])) {
                $assetTxt .= ' [Lama: ' . $row['alias_code'] . ']';
            }
            if (!empty($row['asset_name'])) {
                $assetTxt .= ' - ' . $row['asset_name'];
            }

            $fromBundleTxt = !empty($row['from_bundle']) ? $row['from_bundle'] : (!empty($row['from_parent_code']) ? $row['from_parent_code'] : '-');
            $toBundleTxt = !empty($row['to_bundle']) ? $row['to_bundle'] : (!empty($row['to_parent_code']) ? $row['to_parent_code'] : '-');

            $compTxt = '-';
            if (!empty($row['from_company']) && !empty($row['to_company'])) {
                $compTxt = $row['from_company'] . ' -> ' . $row['to_company'];
            } elseif (!empty($row['to_company'])) {
                $compTxt = $row['to_company'];
            } elseif (!empty($row['from_company'])) {
                $compTxt = $row['from_company'];
            }

            $printRows[] = [
                $no++,
                (string)($row['movement_date'] ?? '-'),
                $statusTxt,
                $assetTxt,
                $fromBundleTxt,
                $toBundleTxt,
                $compTxt,
                (string)($row['reason'] ?? '-'),
                (string)($row['pic'] ?? '-')
            ];
        }
        render_report_print_standalone_page('Laporan Riwayat Mutasi Aset', 'Per ' . date('F Y'), $headers, $printRows, $pdo);
    }'''

    assert target_mov in c, 'target_mov not found in asset.php'
    c = c.replace(target_mov, rep_mov, 1)

    target_btn2 = ". '    <button type=\"button\" class=\"btn primary\" onclick=\"window.print()\" style=\"padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;\">PRINT</button>'"
    rep_btn2 = ". '    <a class=\"btn primary\" href=\"' . e(route_url('asset_movements', array_merge($_GET, ['print' => 1]))) . '\" target=\"_blank\" style=\"padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;text-decoration:none;\">PRINT</a>'"

    assert target_btn2 in c, 'target_btn2 not found in asset.php'
    c = c.replace(target_btn2, rep_btn2, 1)

    with open('app/modules/asset.php', 'w', encoding='utf-8') as f:
        f.write(c)
    print("asset.php patched successfully.")

patch_asset()

