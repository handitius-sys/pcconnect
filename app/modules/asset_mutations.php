<?php
declare(strict_types=1);

/**
 * Modul: Mutasi Aset (Asset Mutations)
 * Proses: Mengelola proses mutasi unit aset fisik khusus untuk 3 dimensi:
 * 1. Mutasi antar Company (Perusahaan)
 * 2. Mutasi antar Lokasi (Master Lokasi & Detail Ruangan)
 * 3. Mutasi antar Pengguna (Master Karyawan / Custodian)
 * 
 * Pengguna diwajibkan memilih Komoditas dan Kategori (asset_type) terlebih dahulu
 * sebelum menampilkan dan memilih unit aset yang akan dimutasi.
 */

function handle_route_asset_mutations(PDO $pdo): void
{
    $user = require_login();
    if (!is_full_admin($user) && !has_regulation('asset_mutations', 'view', $user)) {
        http_response_code(403);
        exit('Akses ditolak.');
    }

    if (function_exists('ensure_asset_master_schema')) {
        ensure_asset_master_schema($pdo);
    }

    $action = (string)($_POST['action'] ?? '');

    // =========================================================================
    // 1. PROSES POST SIMPAN MUTASI ASET (COMPANY / LOKASI / PENGGUNA)
    // =========================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'mutate') {
        verify_csrf();
        require_regulation('asset_mutations', 'create');

        $assetItemId = (int)($_POST['asset_item_id'] ?? 0);
        $rawCompany = trim((string)($_POST['new_company_id'] ?? 'keep'));
        $rawLocation = trim((string)($_POST['new_location_id'] ?? 'keep'));
        $newLocationLabel = trim((string)($_POST['new_location_label'] ?? ''));
        $hasCustodianInput = isset($_POST['new_custodian_name']);
        $newCustodianNik = trim((string)($_POST['new_custodian_nik'] ?? ''));
        $newCustodianName = trim((string)($_POST['new_custodian_name'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));
        $pic = trim((string)($_POST['pic'] ?? ($user['name'] ?? 'Admin')));
        $date = normalize_date_input((string)($_POST['movement_date'] ?? date('Y-m-d')));

        $item = asset_item_row($pdo, $assetItemId);
        if (!$item) {
            flash('Unit aset tidak ditemukan.', 'err');
            redirect_to('asset_mutations', $_GET);
        }

        $oldCompanyId = !empty($item['company_id']) ? (int)$item['company_id'] : 0;
        $oldLocationId = !empty($item['location_id']) ? (int)$item['location_id'] : 0;
        $oldLocationLabel = (string)($item['location_label'] ?? '');
        $oldCustodianNik = (string)($item['custodian_nik'] ?? '');
        $oldCustodianName = (string)($item['custodian_name'] ?? '');

        // 1. Deteksi Perubahan Company
        $companyChanged = false;
        $targetCompanyId = $oldCompanyId > 0 ? $oldCompanyId : null;
        if ($rawCompany !== 'keep') {
            if ($rawCompany === 'none' || $rawCompany === '0' || $rawCompany === '') {
                $companyChanged = true;
                $targetCompanyId = null;
            } elseif (is_numeric($rawCompany) && (int)$rawCompany > 0) {
                $compVal = (int)$rawCompany;
                $companyChanged = true;
                $targetCompanyId = $compVal;
            }
        }

        // 2. Deteksi Perubahan Lokasi
        $locationChanged = false;
        $targetLocationId = $oldLocationId > 0 ? $oldLocationId : null;
        $targetLocationLabel = $oldLocationLabel;
        if ($rawLocation !== 'keep') {
            if ($rawLocation === 'none' || $rawLocation === '0' || $rawLocation === '') {
                $locationChanged = true;
                $targetLocationId = null;
            } elseif (is_numeric($rawLocation) && (int)$rawLocation > 0) {
                $locVal = (int)$rawLocation;
                $locationChanged = true;
                $targetLocationId = $locVal;
                if ($newLocationLabel === '' && db_table_exists($pdo, 'asset_locations')) {
                    $locName = (string)$pdo->query("SELECT location_name FROM asset_locations WHERE id = {$locVal}")->fetchColumn() ?: '';
                    if ($locName !== '') {
                        $targetLocationLabel = $locName;
                    }
                }
            }
        }
        if ($newLocationLabel !== '' && $newLocationLabel !== $oldLocationLabel) {
            $locationChanged = true;
            $targetLocationLabel = $newLocationLabel;
        }

        // 3. Deteksi Perubahan Pengguna
        $custodianChanged = false;
        $targetCustodianNik = $oldCustodianNik;
        $targetCustodianName = $oldCustodianName;
        if ($hasCustodianInput) {
            $normNewCustName = ($newCustodianName === '-' ? '' : $newCustodianName);
            $normOldCustName = ($oldCustodianName === '-' ? '' : $oldCustodianName);
            if ($normNewCustName !== $normOldCustName || $newCustodianNik !== $oldCustodianNik) {
                $custodianChanged = true;
                $targetCustodianNik = $newCustodianNik ?: null;
                $targetCustodianName = $normNewCustName ?: null;
            }
        }

        if (!$companyChanged && !$locationChanged && !$custodianChanged) {
            flash('Tidak ada data mutasi yang diubah. Silakan tentukan Company, Lokasi, atau Pengguna tujuan baru.', 'err');
            redirect_to('asset_mutations', $_GET);
        }

        try {
            $pdo->beginTransaction();

            // Kumpulkan unit aset yang akan dimutasi (parent + children jika bundle)
            $itemsToUpdate = [$item];
            if (in_array((string)($item['asset_mode'] ?? ''), ['standalone', 'group', 'parent'], true) && db_table_exists($pdo, 'asset_item_members')) {
                $stmtChildren = $pdo->prepare('SELECT child_asset_item_id FROM asset_item_members WHERE parent_asset_item_id = ? AND detached_at IS NULL');
                $stmtChildren->execute([$assetItemId]);
                $childrenIds = $stmtChildren->fetchAll(PDO::FETCH_COLUMN);
                foreach ($childrenIds as $cid) {
                    $cItem = asset_item_row($pdo, (int)$cid);
                    if ($cItem) {
                        $itemsToUpdate[] = $cItem;
                    }
                }
            }

            // Tipe mutasi deskriptif
            $types = [];
            if ($companyChanged) $types[] = 'Company';
            if ($locationChanged) $types[] = 'Lokasi';
            if ($custodianChanged) $types[] = 'Pengguna';
            $mutationType = 'Mutasi ' . implode(' & ', $types);

            foreach ($itemsToUpdate as $target) {
                $isParent = ((int)$target['id'] === (int)$item['id']);
                
                $updFields = [];
                $updParams = [];

                if ($companyChanged) {
                    $updFields[] = 'company_id = ?';
                    $updParams[] = $targetCompanyId > 0 ? $targetCompanyId : null;
                }
                if ($locationChanged) {
                    $updFields[] = 'location_id = ?';
                    $updParams[] = $targetLocationId > 0 ? $targetLocationId : null;
                    $updFields[] = 'location_label = ?';
                    $updParams[] = $targetLocationLabel;
                }
                // Pengguna diupdate pada unit utama (parent/single)
                if ($custodianChanged && $isParent) {
                    $updFields[] = 'custodian_nik = ?';
                    $updParams[] = $targetCustodianNik;
                    $updFields[] = 'custodian_name = ?';
                    $updParams[] = $targetCustodianName;
                }

                if (!empty($updFields)) {
                    $updParams[] = $target['id'];
                    $pdo->prepare('UPDATE asset_items SET ' . implode(', ', $updFields) . ' WHERE id = ?')->execute($updParams);

                    // Sinkronkan owner PC jika unit terhubung ke PC
                    if ($custodianChanged && $isParent && db_table_exists($pdo, 'pcs') && !empty($target['source_pc_id'])) {
                        $pdo->prepare('UPDATE pcs SET owner_name = ?, employee_nik = ? WHERE pc_id = ?')
                            ->execute([$targetCustodianName, $targetCustodianNik, $target['source_pc_id']]);
                    }

                    // Catat ke log riwayat asset_movements
                    $itemReason = $reason ?: $mutationType;
                    if (!$isParent) {
                        $itemReason .= ' (Otomatis mengikuti Bundle Parent ' . e($item['asset_code']) . ')';
                    }

                    $stmtMove = $pdo->prepare('INSERT INTO asset_movements (
                        asset_item_id, movement_type, 
                        from_company_id, to_company_id, 
                        from_location_id, to_location_id, from_location_label, to_location_label,
                        from_custodian_name, from_custodian_nik, to_custodian_name, to_custodian_nik,
                        movement_date, reason, pic
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

                    $stmtMove->execute([
                        $target['id'],
                        $mutationType,
                        $oldCompanyId > 0 ? $oldCompanyId : null,
                        $targetCompanyId > 0 ? $targetCompanyId : null,
                        $oldLocationId > 0 ? $oldLocationId : null,
                        $targetLocationId > 0 ? $targetLocationId : null,
                        $oldLocationLabel,
                        $targetLocationLabel,
                        $oldCustodianName,
                        $oldCustodianNik,
                        $targetCustodianName,
                        $targetCustodianNik,
                        $date,
                        $itemReason,
                        $pic
                    ]);
                }
            }

            $pdo->commit();
            flash("Mutasi unit aset {$item['asset_code']} ({$mutationType}) berhasil disimpan.");
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('Gagal memproses mutasi aset: ' . $e->getMessage(), 'err');
        }

        redirect_to('asset_mutations', $_GET);
    }

    // =========================================================================
    // 2. PROSES POST HAPUS LOG RIWAYAT MUTASI
    // =========================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
        verify_csrf();
        require_regulation('asset_mutations', 'delete');
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId > 0) {
            $pdo->prepare('DELETE FROM asset_movements WHERE id = ?')->execute([$delId]);
            flash('Riwayat mutasi aset berhasil dihapus.');
        }
        redirect_to('asset_mutations', $_GET);
    }

    // =========================================================================
    // 3. PARAMETER FILTER: KOMODITAS & KATEGORI (ASSET_TYPE) WAJIB DIPILIH
    // =========================================================================
    $filterGroup = (int)($_GET['group_id'] ?? 0);
    $filterType = (int)($_GET['type_id'] ?? 0);
    $q = trim((string)($_GET['q'] ?? ''));

    // Ambil data karyawan dari database untuk pencarian cepat autocomplete pengguna baru
    $employeesJson = '[]';
    try {
        if (function_exists('employee_search_rows')) {
            $empRows = employee_search_rows($pdo, '', 300);
            $employeesJson = json_encode($empRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    } catch (Throwable $ignored) {}

    render_header('Mutasi Aset', $user);
    echo asset_nav_html();

    echo '<section class="panel">';
    echo '<div class="split" style="align-items:center;margin-bottom:16px;">';
    echo '  <div>';
    echo '    <h1 style="margin:0;display:flex;align-items:center;gap:8px;">🔄 Mutasi Aset</h1>';
    echo '    <p class="muted" style="margin:4px 0 0 0;">Pengelolaan mutasi unit aset fisik khusus antar <strong>Company</strong>, antar <strong>Lokasi</strong>, dan antar <strong>Pengguna</strong>.</p>';
    echo '  </div>';
    echo '  <div class="actions">';
    echo '    <a class="btn" href="' . route_url('export_excel', ['type' => 'asset_mutations']) . '">📥 Export Excel Mutasi</a>';
    echo '  </div>';
    echo '</div>';

    // Form Filter: Wajib Pilih Komoditas & Kategori (asset_type)
    echo '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:20px;">';
    echo '  <form method="get" class="actions" style="gap:12px;align-items:flex-end;flex-wrap:wrap;margin:0;">';
    echo '    <input type="hidden" name="route" value="asset_mutations">';
    echo '    <label style="margin:0;flex:1;min-width:200px;">1. Komoditas (Grup Aset) *';
    echo '      <select name="group_id" onchange="this.form.submit()" required>' . asset_group_options($pdo, $filterGroup, true, '- Pilih Komoditas Terlebih Dahulu -') . '</select>';
    echo '    </label>';
    
    echo '    <label style="margin:0;flex:1;min-width:200px;">2. Kategori Aset (Tipe) *';
    if ($filterGroup > 0) {
        echo '      <select name="type_id" onchange="this.form.submit()" required>' . asset_type_options($pdo, $filterType, $filterGroup, true, '- Pilih Kategori (Tipe) -') . '</select>';
    } else {
        echo '      <select name="type_id" disabled style="background:#f1f5f9;cursor:not-allowed;"><option value="">- Pilih Komoditas Terlebih Dahulu -</option></select>';
    }
    echo '    </label>';

    echo '    <label style="margin:0;flex:1.2;min-width:200px;">Cari (Kode/Nama/SN/Pengguna)';
    echo '      <input type="text" name="q" value="' . e($q) . '" placeholder="Ketik kata kunci...">';
    echo '    </label>';
    
    echo '    <button class="btn primary" type="submit" style="height:38px;">Tampilkan</button>';
    if ($filterGroup > 0 || $filterType > 0 || $q !== '') {
        echo '    <a href="' . route_url('asset_mutations') . '" class="btn" style="height:38px;">Reset</a>';
    }
    echo '  </form>';
    echo '</div>';

    // =========================================================================
    // 4. VALIDASI PEMILIHAN: HARUS MEMASUKKAN KOMODITAS & KATEGORI TERLEBIH DAHULU
    // =========================================================================
    if ($filterGroup <= 0 || $filterType <= 0) {
        echo '<div style="text-align:center;padding:48px 24px;color:#475569;background:#f8fafc;border-radius:8px;border:2px dashed #cbd5e1;margin-bottom:24px;">';
        echo '  <div style="font-size:36px;margin-bottom:10px;">📦</div>';
        echo '  <h3 style="margin:0 0 6px 0;color:#0f172a;font-size:17px;font-weight:700;">Masukkan Komoditas dan Kategori Terlebih Dahulu</h3>';
        echo '  <p style="margin:0;color:#64748b;font-size:13.5px;max-width:540px;margin:0 auto;line-height:1.5;">Silakan pilih <strong>Komoditas</strong> dan <strong>Kategori (Tipe Aset)</strong> pada filter di atas untuk menampilkan daftar unit aset yang siap dimutasi.</p>';
        echo '</div>';
    } else {
        // Ambil info type_code & type_name dari asset_types untuk filtering yang aman dari isu collation MySQL
        $stT = $pdo->prepare("SELECT type_code, type_name FROM asset_types WHERE id = ?");
        $stT->execute([$filterType]);
        $tRow = $stT->fetch(PDO::FETCH_ASSOC);
        $tCode = $tRow ? trim((string)$tRow['type_code']) : '';
        $tName = $tRow ? trim((string)$tRow['type_name']) : '';

        $typeClauses = ["a.asset_type_id = ?"];
        $params = [$filterGroup, $filterType];
        if ($tCode !== '') {
            $typeClauses[] = "a.asset_type = ?";
            $params[] = $tCode;
        }
        if ($tName !== '' && $tName !== $tCode) {
            $typeClauses[] = "a.asset_type = ?";
            $params[] = $tName;
        }

        // Query unit aset terfilter berdasarkan Komoditas & Kategori
        $sql = "SELECT a.*, 
                       COALESCE(NULLIF(a.asset_name, ''), a.model, '-') AS display_name,
                       COALESCE(g.group_name, 'IT') AS group_name,
                       COALESCE(t.type_name, a.asset_type, '-') AS type_name,
                       c.company_name,
                       COALESCE(loc.location_name, a.location_label, '-') AS display_location
                FROM asset_items a
                LEFT JOIN asset_groups g ON g.id = a.asset_group_id
                LEFT JOIN asset_types t ON t.id = a.asset_type_id
                LEFT JOIN asset_companies c ON a.company_id = c.id
                LEFT JOIN asset_locations loc ON a.location_id = loc.id
                WHERE a.asset_group_id = ?
                AND (" . implode(" OR ", $typeClauses) . ")
                AND LOWER(a.status) <> 'deleted'";
        
        if ($q !== '') {
            $sql .= " AND (a.asset_code LIKE ? OR a.asset_name LIKE ? OR a.model LIKE ? OR a.serial_number LIKE ? OR a.custodian_name LIKE ? OR a.custodian_nik LIKE ?)";
            $w = '%' . $q . '%';
            $params = array_merge($params, [$w, $w, $w, $w, $w, $w]);
        }
        
        $sql .= " ORDER BY a.asset_code ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($assets)) {
            echo '<div style="text-align:center;padding:36px;color:#64748b;background:#f8fafc;border-radius:8px;border:1px dashed #cbd5e1;margin-bottom:24px;">Tidak ada unit aset yang sesuai dengan filter Komoditas dan Kategori ini.</div>';
        } else {
            echo '<div style="margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;">';
            echo '  <h3 style="margin:0;color:#1e3a8a;font-size:15px;">Daftar Unit Aset Siap Mutasi (' . count($assets) . ' Unit)</h3>';
            echo '  <span class="badge ok">' . e($assets[0]['group_name'] ?? '') . ' › ' . e($assets[0]['type_name'] ?? '') . '</span>';
            echo '</div>';

            echo '<div style="overflow-x:auto;margin-bottom:32px;">';
            echo '<table class="table" style="width:100%;font-size:13px;">';
            echo '<thead><tr style="background:#f1f5f9;border-bottom:2px solid #cbd5e1;text-align:left;">';
            echo '  <th style="width:36px;text-align:center;">No</th>';
            echo '  <th>Kode Unit</th>';
            echo '  <th>Nama Aset / Model</th>';
            echo '  <th>Serial Number</th>';
            echo '  <th>Company Saat Ini</th>';
            echo '  <th>Lokasi Saat Ini</th>';
            echo '  <th>Pengguna Saat Ini</th>';
            echo '  <th>Mode</th>';
            echo '  <th style="text-align:center;width:100px;">Aksi Mutasi</th>';
            echo '</tr></thead><tbody>';
            
            $canCreate = has_regulation('asset_mutations', 'create', $user);
            $no = 1;
            
            foreach ($assets as $row) {
                $rawMode = (string)($row['asset_mode'] ?? 'standalone');
                $modeLabel = $rawMode === 'group' ? 'Bundle (Parent)' : ($rawMode === 'child' ? 'Bundle (Child)' : 'Single');
                $custText = !empty($row['custodian_name']) ? ('<strong>' . e($row['custodian_name']) . '</strong>' . (!empty($row['custodian_nik']) ? '<br><span class="muted" style="font-size:11px;">NIK: ' . e($row['custodian_nik']) . '</span>' : '')) : '<span class="muted">-</span>';
                
                // Siapkan data JSON untuk ditransfer ke modal mutasi
                $jsonData = htmlspecialchars(json_encode([
                    'id' => (int)$row['id'],
                    'code' => (string)$row['asset_code'],
                    'name' => (string)($row['display_name'] ?: '-'),
                    'sn' => (string)($row['serial_number'] ?: '-'),
                    'company_id' => (int)($row['company_id'] ?? 0),
                    'company_name' => (string)($row['company_name'] ?: '-'),
                    'location_id' => (int)($row['location_id'] ?? 0),
                    'location_label' => (string)($row['location_label'] ?: ($row['display_location'] ?: '-')),
                    'custodian_nik' => (string)($row['custodian_nik'] ?? ''),
                    'custodian_name' => (string)($row['custodian_name'] ?? '-'),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

                echo '<tr style="border-bottom:1px solid #e2e8f0;">';
                echo '  <td style="text-align:center;">' . $no++ . '</td>';
                echo '  <td><strong><a href="' . route_url('asset_item_form', ['id' => $row['id']]) . '">' . e($row['asset_code']) . '</a></strong></td>';
                echo '  <td>' . e($row['display_name']) . '</td>';
                echo '  <td><code>' . e($row['serial_number'] ?: '-') . '</code></td>';
                echo '  <td>' . e($row['company_name'] ?: '-') . '</td>';
                echo '  <td>' . e($row['display_location']) . '</td>';
                echo '  <td>' . $custText . '</td>';
                echo '  <td><span class="badge" style="font-size:10px;">' . e($modeLabel) . '</span></td>';
                echo '  <td style="text-align:center;">';
                if ($canCreate) {
                    echo '    <button type="button" class="btn small primary" onclick="openMutationForm(' . $jsonData . ')" style="display:inline-flex;align-items:center;gap:5px;padding:5px 10px;font-size:12px;">';
                    echo '      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg> Mutasi';
                    echo '    </button>';
                }
                echo '  </td>';
                echo '</tr>';
            }
            
            echo '</tbody></table></div>';
        }
    }

    // =========================================================================
    // 5. RIWAYAT MUTASI ASET TERKINI (Jejak Audit Mutasi)
    // =========================================================================
    echo '<div style="margin-top:36px;border-top:2px solid #e2e8f0;padding-top:20px;">';
    echo '  <div class="split" style="align-items:center;margin-bottom:14px;">';
    echo '    <div>';
    echo '      <h2 style="margin:0;font-size:16px;color:#0f172a;">📋 Riwayat Mutasi Aset Terkini</h2>';
    echo '      <p class="muted" style="margin:2px 0 0 0;font-size:12px;">Catatan riwayat pergerakan mutasi antar Company, Lokasi, dan Pengguna.</p>';
    echo '    </div>';
    echo '    <a class="btn" href="' . route_url('asset_movements') . '" style="font-size:12px;">Lihat Seluruh Riwayat Mutasi &rarr;</a>';
    echo '  </div>';

    $recentStmt = $pdo->query("SELECT mv.*, ai.asset_code, ai.asset_name, ai.asset_type,
                                      fc.company_name AS from_company, tc.company_name AS to_company,
                                      floc.location_name AS from_loc_name, tloc.location_name AS to_loc_name
                               FROM asset_movements mv
                               JOIN asset_items ai ON ai.id = mv.asset_item_id
                               LEFT JOIN asset_companies fc ON fc.id = mv.from_company_id
                               LEFT JOIN asset_companies tc ON tc.id = mv.to_company_id
                               LEFT JOIN asset_locations floc ON floc.id = mv.from_location_id
                               LEFT JOIN asset_locations tloc ON tloc.id = mv.to_location_id
                               ORDER BY mv.movement_date DESC, mv.id DESC
                               LIMIT 15");
    $recentRows = $recentStmt ? $recentStmt->fetchAll(PDO::FETCH_ASSOC) : [];

    if (empty($recentRows)) {
        echo '<p class="muted" style="font-size:13px;padding:12px 0;">Belum ada catatan transaksi mutasi aset.</p>';
    } else {
        echo '<div style="overflow-x:auto;">';
        echo '<table class="table" style="font-size:12px;width:100%;">';
        echo '<thead><tr style="background:#f8fafc;border-bottom:1px solid #cbd5e1;text-align:left;">';
        echo '  <th>Tanggal</th>';
        echo '  <th>Jenis Mutasi</th>';
        echo '  <th>Unit Aset</th>';
        echo '  <th>Asal (Sebelum)</th>';
        echo '  <th>Tujuan (Sesudah)</th>';
        echo '  <th>Alasan / Keterangan</th>';
        echo '  <th>PIC</th>';
        echo '</tr></thead><tbody>';

        foreach ($recentRows as $mr) {
            $mType = !empty($mr['movement_type']) ? $mr['movement_type'] : 'Mutasi Aset';
            
            $fromParts = [];
            if (!empty($mr['from_company'])) $fromParts[] = '🏢 ' . $mr['from_company'];
            if (!empty($mr['from_loc_name']) || !empty($mr['from_location_label'])) $fromParts[] = '📍 ' . ($mr['from_loc_name'] ?: $mr['from_location_label']);
            if (!empty($mr['from_custodian_name'])) $fromParts[] = '👤 ' . $mr['from_custodian_name'];
            $fromStr = !empty($fromParts) ? implode('<br>', array_map('e', $fromParts)) : '<span class="muted">-</span>';

            $toParts = [];
            if (!empty($mr['to_company'])) $toParts[] = '🏢 ' . $mr['to_company'];
            if (!empty($mr['to_loc_name']) || !empty($mr['to_location_label'])) $toParts[] = '📍 ' . ($mr['to_loc_name'] ?: $mr['to_location_label']);
            if (!empty($mr['to_custodian_name'])) $toParts[] = '👤 ' . $mr['to_custodian_name'];
            $toStr = !empty($toParts) ? implode('<br>', array_map('e', $toParts)) : '<span class="muted">-</span>';

            echo '<tr style="border-bottom:1px solid #f1f5f9;">';
            echo '  <td>' . date('d/m/Y', strtotime($mr['movement_date'])) . '</td>';
            echo '  <td><span class="badge" style="background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;font-size:11px;">' . e($mType) . '</span></td>';
            echo '  <td><strong>' . e($mr['asset_code']) . '</strong><br><span class="muted">' . e($mr['asset_name'] ?: '-') . '</span></td>';
            echo '  <td>' . $fromStr . '</td>';
            echo '  <td>' . $toStr . '</td>';
            echo '  <td>' . nl2br(e($mr['reason'] ?: '-')) . '</td>';
            echo '  <td>' . e($mr['pic'] ?: '-') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';
    echo '</section>';

    // =========================================================================
    // 6. MODAL INTERAKTIF: MUTASI KHUSUS COMPANY, LOKASI, DAN PENGGUNA
    // =========================================================================
    echo '
    <div id="mutationModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.65);backdrop-filter:blur(3px);z-index:999999;align-items:center;justify-content:center;padding:16px;">
        <div style="background:#fff;border-radius:12px;width:100%;max-width:680px;max-height:92vh;display:flex;flex-direction:column;box-shadow:0 25px 50px -12px rgba(0,0,0,0.3);overflow:hidden;">
            
            <!-- Modal Header -->
            <div style="padding:16px 22px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;">
                <div>
                    <h3 style="margin:0;font-size:17px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:8px;">
                        <span>🔄</span> Form Mutasi Unit Aset: <span id="mutAssetCode" style="color:#0284c7;font-family:monospace;"></span>
                    </h3>
                    <small id="mutAssetName" style="color:#64748b;font-size:12px;display:block;margin-top:2px;"></small>
                </div>
                <button type="button" onclick="closeMutationModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;line-height:1;">✕</button>
            </div>

            <!-- Modal Body Form -->
            <div style="padding:20px 22px;overflow-y:auto;flex:1;">
                <form method="post" id="mutForm">
                    <input type="hidden" name="csrf" value="' . csrf_token() . '">
                    <input type="hidden" name="action" value="mutate">
                    <input type="hidden" name="asset_item_id" id="mutAssetId" value="">

                    <!-- Ringkasan Info Asal Unit -->
                    <div style="background:#f1f5f9;border:1px solid #cbd5e1;border-radius:8px;padding:12px 16px;margin-bottom:18px;font-size:12.5px;">
                        <div style="font-weight:700;color:#334155;margin-bottom:6px;text-transform:uppercase;font-size:11px;letter-spacing:0.5px;">📍 Data Asal Unit Saat Ini:</div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;">
                            <div>🏢 <strong>Company:</strong> <span id="mutOldCompanyText" style="color:#0f172a;">-</span></div>
                            <div>📍 <strong>Lokasi:</strong> <span id="mutOldLocationText" style="color:#0f172a;">-</span></div>
                            <div>👤 <strong>Pengguna:</strong> <span id="mutOldCustodianText" style="color:#0f172a;">-</span></div>
                        </div>
                    </div>

                    <!-- 1. Mutasi Antar Company -->
                    <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;padding:14px;margin-bottom:14px;">
                        <label style="font-weight:700;color:#0f172a;font-size:13px;display:block;margin-bottom:6px;">
                            🏢 1. Mutasi Antar Company (Perusahaan)
                        </label>
                        <select name="new_company_id" id="mutCompanySelect" style="width:100%;box-sizing:border-box;">
                            <option value="keep">-- Tetap (Tidak Ada Perubahan Company) --</option>
                            <option value="none">-- Lepas Company (Unit Pool / Tanpa Company) --</option>
                            ' . (function() use ($pdo): string {
                                $h = '';
                                if (db_table_exists($pdo, 'asset_companies')) {
                                    foreach ($pdo->query('SELECT id, company_code, company_name FROM asset_companies WHERE is_active=1 ORDER BY company_name') as $cRow) {
                                        $h .= '<option value="' . e($cRow['id']) . '">' . e($cRow['company_code'] . ' - ' . $cRow['company_name']) . '</option>';
                                    }
                                }
                                return $h;
                            })() . '
                        </select>
                        <small style="color:#64748b;display:block;margin-top:4px;">Pilih company tujuan jika unit dipindahtugaskan ke entitas perusahaan lain.</small>
                    </div>

                    <!-- 2. Mutasi Antar Lokasi -->
                    <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;padding:14px;margin-bottom:14px;">
                        <label style="font-weight:700;color:#0f172a;font-size:13px;display:block;margin-bottom:6px;">
                            📍 2. Mutasi Antar Lokasi (Data Lokasi)
                        </label>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <div>
                                <small style="color:#475569;display:block;margin-bottom:3px;font-weight:600;">Master Lokasi Tujuan:</small>
                                <select name="new_location_id" id="mutLocationSelect" style="width:100%;box-sizing:border-box;">
                                    <option value="keep">-- Tetap (Tidak Ada Perubahan Lokasi) --</option>
                                    <option value="none">-- Lepas Lokasi (Unit Pool / Tanpa Lokasi) --</option>
                                    ' . (function() use ($pdo): string {
                                        $h = '';
                                        if (db_table_exists($pdo, 'asset_locations')) {
                                            foreach ($pdo->query('SELECT id, location_code, location_name FROM asset_locations WHERE is_active=1 ORDER BY location_name ASC') as $lRow) {
                                                $h .= '<option value="' . e($lRow['id']) . '">' . e($lRow['location_name'] . ' (' . $lRow['location_code'] . ')') . '</option>';
                                            }
                                        }
                                        return $h;
                                    })() . '
                                </select>
                            </div>
                            <div>
                                <small style="color:#475569;display:block;margin-bottom:3px;font-weight:600;">Detail Ruangan / Gedung Baru:</small>
                                <input type="text" name="new_location_label" id="mutLocationLabelInput" placeholder="Contoh: Gd. A / Lt. 2 / Ruang IT" style="width:100%;box-sizing:border-box;">
                            </div>
                        </div>
                        <small style="color:#64748b;display:block;margin-top:4px;">Tentukan lokasi fisik atau ruangan baru penempatan unit aset.</small>
                    </div>

                    <!-- 3. Mutasi Antar Pengguna -->
                    <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;padding:14px;margin-bottom:14px;position:relative;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                            <label style="font-weight:700;color:#0f172a;font-size:13px;margin:0;">
                                👤 3. Mutasi Antar Pengguna (Data Pengguna / Custodian)
                            </label>
                            <button type="button" onclick="clearMutCustodian()" style="background:none;border:none;color:#dc2626;font-size:11px;cursor:pointer;text-decoration:underline;">
                                Kosongkan Pengguna (Unit ke Pool)
                            </button>
                        </div>
                        
                        <!-- Autocomplete live search dari database karyawan -->
                        <div style="position:relative;margin-bottom:8px;">
                            <input type="text" id="mutCustodianSearchInput" placeholder="🔍 Cari karyawan di Master Pengguna (Ketik NIK / Nama)..." autocomplete="off" style="width:100%;box-sizing:border-box;border:1.5px solid #0284c7;padding:8px 12px;font-size:13px;" oninput="onMutEmployeeSearch(this.value)" onfocus="onMutEmployeeSearch(this.value)">
                            <div id="mutCustodianDropdown" style="display:none;position:absolute;left:0;right:0;top:calc(100% + 2px);max-height:220px;overflow-y:auto;background:#fff;border:1px solid #cbd5e1;border-radius:6px;box-shadow:0 10px 25px rgba(0,0,0,0.18);z-index:999999;"></div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:10px;">
                            <div>
                                <small style="color:#475569;display:block;margin-bottom:3px;font-weight:600;">NIK Pengguna Baru:</small>
                                <input type="text" name="new_custodian_nik" id="mutCustodianNikInput" placeholder="NIK Karyawan" style="width:100%;box-sizing:border-box;">
                            </div>
                            <div>
                                <small style="color:#475569;display:block;margin-bottom:3px;font-weight:600;">Nama Pengguna Baru:</small>
                                <input type="text" name="new_custodian_name" id="mutCustodianNameInput" placeholder="Nama Karyawan Pemakai" style="width:100%;box-sizing:border-box;">
                            </div>
                        </div>
                        <small style="color:#64748b;display:block;margin-top:4px;">Pilih karyawan dari master atau isi langsung NIK & nama pemegang unit yang baru.</small>
                    </div>

                    <!-- Informasi Transaksi Mutasi -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                        <div>
                            <label style="font-weight:700;color:#0f172a;font-size:12.5px;display:block;margin-bottom:4px;">Tanggal Mutasi *</label>
                            <input type="date" name="movement_date" value="' . date('Y-m-d') . '" required style="width:100%;box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="font-weight:700;color:#0f172a;font-size:12.5px;display:block;margin-bottom:4px;">PIC (Penanggung Jawab) *</label>
                            <input type="text" name="pic" value="' . e($user['name'] ?? 'Admin') . '" required style="width:100%;box-sizing:border-box;">
                        </div>
                    </div>

                    <div style="margin-bottom:18px;">
                        <label style="font-weight:700;color:#0f172a;font-size:12.5px;display:block;margin-bottom:4px;">Alasan / Keterangan Mutasi *</label>
                        <input type="text" name="reason" placeholder="Contoh: Rotasi kerja staf, Relokasi pabrik, Serah terima unit kerja" required style="width:100%;box-sizing:border-box;">
                    </div>

                    <!-- Modal Actions -->
                    <div class="actions" style="margin:0;display:flex;justify-content:flex-end;gap:8px;">
                        <button type="button" class="btn" onclick="closeMutationModal()" style="padding:8px 18px;">Batal</button>
                        <button type="submit" class="btn primary" style="padding:8px 22px;font-weight:700;">💾 Simpan Mutasi Aset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Client-side script untuk modal & autocomplete -->
    <script>
    var mutEmployees = ' . $employeesJson . ';

    function openMutationForm(item) {
        document.getElementById("mutAssetId").value = item.id;
        document.getElementById("mutAssetCode").innerText = item.code;
        document.getElementById("mutAssetName").innerText = item.name + (item.sn && item.sn !== "-" ? (" • SN: " + item.sn) : "");
        
        document.getElementById("mutOldCompanyText").innerText = item.company_name || "-";
        document.getElementById("mutOldLocationText").innerText = item.location_label || "-";
        document.getElementById("mutOldCustodianText").innerText = item.custodian_name ? (item.custodian_name + (item.custodian_nik ? " (" + item.custodian_nik + ")" : "")) : "-";

        document.getElementById("mutCompanySelect").value = "keep";
        document.getElementById("mutLocationSelect").value = "keep";
        document.getElementById("mutLocationLabelInput").value = "";
        
        document.getElementById("mutCustodianSearchInput").value = "";
        document.getElementById("mutCustodianNikInput").value = item.custodian_nik || "";
        document.getElementById("mutCustodianNameInput").value = (item.custodian_name && item.custodian_name !== "-") ? item.custodian_name : "";

        var dd = document.getElementById("mutCustodianDropdown");
        if (dd) dd.style.display = "none";

        document.getElementById("mutationModal").style.display = "flex";
    }

    function closeMutationModal() {
        document.getElementById("mutationModal").style.display = "none";
    }

    function clearMutCustodian() {
        document.getElementById("mutCustodianSearchInput").value = "";
        document.getElementById("mutCustodianNikInput").value = "";
        document.getElementById("mutCustodianNameInput").value = "-";
        var dd = document.getElementById("mutCustodianDropdown");
        if (dd) dd.style.display = "none";
    }

    function onMutEmployeeSearch(q) {
        var dd = document.getElementById("mutCustodianDropdown");
        if (!dd) return;
        q = (q || "").trim().toLowerCase();

        var matches = mutEmployees.filter(function(e) {
            if (!q) return true;
            var nik = (e.nik || "").toLowerCase();
            var name = (e.name || "").toLowerCase();
            var dept = (e.department || "").toLowerCase();
            return nik.includes(q) || name.includes(q) || dept.includes(q);
        });

        if (matches.length === 0) {
            dd.innerHTML = "<div style=\'padding:10px 14px;font-size:12px;color:#64748b;text-align:center;\'>Tidak ditemukan karyawan yang cocok.</div>";
            dd.style.display = "block";
            return;
        }

        dd.innerHTML = "";
        matches.slice(0, 20).forEach(function(emp) {
            var row = document.createElement("div");
            row.style.padding = "8px 12px";
            row.style.borderBottom = "1px solid #f1f5f9";
            row.style.cursor = "pointer";
            row.style.background = "#fff";
            row.innerHTML = "<div style=\'font-weight:600;color:#0f172a;font-size:13px;\'>" + escapeHtml(emp.name) + "</div>"
                + "<div style=\'font-size:11px;color:#64748b;\'>NIK: " + escapeHtml(emp.nik) + (emp.department ? " • Dept: " + escapeHtml(emp.department) : "") + "</div>";
            row.onmouseenter = function() { row.style.background = "#f0f9ff"; };
            row.onmouseleave = function() { row.style.background = "#fff"; };
            row.onmousedown = function() {
                document.getElementById("mutCustodianNikInput").value = emp.nik || "";
                document.getElementById("mutCustodianNameInput").value = emp.name || "";
                document.getElementById("mutCustodianSearchInput").value = emp.name + " (" + emp.nik + ")";
                dd.style.display = "none";
            };
            dd.appendChild(row);
        });
        dd.style.display = "block";
    }

    function escapeHtml(str) {
        if (!str) return "";
        return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/\'/g, "&#039;");
    }

    document.addEventListener("click", function(e) {
        var dd = document.getElementById("mutCustodianDropdown");
        var input = document.getElementById("mutCustodianSearchInput");
        if (dd && input && !dd.contains(e.target) && e.target !== input) {
            dd.style.display = "none";
        }
    });
    </script>
    ';

    render_footer();
}
