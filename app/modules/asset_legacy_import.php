<?php

declare(strict_types=1);

/**
 * Modul: Import Data Aset Lama (Legacy) dengan AI-Assisted Confirmation & Master Builder
 *
 * [ALUR PROSES TERSTRUKTUR & FITUR UTAMA]:
 * 1. Regulasi Hak Akses:
 *    - Terintegrasi di bawah izin Tambah (Create) pada Unit Aset (`asset_items`).
 *    - Diakses melalui tombol "Import Aset Lama (Excel)" di dalam menu Unit Aset.
 * 2. Step 1 (Pemilihan Komoditas & Kategori Awal + Upload File):
 *    - Pengguna memilih Komoditas (`asset_groups`) & Kategori (`asset_types`) tujuan terlebih dahulu.
 *    - Pengguna mengunggah file Excel (`.xlsx`) atau CSV (`.csv`) data inventaris lama.
 * 3. Step 2 (Form Konfirmasi & Editing Interaktif Setelah Upload):
 *    - Kode Lama (Legacy) Unit Aset: otomatis terisi default dari kolom "NO" file import dan dapat diedit per baris.
 *      Tanggal perolehan/beli otomatis diekstrak dari teks setelah tanda "/" pada kolom "NO".
 *    - AI Ekstraksi Merk & Rancangan Kombinasi Master Barang:
 *      Dari kolom "NAMA INVENTARIS + SPESIFIKASI", AI mencari Merk, otomatis memasukkan Merk baru ke database
 *      `asset_brands` serta memilihnya, lalu merancang kombinasi ("Komoditas" + "Kategori" + "Merk" + Spesifikasi/Model)
 *      sebagai ajuan Master Barang (`asset_master_items`) yang dapat dikonfirmasi, diedit, atau dipilih dari Master Barang eksisting.
 *    - Ajuan Rancangan Lokasi Baru dari Kolom "Ruko", "Lantai", dan "Ruangan":
 *      Membuat ajuan rancangan lokasi baru yang akan dimasukkan ke tabel `asset_locations` (Master Lokasi),
 *      dilengkapi opsi simpan langsung ke Master Lokasi, tombol buka menu "Master Lokasi", atau pilih dari "Master Lokasi" eksisting.
 * 4. Step 3 (Eksekusi Simpan Transaksional):
 *    - Menyimpan Merk, Master Barang, dan Master Lokasi hasil konfirmasi, kemudian menyimpan seluruh baris Unit Aset
 *      yang dicentang beserta Kode Lama (`alias_code`), relasi `master_item_id`, `brand_id`, `location_id`,
 *      `asset_identifiers`, dan `asset_specifications`.
 */

function handle_route_asset_legacy_import(PDO $pdo): void
{
    $user = require_login();
    if (!is_full_admin($user) && !has_regulation('asset_items', 'create', $user)) {
        http_response_code(403);
        exit('Akses ditolak ke menu Import Aset Lama.');
    }

    ensure_brand_and_master_item_schema($pdo);

    $action = (string)($_GET['action'] ?? ($_POST['action'] ?? ''));

    // -------------------------------------------------------------------------
    // 1. Download Template CSV Contoh
    // -------------------------------------------------------------------------
    if ($action === 'download_template') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Template_Import_Aset_Lama.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
        fputcsv($out, ['NO', 'NAMA INVENTARIS + SPESIFIKASI', 'RUANGAN', 'LANTAI', 'RUKO']);
        fputcsv($out, ['1 /21-2-2023', 'AC SHARP 1/2 PK R32', 'RUANG MAKAN', '4', 'BLOK AY NO. 16 A']);
        fputcsv($out, ['2 /15-05-2022', 'Laptop Lenovo ThinkPad T480 Intel Core i5-8250U RAM 16GB SSD 512GB Windows 11 Pro SN: PF1A2B3C', 'RUANG IT', '2', 'KANTOR PUSAT']);
        fputcsv($out, ['3 /10-08-2021', 'PC Desktop Dell OptiPlex 3080 Core i7-10700 RAM 32GB HDD 1TB', 'FINANCE', '1', 'KANTOR PUSAT']);
        fclose($out);
        exit;
    }

    // -------------------------------------------------------------------------
    // 2. Endpoint JSON / AJAX untuk Form Konfirmasi & Editing
    //    - locations_json : muat ulang daftar Master Lokasi, Master Merk & Master Barang
    //    - ajax_save_location : simpan ajuan rancangan lokasi baru langsung ke Master Lokasi
    //    - ajax_save_master_item : simpan rancangan kombinasi AI langsung ke Master Barang
    // -------------------------------------------------------------------------
    if ($action === 'locations_json') {
        header('Content-Type: application/json; charset=utf-8');
        $groupId = (int)($_GET['group_id'] ?? ($_SESSION['asset_legacy_import_opts']['asset_group_id'] ?? 0));
        $typeId = (int)($_GET['type_id'] ?? ($_SESSION['asset_legacy_import_opts']['asset_type_id'] ?? 0));
        $locations = $pdo->query("SELECT id, location_code, location_name FROM asset_locations WHERE (is_active = 1 OR is_active IS NULL) ORDER BY location_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $brands = $pdo->query("SELECT id, brand_code, brand_name, asset_group_id, asset_type_id FROM asset_brands WHERE (is_active = 1 OR is_active IS NULL) ORDER BY brand_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $masterSql = "SELECT m.id, m.item_code, m.item_name, m.model_name, m.brand_id, COALESCE(b.brand_name, '') AS brand_name
                      FROM asset_master_items m
                      LEFT JOIN asset_brands b ON b.id = m.brand_id
                      WHERE (m.is_active = 1 OR m.is_active IS NULL)";
        $masterParams = [];
        if ($groupId > 0) {
            $masterSql .= " AND m.asset_group_id = ?";
            $masterParams[] = $groupId;
        }
        if ($typeId > 0) {
            $masterSql .= " AND m.asset_type_id = ?";
            $masterParams[] = $typeId;
        }
        $masterSql .= " ORDER BY m.item_name ASC";
        $stM = $pdo->prepare($masterSql);
        $stM->execute($masterParams);
        $masters = $stM->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'locations' => $locations,
            'brands' => $brands,
            'masters' => $masters,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'ajax_save_location' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        verify_csrf();
        $locName = mb_substr(trim((string)($_POST['location_name'] ?? '')), 0, 120);
        $locCodeRaw = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', trim((string)($_POST['location_code'] ?? ''))));
        $locKey = trim((string)($_POST['loc_key'] ?? ''));

        if ($locName === '') {
            echo json_encode(['ok' => false, 'error' => 'Nama lokasi wajib diisi.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $stFind = $pdo->prepare("SELECT id, location_code, location_name FROM asset_locations WHERE UPPER(TRIM(location_name)) = UPPER(TRIM(?)) LIMIT 1");
            $stFind->execute([$locName]);
            $existing = $stFind->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $locId = (int)$existing['id'];
                $locCode = (string)$existing['location_code'];
                $locName = (string)$existing['location_name'];
                $created = false;
            } else {
                $usedCodes = [];
                $locCode = legacy_propose_location_code($pdo, $locCodeRaw !== '' ? $locCodeRaw : $locName, $usedCodes, $locCodeRaw !== '');
                $ins = $pdo->prepare("INSERT INTO asset_locations (location_code, location_name, is_active) VALUES (?, ?, 1)");
                $ins->execute([$locCode, $locName]);
                $locId = (int)$pdo->lastInsertId();
                $created = true;
            }

            if ($locKey !== '' && isset($_SESSION['asset_legacy_import_plan']['locations'][$locKey])) {
                $_SESSION['asset_legacy_import_plan']['locations'][$locKey]['mode'] = 'existing';
                $_SESSION['asset_legacy_import_plan']['locations'][$locKey]['location_id'] = $locId;
                $_SESSION['asset_legacy_import_plan']['locations'][$locKey]['name'] = $locName;
                $_SESSION['asset_legacy_import_plan']['locations'][$locKey]['code'] = $locCode;
            }

            $allLocations = $pdo->query("SELECT id, location_code, location_name FROM asset_locations WHERE (is_active = 1 OR is_active IS NULL) ORDER BY location_name ASC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'ok' => true,
                'created' => $created,
                'location' => ['id' => $locId, 'location_code' => $locCode, 'location_name' => $locName],
                'locations' => $allLocations,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    if ($action === 'ajax_save_master_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        verify_csrf();
        $groupId = (int)($_POST['asset_group_id'] ?? ($_SESSION['asset_legacy_import_opts']['asset_group_id'] ?? 0));
        $typeId = (int)($_POST['asset_type_id'] ?? ($_SESSION['asset_legacy_import_opts']['asset_type_id'] ?? 0));
        $brandId = (int)($_POST['brand_id'] ?? 0);
        $brandNameCustom = mb_substr(trim((string)($_POST['brand_name'] ?? '')), 0, 120);
        $itemName = mb_substr(trim((string)($_POST['item_name'] ?? '')), 0, 180);
        $modelName = mb_substr(trim((string)($_POST['model_name'] ?? '')), 0, 160);
        $specsText = trim((string)($_POST['specs_text'] ?? ''));
        $masterKey = trim((string)($_POST['master_key'] ?? ''));

        if ($groupId <= 0 || $typeId <= 0 || $itemName === '') {
            echo json_encode(['ok' => false, 'error' => 'Komoditas, Kategori, dan Nama Master Barang wajib diisi.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $newBrandCreated = 0;
            if ($brandId <= 0 && $brandNameCustom !== '') {
                $brandId = (int)(legacy_find_or_create_brand($pdo, $brandNameCustom, $groupId, $typeId, $newBrandCreated) ?: 0);
            }
            $brandNameResolved = '';
            if ($brandId > 0) {
                $stB = $pdo->prepare("SELECT brand_name FROM asset_brands WHERE id = ?");
                $stB->execute([$brandId]);
                $brandNameResolved = (string)($stB->fetchColumn() ?: '');
            }

            // Cek apakah Master Barang dengan nama serupa sudah ada pada kategori ini
            $stChk = $pdo->prepare("SELECT id, item_code, item_name, model_name, brand_id FROM asset_master_items WHERE asset_group_id = ? AND asset_type_id = ? AND UPPER(TRIM(item_name)) = UPPER(TRIM(?)) LIMIT 1");
            $stChk->execute([$groupId, $typeId, $itemName]);
            $existingM = $stChk->fetch(PDO::FETCH_ASSOC);

            if ($existingM) {
                $masterId = (int)$existingM['id'];
                $itemCode = (string)$existingM['item_code'];
                $created = false;
            } else {
                $specsMap = ($masterKey !== '' && isset($_SESSION['asset_legacy_import_plan']['masters'][$masterKey]['specs_map']))
                    ? (array)$_SESSION['asset_legacy_import_plan']['masters'][$masterKey]['specs_map']
                    : [];
                $specJson = legacy_build_master_specifications_json($pdo, $typeId, $specsMap, $specsText);
                $itemCode = generate_master_item_code($pdo, $groupId, $typeId, $brandId);
                $insM = $pdo->prepare("INSERT INTO asset_master_items (item_code, item_name, asset_group_id, asset_type_id, brand_id, model_name, specifications, description, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $insM->execute([
                    $itemCode,
                    $itemName,
                    $groupId,
                    $typeId,
                    $brandId > 0 ? $brandId : null,
                    $modelName !== '' ? $modelName : null,
                    $specJson,
                    'Dirancang oleh AI dari Import Data Aset Lama (Legacy)' . ($specsText !== '' ? ' | Spesifikasi: ' . $specsText : ''),
                ]);
                $masterId = (int)$pdo->lastInsertId();
                $created = true;
            }

            if ($masterKey !== '' && isset($_SESSION['asset_legacy_import_plan']['masters'][$masterKey])) {
                $_SESSION['asset_legacy_import_plan']['masters'][$masterKey]['mode'] = 'existing';
                $_SESSION['asset_legacy_import_plan']['masters'][$masterKey]['master_item_id'] = $masterId;
                $_SESSION['asset_legacy_import_plan']['masters'][$masterKey]['item_code'] = $itemCode;
                $_SESSION['asset_legacy_import_plan']['masters'][$masterKey]['item_name'] = $itemName;
                $_SESSION['asset_legacy_import_plan']['masters'][$masterKey]['model'] = $modelName;
                $_SESSION['asset_legacy_import_plan']['masters'][$masterKey]['brand_id'] = $brandId;
                $_SESSION['asset_legacy_import_plan']['masters'][$masterKey]['brand_name'] = $brandNameResolved;
            }

            $stAllM = $pdo->prepare("SELECT m.id, m.item_code, m.item_name, m.model_name, m.brand_id, COALESCE(b.brand_name, '') AS brand_name FROM asset_master_items m LEFT JOIN asset_brands b ON b.id = m.brand_id WHERE m.asset_group_id = ? AND m.asset_type_id = ? AND (m.is_active = 1 OR m.is_active IS NULL) ORDER BY m.item_name ASC");
            $stAllM->execute([$groupId, $typeId]);
            $allMasters = $stAllM->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'ok' => true,
                'created' => $created,
                'master' => [
                    'id' => $masterId,
                    'item_code' => $itemCode,
                    'item_name' => $itemName,
                    'model_name' => $modelName,
                    'brand_id' => $brandId,
                    'brand_name' => $brandNameResolved,
                ],
                'masters' => $allMasters,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // Reset Session Preview & Rancangan
    if ($action === 'reset') {
        unset($_SESSION['asset_legacy_import_preview'], $_SESSION['asset_legacy_import_opts'], $_SESSION['asset_legacy_import_plan']);
        redirect_to('asset_legacy_import');
    }

    // -------------------------------------------------------------------------
    // 3. Eksekusi Simpan Hasil Form Konfirmasi & Editing ke Database (Step 3)
    // -------------------------------------------------------------------------
    if ($action === 'execute_import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if (!is_full_admin($user) && !has_regulation('asset_items', 'create', $user)) {
            http_response_code(403);
            exit('Akses ditolak. Role Anda tidak memiliki izin untuk menyimpan data hasil import aset lama.');
        }

        $previewData = $_SESSION['asset_legacy_import_preview'] ?? [];
        $plan = $_SESSION['asset_legacy_import_plan'] ?? ['masters' => [], 'locations' => []];
        if (empty($previewData)) {
            flash('Tidak ada data preview yang siap diimpor. Silakan upload file kembali.', 'err');
            redirect_to('asset_legacy_import');
        }

        $selectedIndexes = (array)($_POST['selected_rows'] ?? []);
        if (empty($selectedIndexes)) {
            flash('Tidak ada item yang dipilih untuk diimpor. Berikan tanda centang pada baris unit yang ingin diimpor.', 'err');
            redirect_to('asset_legacy_import');
        }

        $companyId = (int)($_POST['company_id'] ?? 0) ?: null;
        $groupId = (int)($_POST['asset_group_id'] ?? 0);
        $typeId = (int)($_POST['asset_type_id'] ?? 0);
        if ($groupId <= 0 || $typeId <= 0) {
            flash('Komoditas dan Kategori tujuan wajib dipilih.', 'err');
            redirect_to('asset_legacy_import');
        }

        $gStmt = $pdo->prepare("SELECT group_code, group_name FROM asset_groups WHERE id = ?");
        $gStmt->execute([$groupId]);
        $gRow = $gStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $gCode = strtoupper(trim((string)($gRow['group_code'] ?? 'IT')));

        $tStmt = $pdo->prepare("SELECT type_code, type_name FROM asset_types WHERE id = ?");
        $tStmt->execute([$typeId]);
        $tRow = $tStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $tCode = strtoupper(trim((string)($tRow['type_code'] ?? 'CMP')));
        $typeName = (string)($tRow['type_name'] ?? 'Computer');

        $postMasters = (array)($_POST['plan_master'] ?? []);
        $postLocs = (array)($_POST['plan_loc'] ?? []);

        // Gabungkan baris terpilih dengan hasil editing pada Form Konfirmasi & Editing
        $rowsToImport = [];
        foreach ($selectedIndexes as $idxStr) {
            $idx = (int)$idxStr;
            if (!isset($previewData[$idx])) {
                continue;
            }
            $row = $previewData[$idx];
            $pItem = (isset($_POST['items'][$idx]) && is_array($_POST['items'][$idx])) ? $_POST['items'][$idx] : [];

            if (isset($pItem['legacy_code'])) {
                $row['legacy_code'] = trim((string)$pItem['legacy_code']);
            }
            if (isset($pItem['installed_at'])) {
                $row['installed_at'] = trim((string)$pItem['installed_at']) ?: null;
            }
            if (isset($pItem['asset_name']) && trim((string)$pItem['asset_name']) !== '') {
                $row['asset_name'] = trim((string)$pItem['asset_name']);
            }
            if (isset($pItem['brand_id'])) {
                $row['brand_id'] = (int)$pItem['brand_id'];
            }
            if (isset($pItem['brand']) && trim((string)$pItem['brand']) !== '') {
                $row['brand'] = trim((string)$pItem['brand']);
            }
            if (isset($pItem['model'])) {
                $row['model'] = trim((string)$pItem['model']);
            }
            if (isset($pItem['serial_number'])) {
                $row['serial_number'] = trim((string)$pItem['serial_number']);
            }
            if (isset($pItem['loc_label'])) {
                $row['loc_label'] = trim((string)$pItem['loc_label']);
            }
            if (isset($pItem['row_location_choice'])) {
                $row['row_location_choice'] = trim((string)$pItem['row_location_choice']);
            }
            if (isset($pItem['row_master_choice'])) {
                $row['row_master_choice'] = trim((string)$pItem['row_master_choice']);
            }
            $rowsToImport[] = $row;
        }

        $activeStatusId = (int)($pdo->query("SELECT id FROM asset_statuses WHERE status_code = 'ACTIVE' LIMIT 1")->fetchColumn() ?: 1);
        $importedCount = 0;
        $skippedCount = 0;
        $newBrandCount = 0;
        $newMasterCount = 0;
        $newLocCount = 0;

        $pdo->beginTransaction();
        try {
            // a. Resolusi Rancangan Master Barang (Kombinasi Komoditas + Kategori + Merk + Spesifikasi)
            $masterRes = [];
            $insMaster = $pdo->prepare("INSERT INTO asset_master_items (item_code, item_name, asset_group_id, asset_type_id, brand_id, model_name, specifications, description, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");

            $neededMasterKeys = array_unique(array_filter(array_column($rowsToImport, 'master_key')));
            foreach ($neededMasterKeys as $mKey) {
                $cfg = $plan['masters'][$mKey] ?? [];
                $p = (array)($postMasters[$mKey] ?? []);
                $mode = (string)($p['mode'] ?? ($cfg['mode'] ?? 'new'));

                $bId = (int)($p['brand_id'] ?? ($cfg['brand_id'] ?? 0));
                $bCustom = mb_substr(trim((string)($p['brand_custom'] ?? '')), 0, 120);
                if ($bCustom !== '') {
                    $bId = (int)(legacy_find_or_create_brand($pdo, $bCustom, $groupId, $typeId, $newBrandCount) ?: 0);
                } elseif ($bId <= 0 && !empty($cfg['brand_name'])) {
                    $bId = (int)(legacy_find_or_create_brand($pdo, (string)$cfg['brand_name'], $groupId, $typeId, $newBrandCount) ?: 0);
                }

                $bName = '';
                if ($bId > 0) {
                    $stB = $pdo->prepare("SELECT brand_name FROM asset_brands WHERE id = ?");
                    $stB->execute([$bId]);
                    $bName = (string)($stB->fetchColumn() ?: '');
                }

                $itemName = mb_substr(trim((string)($p['item_name'] ?? ($cfg['item_name'] ?? ''))), 0, 180);
                $modelName = mb_substr(trim((string)($p['model'] ?? ($cfg['model'] ?? ''))), 0, 160);
                $specsText = trim((string)($p['specs_text'] ?? ($cfg['specs_text'] ?? '')));

                $masterRes[$mKey] = [
                    'id' => null,
                    'brand_id' => $bId > 0 ? $bId : null,
                    'brand_name' => $bName,
                    'model' => $modelName,
                    'item_name' => $itemName,
                ];

                if ($mode === 'existing') {
                    $mid = (int)($p['master_item_id'] ?? ($cfg['master_item_id'] ?? 0));
                    if ($mid > 0) {
                        $stM = $pdo->prepare("SELECT m.id, m.item_name, m.model_name, m.brand_id, COALESCE(b.brand_name, '') AS brand_name FROM asset_master_items m LEFT JOIN asset_brands b ON b.id = m.brand_id WHERE m.id = ?");
                        $stM->execute([$mid]);
                        if ($mRow = $stM->fetch(PDO::FETCH_ASSOC)) {
                            $masterRes[$mKey] = [
                                'id' => (int)$mRow['id'],
                                'brand_id' => $mRow['brand_id'] ? (int)$mRow['brand_id'] : ($bId > 0 ? $bId : null),
                                'brand_name' => (string)($mRow['brand_name'] ?: $bName),
                                'model' => (string)($mRow['model_name'] ?: $modelName),
                                'item_name' => (string)$mRow['item_name'],
                            ];
                        }
                    }
                } elseif ($mode === 'new' && $itemName !== '') {
                    // Cek apakah sudah ada Master Barang dengan nama yang sama pada Komoditas & Kategori ini
                    $stExist = $pdo->prepare("SELECT id, item_name, model_name, brand_id FROM asset_master_items WHERE asset_group_id = ? AND asset_type_id = ? AND UPPER(TRIM(item_name)) = UPPER(TRIM(?)) LIMIT 1");
                    $stExist->execute([$groupId, $typeId, $itemName]);
                    if ($exM = $stExist->fetch(PDO::FETCH_ASSOC)) {
                        $masterRes[$mKey]['id'] = (int)$exM['id'];
                    } else {
                        $specJson = legacy_build_master_specifications_json($pdo, $typeId, (array)($cfg['specs_map'] ?? []), $specsText);
                        $itemCode = generate_master_item_code($pdo, $groupId, $typeId, $bId);
                        $insMaster->execute([
                            $itemCode,
                            $itemName,
                            $groupId,
                            $typeId,
                            $bId > 0 ? $bId : null,
                            $modelName !== '' ? $modelName : null,
                            $specJson,
                            'Dirancang oleh AI dari Import Data Aset Lama (Legacy)' . ($specsText !== '' ? ' | Spesifikasi: ' . $specsText : ''),
                        ]);
                        $newMasterCount++;
                        $masterRes[$mKey]['id'] = (int)$pdo->lastInsertId();
                    }
                }
            }

            // b. Resolusi Ajuan Rancangan Lokasi Baru (dari Ruko, Lantai, dan Ruangan) / Pilih Master Lokasi
            $locRes = [];
            $insLoc = $pdo->prepare("INSERT INTO asset_locations (location_code, location_name, is_active) VALUES (?, ?, 1)");
            $usedLocCodes = [];

            $neededLocKeys = array_unique(array_filter(array_column($rowsToImport, 'loc_key')));
            foreach ($neededLocKeys as $lKey) {
                $cfg = $plan['locations'][$lKey] ?? [];
                $p = (array)($postLocs[$lKey] ?? []);
                $mode = (string)($p['mode'] ?? ($cfg['mode'] ?? 'new'));
                $locRes[$lKey] = null;

                if ($mode === 'existing') {
                    $lid = (int)($p['location_id'] ?? ($cfg['location_id'] ?? 0));
                    if ($lid > 0) {
                        $stL = $pdo->prepare("SELECT id FROM asset_locations WHERE id = ?");
                        $stL->execute([$lid]);
                        $locRes[$lKey] = ($v = $stL->fetchColumn()) ? (int)$v : null;
                    }
                } elseif ($mode === 'new') {
                    $locName = mb_substr(trim((string)($p['name'] ?? ($cfg['name'] ?? ''))), 0, 120);
                    $codeRaw = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', trim((string)($p['code'] ?? ($cfg['code'] ?? '')))));
                    if ($locName !== '') {
                        $stFind = $pdo->prepare("SELECT id FROM asset_locations WHERE UPPER(TRIM(location_name)) = UPPER(TRIM(?)) LIMIT 1");
                        $stFind->execute([$locName]);
                        if ($v = $stFind->fetchColumn()) {
                            $locRes[$lKey] = (int)$v;
                        } else {
                            $locCode = legacy_propose_location_code($pdo, $codeRaw !== '' ? $codeRaw : $locName, $usedLocCodes, $codeRaw !== '');
                            $insLoc->execute([$locCode, $locName]);
                            $newLocCount++;
                            $locRes[$lKey] = (int)$pdo->lastInsertId();
                        }
                    }
                }
            }

            // c. Simpan Setiap Baris Unit Aset Terpilih
            $checkStmt = $pdo->prepare("SELECT id FROM asset_items WHERE alias_code = ? LIMIT 1");
            $insSql = "INSERT INTO asset_items (
                master_item_id, brand_id, asset_code, alias_code, asset_group_id, asset_type_id,
                asset_status_id, asset_mode, asset_name, brand, model, serial_number,
                custodian_name, notes, company_id, location_id, location_label,
                installed_at, asset_type, asset_category, status
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, 'standalone', ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, 'active'
            )";
            $insStmt = $pdo->prepare($insSql);
            $seenLegacy = [];

            foreach ($rowsToImport as $row) {
                $legacyCode = trim((string)($row['legacy_code'] ?? ''));
                if ($legacyCode === '' || isset($seenLegacy[mb_strtolower($legacyCode)])) {
                    $skippedCount++;
                    continue;
                }
                $checkStmt->execute([$legacyCode]);
                if ($checkStmt->fetchColumn()) {
                    $skippedCount++;
                    continue;
                }
                $seenLegacy[mb_strtolower($legacyCode)] = true;

                // Resolusi Master Barang untuk baris ini (mendukung override per baris jika dipilih user)
                $mInfo = $masterRes[$row['master_key'] ?? ''] ?? null;
                $rowMasterChoice = (string)($row['row_master_choice'] ?? 'plan');
                $masterItemId = $mInfo['id'] ?? null;
                if (str_starts_with($rowMasterChoice, 'existing:')) {
                    $overrideMid = (int)substr($rowMasterChoice, 9);
                    if ($overrideMid > 0) {
                        $masterItemId = $overrideMid;
                    }
                } elseif ($rowMasterChoice === 'none') {
                    $masterItemId = null;
                }

                // Resolusi Merk untuk baris ini
                $brandId = !empty($row['brand_id']) ? (int)$row['brand_id'] : ($mInfo['brand_id'] ?? null);
                $brandName = trim((string)($row['brand'] ?? ''));
                if ($brandId > 0) {
                    $stB = $pdo->prepare("SELECT brand_name FROM asset_brands WHERE id = ?");
                    $stB->execute([$brandId]);
                    $bDbName = (string)($stB->fetchColumn() ?: '');
                    if ($bDbName !== '') {
                        $brandName = $bDbName;
                    }
                } elseif ($brandName !== '') {
                    $brandId = legacy_find_or_create_brand($pdo, $brandName, $groupId, $typeId, $newBrandCount);
                } elseif (!empty($mInfo['brand_name'])) {
                    $brandName = (string)$mInfo['brand_name'];
                }

                // Resolusi Lokasi untuk baris ini (mendukung pilihan per baris atau mengikuti ajuan rancangan lokasi)
                $locId = $locRes[$row['loc_key'] ?? ''] ?? null;
                $rowLocChoice = (string)($row['row_location_choice'] ?? 'plan');
                if (str_starts_with($rowLocChoice, 'existing:')) {
                    $overrideLid = (int)substr($rowLocChoice, 9);
                    if ($overrideLid > 0) {
                        $locId = $overrideLid;
                    }
                } elseif ($rowLocChoice === 'none') {
                    $locId = null;
                }

                $newAssetCode = generate_next_asset_code($pdo, $gCode, $tCode);
                $model = trim((string)($row['model'] ?? '')) ?: (string)($mInfo['model'] ?? '');
                $assetName = trim((string)($row['asset_name'] ?? ''));
                if ($assetName === '') {
                    $assetName = (string)($mInfo['item_name'] ?? '') ?: (($brandName ? $brandName . ' ' : '') . $model) ?: $newAssetCode;
                }
                $installedAt = !empty($row['installed_at']) ? $row['installed_at'] : null;
                $serialNumber = trim((string)($row['serial_number'] ?? ''));
                $custodianName = trim((string)($row['custodian_name'] ?? ''));
                $locLabel = trim((string)($row['loc_label'] ?? ''));
                $rawNotes = 'Diimpor dari data lama (Legacy). Deskripsi asli: ' . trim((string)($row['raw_description'] ?? ''));

                $insStmt->execute([
                    $masterItemId,
                    $brandId ?: null,
                    $newAssetCode,
                    $legacyCode,
                    $groupId,
                    $typeId,
                    $activeStatusId,
                    $assetName,
                    $brandName !== '' ? $brandName : null,
                    $model !== '' ? $model : null,
                    $serialNumber !== '' ? $serialNumber : null,
                    $custodianName !== '' ? $custodianName : null,
                    $rawNotes,
                    $companyId,
                    $locId,
                    $locLabel !== '' ? $locLabel : null,
                    $installedAt,
                    $typeName,
                    $typeName,
                ]);
                $newId = (int)$pdo->lastInsertId();

                // Simpan Identifiers (Serial Number, MAC, dll.)
                if ($serialNumber !== '') {
                    $idfStmt = $pdo->prepare("SELECT id FROM asset_type_identifiers WHERE asset_type_id = ? AND (UPPER(identifier_code) LIKE '%SERIAL%' OR UPPER(identifier_code) LIKE '%SN%') LIMIT 1");
                    $idfStmt->execute([$typeId]);
                    $idfId = (int)($idfStmt->fetchColumn() ?: 0);
                    if ($idfId > 0) {
                        $pdo->prepare("INSERT INTO asset_identifiers (asset_item_id, asset_type_identifier_id, identifier_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE identifier_value=VALUES(identifier_value)")
                            ->execute([$newId, $idfId, $serialNumber]);
                    }
                }
                if (!empty($row['mac_address'])) {
                    $idfMacStmt = $pdo->prepare("SELECT id FROM asset_type_identifiers WHERE asset_type_id = ? AND UPPER(identifier_code) LIKE '%MAC%' LIMIT 1");
                    $idfMacStmt->execute([$typeId]);
                    $macIdfId = (int)($idfMacStmt->fetchColumn() ?: 0);
                    if ($macIdfId > 0) {
                        $pdo->prepare("INSERT INTO asset_identifiers (asset_item_id, asset_type_identifier_id, identifier_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE identifier_value=VALUES(identifier_value)")
                            ->execute([$newId, $macIdfId, trim((string)$row['mac_address'])]);
                    }
                }

                // Simpan Spesifikasi sesuai Kategori ke tabel asset_specifications (kolom specification_value)
                $specsToSave = (array)($row['specs_map'] ?? []);
                foreach ($specsToSave as $spCode => $spVal) {
                    $spVal = trim((string)$spVal);
                    if ($spVal === '') continue;
                    $spFindStmt = $pdo->prepare("SELECT id FROM asset_type_specifications WHERE asset_type_id = ? AND UPPER(specification_code) = UPPER(?) LIMIT 1");
                    $spFindStmt->execute([$typeId, $spCode]);
                    $spId = (int)($spFindStmt->fetchColumn() ?: 0);
                    if ($spId > 0) {
                        $pdo->prepare("INSERT INTO asset_specifications (asset_item_id, asset_type_specification_id, specification_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE specification_value=VALUES(specification_value)")
                            ->execute([$newId, $spId, $spVal]);
                    }
                }

                $importedCount++;
            }

            $pdo->commit();
            unset($_SESSION['asset_legacy_import_preview'], $_SESSION['asset_legacy_import_opts'], $_SESSION['asset_legacy_import_plan']);

            $extraInfo = [];
            if ($newBrandCount > 0) $extraInfo[] = "{$newBrandCount} Merk baru";
            if ($newMasterCount > 0) $extraInfo[] = "{$newMasterCount} Master Barang baru";
            if ($newLocCount > 0) $extraInfo[] = "{$newLocCount} Master Lokasi baru";

            flash("Berhasil mengimpor {$importedCount} unit aset ke kategori '{$typeName}'!"
                . (!empty($extraInfo) ? ' (Tersimpan otomatis ke Master: ' . implode(', ', $extraInfo) . ').' : '')
                . ($skippedCount > 0 ? " ({$skippedCount} baris dilewati karena kode lama duplikat atau kosong)." : ''));
            redirect_to('asset_items');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash("Gagal memproses import data aset: " . $e->getMessage(), 'err');
            redirect_to('asset_legacy_import');
        }
    }

    // -------------------------------------------------------------------------
    // 4. Proses Upload File & Persiapan Form Konfirmasi dan Editing (Step 2)
    // -------------------------------------------------------------------------
    if ($action === 'upload_preview' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        @set_time_limit(180);

        $groupId = (int)($_POST['asset_group_id'] ?? 0);
        $typeId = (int)($_POST['asset_type_id'] ?? 0);
        if ($groupId <= 0 || $typeId <= 0) {
            flash('Silakan pilih Komoditas dan Kategori Aset terlebih dahulu sebelum mengunggah file.', 'err');
            redirect_to('asset_legacy_import');
        }

        if (empty($_FILES['import_file']['tmp_name']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) {
            flash('Silakan pilih file Excel (.xlsx) atau CSV terlebih dahulu.', 'err');
            redirect_to('asset_legacy_import');
        }

        $fileTmp = $_FILES['import_file']['tmp_name'];
        $fileName = (string)($_FILES['import_file']['name'] ?? '');
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            flash('Format file tidak didukung. Gunakan file Excel (.xlsx) atau CSV (.csv).', 'err');
            redirect_to('asset_legacy_import');
        }

        $rawRows = parse_legacy_spreadsheet_file($fileTmp, $ext);
        if (empty($rawRows)) {
            flash('File kosong atau tidak dapat dibaca. Pastikan file berisi data tabel.', 'err');
            redirect_to('asset_legacy_import');
        }

        $gStmt = $pdo->prepare("SELECT group_code, group_name FROM asset_groups WHERE id = ?");
        $gStmt->execute([$groupId]);
        $gRow = $gStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $gCode = strtoupper(trim((string)($gRow['group_code'] ?? 'FAC')));
        $gName = (string)($gRow['group_name'] ?? 'Facility');

        $tStmt = $pdo->prepare("SELECT type_code, type_name FROM asset_types WHERE id = ?");
        $tStmt->execute([$typeId]);
        $tRow = $tStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $tCode = strtoupper(trim((string)($tRow['type_code'] ?? 'AC')));
        $tName = (string)($tRow['type_name'] ?? 'AC / Pendingin');

        // Deteksi indeks kolom pada baris header (NO, NAMA INVENTARIS + SPESIFIKASI, RUANGAN, LANTAI, RUKO)
        $headerIdx = -1;
        $colNo = -1;
        $colDesc = -1;
        $colRuangan = -1;
        $colLantai = -1;
        $colRuko = -1;

        foreach ($rawRows as $rIdx => $cells) {
            if ($rIdx > 15) break;
            foreach ($cells as $cIdx => $val) {
                $vClean = strtoupper(trim((string)$val));
                if ($vClean === 'NO' || $vClean === 'NO.' || $vClean === 'KODE LAMA' || $vClean === 'KODE' || $vClean === 'ID ASET') {
                    if ($colNo === -1) $colNo = $cIdx;
                }
                if (str_contains($vClean, 'NAMA INVENTARIS') || str_contains($vClean, 'SPESIFIKASI') || str_contains($vClean, 'NAMA BARANG') || str_contains($vClean, 'DESKRIPSI')) {
                    $colDesc = $cIdx;
                }
                if (str_contains($vClean, 'RUANGAN') || $vClean === 'RUANG') {
                    $colRuangan = $cIdx;
                }
                if (str_contains($vClean, 'LANTAI') || $vClean === 'LT') {
                    $colLantai = $cIdx;
                }
                if (str_contains($vClean, 'RUKO') || str_contains($vClean, 'GEDUNG') || str_contains($vClean, 'CABANG')) {
                    $colRuko = $cIdx;
                }
            }
            if ($colNo !== -1 && $colDesc !== -1) {
                $headerIdx = $rIdx;
                break;
            }
        }

        if ($headerIdx === -1) {
            $headerIdx = 0;
            $colNo = 0;
            $colDesc = 1;
            $colRuangan = 2;
            $colLantai = 3;
            $colRuko = 4;
        }

        $useAi = !empty($_POST['use_ai']);
        $companyId = (int)($_POST['company_id'] ?? 0) ?: null;

        // Pastikan skema specification pada tipe terpilih sudah ada (misal untuk AC)
        ensure_category_default_specs($pdo, $typeId, $tCode);

        $previewList = [];
        $planMasters = [];
        $planLocations = [];
        $usedProposedLocCodes = [];
        $autoCreatedBrandsCount = 0;

        $checkDupStmt = $pdo->prepare("SELECT id, asset_code FROM asset_items WHERE alias_code = ? LIMIT 1");
        $findMasterStmt = $pdo->prepare("SELECT m.id, m.item_code, m.item_name, m.model_name, m.brand_id, COALESCE(b.brand_name, '') AS brand_name
                                         FROM asset_master_items m
                                         LEFT JOIN asset_brands b ON b.id = m.brand_id
                                         WHERE m.asset_group_id = ? AND m.asset_type_id = ?
                                           AND (UPPER(TRIM(m.item_name)) = UPPER(TRIM(?)) OR (m.brand_id = ? AND UPPER(TRIM(COALESCE(m.model_name, ''))) = UPPER(TRIM(?)) AND ? <> ''))
                                         LIMIT 1");

        for ($i = $headerIdx + 1, $total = count($rawRows); $i < $total; $i++) {
            $row = $rawRows[$i];
            $rawNo = trim((string)($row[$colNo] ?? ''));
            $rawDesc = trim((string)($row[$colDesc] ?? ''));
            $rawRuangan = $colRuangan !== -1 ? trim((string)($row[$colRuangan] ?? '')) : '';
            $rawLantai = $colLantai !== -1 ? trim((string)($row[$colLantai] ?? '')) : '';
            $rawRuko = $colRuko !== -1 ? trim((string)($row[$colRuko] ?? '')) : '';

            if ($rawNo === '' && $rawDesc === '') {
                continue;
            }
            if (strtoupper($rawNo) === 'NO' || str_contains(strtoupper($rawDesc), 'NAMA INVENTARIS')) {
                continue;
            }

            // 1. Kode Lama (Legacy) default dari kolom "NO" + ekstraksi tanggal beli setelah "/"
            [$legacyCode, $installedAt] = extract_legacy_code_and_date($rawNo);
            if ($legacyCode === '') {
                $legacyCode = 'OLD-ROW-' . $i;
            }

            $checkDupStmt->execute([$legacyCode]);
            $dupRow = $checkDupStmt->fetch(PDO::FETCH_ASSOC);

            // 2. AI mencari Merk dari "Nama Inventaris + Spesifikasi", masukkan ke database merk (`asset_brands`),
            //    pilih merk tersebut, dan rancang kombinasi dengan Komoditas, Kategori & Spesifikasi sebagai Master Barang
            $parsed = parse_asset_description_by_category($pdo, $rawDesc, $legacyCode, $gCode, $tCode, $useAi, $groupId, $typeId);
            $detectedBrandName = trim((string)($parsed['brand'] ?? ''));
            $brandId = null;
            if ($detectedBrandName !== '') {
                // Otomatis masukkan ke database merk (`asset_brands`) jika belum ada dan pilih ID-nya
                $brandId = legacy_find_or_create_brand($pdo, $detectedBrandName, $groupId, $typeId, $autoCreatedBrandsCount);
                if ($brandId) {
                    $stB = $pdo->prepare("SELECT brand_name FROM asset_brands WHERE id = ?");
                    $stB->execute([$brandId]);
                    $detectedBrandName = (string)($stB->fetchColumn() ?: $detectedBrandName);
                }
            }

            // Rancang kombinasi Nama Master Barang = Kategori + Merk + Model/Spesifikasi
            $proposedMasterName = legacy_compose_master_item_name($gName, $tName, $tCode, $detectedBrandName, (string)($parsed['model'] ?? ''), (array)($parsed['specs_map'] ?? []), (string)($parsed['asset_name'] ?? ''), $rawDesc);
            $proposedModel = trim((string)($parsed['model'] ?? ''));
            if ($proposedModel === '' && !empty($parsed['specs_plain'])) {
                $proposedModel = mb_substr((string)$parsed['specs_plain'], 0, 80);
            }

            $masterKey = 'M_' . substr(md5(strtoupper($groupId . '|' . $typeId . '|' . $detectedBrandName . '|' . $proposedMasterName . '|' . $proposedModel)), 0, 10);
            if (!isset($planMasters[$masterKey])) {
                $findMasterStmt->execute([$groupId, $typeId, $proposedMasterName, (int)($brandId ?? 0), $proposedModel, $proposedModel]);
                $existingMaster = $findMasterStmt->fetch(PDO::FETCH_ASSOC);
                $proposedSku = $existingMaster ? (string)$existingMaster['item_code'] : generate_master_item_code($pdo, $groupId, $typeId, (int)($brandId ?? 0));

                $planMasters[$masterKey] = [
                    'key' => $masterKey,
                    'mode' => $existingMaster ? 'existing' : 'new',
                    'master_item_id' => $existingMaster ? (int)$existingMaster['id'] : 0,
                    'existing_code' => $existingMaster ? (string)$existingMaster['item_code'] : '',
                    'existing_name' => $existingMaster ? (string)$existingMaster['item_name'] : '',
                    'proposed_code' => $proposedSku,
                    'group_name' => $gName,
                    'type_name' => $tName,
                    'brand_id' => (int)($brandId ?? 0),
                    'brand_name' => $detectedBrandName,
                    'item_name' => $existingMaster ? (string)$existingMaster['item_name'] : $proposedMasterName,
                    'model' => $existingMaster && !empty($existingMaster['model_name']) ? (string)$existingMaster['model_name'] : $proposedModel,
                    'specs_map' => (array)($parsed['specs_map'] ?? []),
                    'specs_text' => (string)($parsed['specs_plain'] ?? ''),
                    'sample_desc' => $rawDesc,
                    'row_count' => 0,
                    'legacy_codes' => [],
                ];
            }
            $planMasters[$masterKey]['row_count']++;
            if ($legacyCode !== '' && !in_array($legacyCode, $planMasters[$masterKey]['legacy_codes'] ?? [], true)) {
                $planMasters[$masterKey]['legacy_codes'][] = $legacyCode;
            }

            // 3. Ajuan Rancangan Lokasi Baru dari Kolom "Ruko", "Lantai", dan "Ruangan"
            $locProposal = legacy_build_location_proposal($pdo, $rawRuko, $rawLantai, $rawRuangan, $usedProposedLocCodes);
            $locKey = $locProposal['key'];
            if (!isset($planLocations[$locKey])) {
                $planLocations[$locKey] = $locProposal;
                $planLocations[$locKey]['row_count'] = 0;
                $planLocations[$locKey]['legacy_codes'] = [];
            }
            $planLocations[$locKey]['row_count']++;
            if ($legacyCode !== '' && !in_array($legacyCode, $planLocations[$locKey]['legacy_codes'] ?? [], true)) {
                $planLocations[$locKey]['legacy_codes'][] = $legacyCode;
            }

            $previewList[] = [
                'row_num'         => $i + 1,
                'raw_no'          => $rawNo,
                'legacy_code'     => $legacyCode,
                'installed_at'    => $installedAt,
                'raw_description' => $rawDesc,
                'raw_ruangan'     => $rawRuangan,
                'raw_lantai'      => $rawLantai,
                'raw_ruko'        => $rawRuko,
                'brand_id'        => (int)($brandId ?? 0),
                'brand'           => $detectedBrandName,
                'model'           => $proposedModel,
                'asset_name'      => $proposedMasterName,
                'serial_number'   => $parsed['serial_number'],
                'mac_address'     => $parsed['mac_address'],
                'specs_map'       => $parsed['specs_map'],
                'specs_summary'   => $parsed['specs_summary'],
                'specs_plain'     => $parsed['specs_plain'],
                'custodian_name'  => $parsed['custodian_name'],
                'master_key'      => $masterKey,
                'loc_key'         => $locKey,
                'loc_id'          => $locProposal['location_id'],
                'loc_master_name' => $locProposal['name'],
                'loc_label'       => $locProposal['detail_label'],
                'loc_is_new'      => $locProposal['mode'] === 'new',
                'ai_method'       => $parsed['ai_method'],
                'is_duplicate'    => !empty($dupRow),
                'dup_asset_code'  => $dupRow['asset_code'] ?? '',
            ];
        }

        $_SESSION['asset_legacy_import_preview'] = $previewList;
        $_SESSION['asset_legacy_import_plan'] = [
            'masters' => $planMasters,
            'locations' => $planLocations,
        ];
        $_SESSION['asset_legacy_import_opts'] = [
            'company_id'     => $companyId,
            'asset_group_id' => $groupId,
            'asset_type_id'  => $typeId,
            'group_code'     => $gCode,
            'group_name'     => $gName,
            'type_code'      => $tCode,
            'type_name'      => $tName,
            'use_ai'         => $useAi,
            'auto_brands'    => $autoCreatedBrandsCount,
        ];

        redirect_to('asset_legacy_import');
    }

    // -------------------------------------------------------------------------
    // 5. Render Tampilan Utama (Step 1: Upload / Step 2: Form Konfirmasi & Editing)
    // -------------------------------------------------------------------------
    render_header('Import Data Aset Lama (Legacy)', $user);

    $previewList = $_SESSION['asset_legacy_import_preview'] ?? [];
    $opts = $_SESSION['asset_legacy_import_opts'] ?? [];
    $plan = $_SESSION['asset_legacy_import_plan'] ?? ['masters' => [], 'locations' => []];

    if (!empty($previewList)) {
        render_legacy_import_preview_selection_html($pdo, $previewList, $opts, $plan);
    } else {
        render_legacy_import_init_form_html($pdo);
    }

    render_footer();
}

/**
 * Mencari Merk pada tabel `asset_brands` untuk Komoditas & Kategori terkait,
 * atau otomatis memasukkannya ke database `asset_brands` jika belum ada, lalu mengembalikan ID-nya.
 */
/**
 * Format badge daftar Kode Lama (Legacy) untuk ditampilkan di kartu/panel konfirmasi.
 */
function legacy_render_code_badges(array $codes, int $maxDisplay = 8): string
{
    $codes = array_values(array_unique(array_filter($codes, fn($c) => trim((string)$c) !== '')));
    if (empty($codes)) {
        return '<span class="muted" style="font-size:11px;">-</span>';
    }

    $badges = [];
    $displayCodes = array_slice($codes, 0, $maxDisplay);
    foreach ($displayCodes as $c) {
        $badges[] = '<span style="display:inline-block;padding:2px 7px;border-radius:4px;background:#fef3c7;color:#92400e;font-size:11px;font-weight:700;border:1px solid #fde68a;font-family:monospace;margin:1px 2px;">🏷️ ' . e($c) . '</span>';
    }
    if (count($codes) > $maxDisplay) {
        $badges[] = '<span class="muted" style="font-size:11px;font-weight:600;margin-left:3px;">+' . (count($codes) - $maxDisplay) . ' lainnya</span>';
    }
    return implode(' ', $badges);
}

function legacy_find_or_create_brand(PDO $pdo, string $brandName, int $groupId, int $typeId, int &$createdCount = 0): ?int
{
    $brandName = mb_substr(trim($brandName), 0, 120);
    if ($brandName === '' || $groupId <= 0 || $typeId <= 0) {
        return null;
    }

    // 1. Cari pada pasangan Komoditas & Kategori yang dipilih
    $st = $pdo->prepare("SELECT id FROM asset_brands WHERE asset_group_id = ? AND asset_type_id = ? AND UPPER(TRIM(brand_name)) = UPPER(TRIM(?)) LIMIT 1");
    $st->execute([$groupId, $typeId, $brandName]);
    if ($id = $st->fetchColumn()) {
        return (int)$id;
    }

    // 2. Buat kode brand unik & masukkan ke tabel `asset_brands`
    $slug = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $brandName));
    $baseCode = 'BRD-' . substr($slug !== '' ? $slug : 'GEN', 0, 6);
    $brandCode = $baseCode;
    $seq = 1;
    $stCode = $pdo->prepare("SELECT id FROM asset_brands WHERE asset_group_id = ? AND asset_type_id = ? AND brand_code = ? LIMIT 1");
    while (true) {
        $stCode->execute([$groupId, $typeId, $brandCode]);
        if (!$stCode->fetchColumn()) {
            break;
        }
        $brandCode = $baseCode . '-' . $seq++;
    }

    $ins = $pdo->prepare("INSERT INTO asset_brands (asset_group_id, asset_type_id, brand_code, brand_name, description, is_active) VALUES (?, ?, ?, ?, 'Ditambahkan otomatis oleh AI saat Import Data Aset Lama', 1)");
    $ins->execute([$groupId, $typeId, $brandCode, $brandName]);
    $createdCount++;

    return (int)$pdo->lastInsertId();
}

/**
 * Merancang kode lokasi unik (`location_code`) untuk ajuan lokasi baru.
 */
function legacy_propose_location_code(PDO $pdo, string $rawText, array &$usedInBatch = [], bool $isExplicitCode = false): string
{
    $clean = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', trim($rawText)));
    if ($isExplicitCode && $clean !== '') {
        $base = substr($clean, 0, 24);
    } else {
        $slug = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawText));
        $base = 'LOC-' . substr($slug !== '' ? $slug : 'AREA', 0, 12);
    }

    $candidate = $base;
    $seq = 1;
    $st = $pdo->prepare("SELECT id FROM asset_locations WHERE UPPER(location_code) = UPPER(?) LIMIT 1");
    while (true) {
        $st->execute([$candidate]);
        if (!$st->fetchColumn() && !isset($usedInBatch[strtoupper($candidate)])) {
            break;
        }
        $candidate = substr($base, 0, 24) . '-' . $seq++;
    }
    $usedInBatch[strtoupper($candidate)] = true;
    return $candidate;
}

/**
 * Menyusun JSON spesifikasi Master Barang berdasarkan `asset_type_specifications`.
 */
function legacy_build_master_specifications_json(PDO $pdo, int $typeId, array $specsMap, string $fallbackText = ''): ?string
{
    $mapped = [];
    if ($typeId > 0 && !empty($specsMap)) {
        $st = $pdo->prepare("SELECT id, UPPER(specification_code) AS code FROM asset_type_specifications WHERE asset_type_id = ?");
        $st->execute([$typeId]);
        $codeToId = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $codeToId[(string)$r['code']] = (int)$r['id'];
        }
        foreach ($specsMap as $code => $val) {
            $uCode = strtoupper(trim((string)$code));
            $valStr = trim((string)$val);
            if ($valStr !== '' && isset($codeToId[$uCode])) {
                $mapped[(string)$codeToId[$uCode]] = $valStr;
            }
        }
    }
    if (empty($mapped) && trim($fallbackText) !== '') {
        $mapped['summary'] = trim($fallbackText);
    }
    return !empty($mapped) ? json_encode($mapped, JSON_UNESCAPED_UNICODE) : null;
}

/**
 * Merancang kombinasi Nama Master Barang dari Komoditas, Kategori, Merk, dan Spesifikasi.
 */
function legacy_compose_master_item_name(
    string $groupName,
    string $typeName,
    string $typeCode,
    string $brandName,
    string $model,
    array $specsMap,
    string $parsedAssetName,
    string $rawDesc
): string {
    $parts = [];

    // Awali dengan label pendek kategori bila belum tercermin
    $shortType = trim(explode('/', $typeName)[0]);
    if ($typeCode === 'AC') {
        $shortType = 'AC';
    } elseif ($typeCode === 'NBK') {
        $shortType = 'Laptop';
    } elseif ($typeCode === 'CMP') {
        $shortType = 'PC Desktop';
    }

    if ($shortType !== '') {
        $parts[] = $shortType;
    }
    if ($brandName !== '' && stripos(implode(' ', $parts), $brandName) === false) {
        $parts[] = $brandName;
    }
    if ($model !== '' && stripos(implode(' ', $parts), $model) === false) {
        $parts[] = $model;
    }

    // Tambahkan highlight spesifikasi utama agar kombinasi Master Barang lebih deskriptif
    foreach (['COOLING_CAP', 'REFRIGERANT', 'INVERTER', 'PROCESSOR', 'RAM', 'STORAGE', 'POWER_CAP'] as $spKey) {
        if (!empty($specsMap[$spKey])) {
            $val = trim((string)$specsMap[$spKey]);
            if ($val !== '' && stripos(implode(' ', $parts), $val) === false && ! ($spKey === 'INVERTER' && strcasecmp($val, 'Standard') === 0)) {
                $parts[] = $val;
            }
        }
    }

    $composed = trim(preg_replace('/\s+/', ' ', implode(' ', $parts)));
    if ($composed === '' || strcasecmp($composed, $shortType) === 0) {
        $composed = trim($parsedAssetName) !== '' ? trim($parsedAssetName) : mb_strimwidth(trim($rawDesc), 0, 80, '');
    }

    return mb_substr($composed, 0, 180);
}

/**
 * Membuat Ajuan Rancangan Lokasi Baru dari kolom "Ruko", "Lantai", dan "Ruangan",
 * serta memeriksa apakah sudah ada lokasi yang cocok di tabel `asset_locations`.
 */
function legacy_build_location_proposal(PDO $pdo, string $ruko, string $lantai, string $ruangan, array &$usedCodes = []): array
{
    $ruko = trim($ruko);
    $lantai = trim($lantai);
    $ruangan = trim($ruangan);

    $rukoFormatted = '';
    if ($ruko !== '') {
        $rukoFormatted = (stripos($ruko, 'ruko') === false && stripos($ruko, 'gedung') === false && stripos($ruko, 'kantor') === false && stripos($ruko, 'cabang') === false)
            ? 'Ruko ' . $ruko
            : $ruko;
    }
    $lantaiFormatted = '';
    if ($lantai !== '') {
        $lantaiFormatted = (stripos($lantai, 'lantai') === false && stripos($lantai, 'lt') === false)
            ? 'Lantai ' . $lantai
            : $lantai;
    }

    $fullParts = array_values(array_filter([$rukoFormatted, $lantaiFormatted, $ruangan], fn($v) => $v !== ''));
    $fullProposedName = !empty($fullParts) ? implode(' - ', $fullParts) : 'Lokasi Umum';
    $rukoOnlyName = $rukoFormatted !== '' ? $rukoFormatted : $fullProposedName;

    $detailParts = array_values(array_filter([$lantaiFormatted, $ruangan], fn($v) => $v !== ''));
    $detailLabel = implode(' - ', $detailParts);

    // Cari apakah kombinasi penuh atau nama Ruko sudah terdaftar di Master Lokasi (`asset_locations`)
    $st = $pdo->prepare("SELECT id, location_code, location_name FROM asset_locations WHERE UPPER(TRIM(location_name)) IN (UPPER(TRIM(?)), UPPER(TRIM(?)), UPPER(TRIM(?))) LIMIT 1");
    $st->execute([$fullProposedName, $rukoOnlyName, $ruko]);
    $found = $st->fetch(PDO::FETCH_ASSOC);

    $locKey = 'L_' . substr(md5(strtoupper($ruko . '|' . $lantai . '|' . $ruangan)), 0, 10);
    $proposedCode = $found ? (string)$found['location_code'] : legacy_propose_location_code($pdo, $fullProposedName, $usedCodes);

    return [
        'key'             => $locKey,
        'raw_ruko'        => $ruko,
        'raw_lantai'      => $lantai,
        'raw_ruangan'     => $ruangan,
        'full_name'       => $fullProposedName,
        'ruko_only_name'  => $rukoOnlyName,
        'detail_label'    => $detailLabel,
        'mode'            => $found ? 'existing' : 'new',
        'location_id'     => $found ? (int)$found['id'] : 0,
        'existing_code'   => $found ? (string)$found['location_code'] : '',
        'existing_name'   => $found ? (string)$found['location_name'] : '',
        'code'            => $proposedCode,
        'name'            => $found ? (string)$found['location_name'] : $fullProposedName,
    ];
}

/**
 * Memastikan spesifikasi standar tersedia untuk kategori yang dipilih (misal: AC, PC, Laptop).
 */
function ensure_category_default_specs(PDO $pdo, int $typeId, string $tCode): void
{
    if ($typeId <= 0) return;

    $defaults = [];
    if ($tCode === 'AC') {
        $defaults = [
            ['COOLING_CAP', 'Kapasitas Pendinginan (PK / BTU)', 'text', 10],
            ['AC_TYPE', 'Tipe AC (Split Wall / Cassette / Standing)', 'text', 20],
            ['REFRIGERANT', 'Jenis Freon / Refrigerant (R32 / R410A / R22)', 'text', 30],
            ['POWER_WATT', 'Daya Listrik (Watt)', 'text', 40],
            ['INVERTER', 'Tipe Kompresor (Inverter / Standard)', 'text', 50],
        ];
    } elseif (in_array($tCode, ['CMP', 'NBK', 'SRV'], true)) {
        $defaults = [
            ['PROCESSOR', 'Processor (CPU)', 'text', 10],
            ['RAM', 'Kapasitas RAM', 'text', 20],
            ['STORAGE', 'Kapasitas Storage (SSD / HDD)', 'text', 30],
            ['OS', 'Sistem Operasi (OS)', 'text', 40],
        ];
    }

    if (!empty($defaults)) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO asset_type_specifications (asset_type_id, specification_code, specification_name, data_type, is_required, display_order) VALUES (?, ?, ?, ?, 0, ?)");
        foreach ($defaults as $d) {
            $stmt->execute([$typeId, $d[0], $d[1], $d[2], $d[3]]);
        }
    }
}

/**
 * Membaca file Excel (.xlsx) atau CSV (.csv) secara native tanpa library eksternal.
 */
function parse_legacy_spreadsheet_file(string $filePath, string $ext): array
{
    $rows = [];
    if ($ext === 'csv' || $ext === 'txt') {
        $fh = fopen($filePath, 'r');
        if (!$fh) return [];
        $firstLine = fgets($fh);
        rewind($fh);
        $delim = (substr_count((string)$firstLine, ';') > substr_count((string)$firstLine, ',')) ? ';' : ',';
        while (($data = fgetcsv($fh, 0, $delim)) !== false) {
            if (!empty(array_filter($data, fn($v) => trim((string)$v) !== ''))) {
                $rows[] = array_map(fn($v) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$v)), $data);
            }
        }
        fclose($fh);
        return $rows;
    }

    if ($ext === 'xlsx' && class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            $sharedStrings = [];
            $ssXmlStr = $zip->getFromName('xl/sharedStrings.xml');
            if ($ssXmlStr !== false) {
                $ssXml = @simplexml_load_string($ssXmlStr);
                if ($ssXml && isset($ssXml->si)) {
                    foreach ($ssXml->si as $si) {
                        if (isset($si->t)) {
                            $sharedStrings[] = (string)$si->t;
                        } elseif (isset($si->r)) {
                            $txt = '';
                            foreach ($si->r as $run) {
                                $txt .= (string)$run->t;
                            }
                            $sharedStrings[] = $txt;
                        } else {
                            $sharedStrings[] = '';
                        }
                    }
                }
            }

            $sheetXmlStr = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXmlStr === false) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if (preg_match('#xl/worksheets/sheet\d*\.xml#i', $name)) {
                        $sheetXmlStr = $zip->getFromName($name);
                        break;
                    }
                }
            }

            if ($sheetXmlStr !== false) {
                $sheetXml = @simplexml_load_string($sheetXmlStr);
                if ($sheetXml && isset($sheetXml->sheetData->row)) {
                    foreach ($sheetXml->sheetData->row as $rowNode) {
                        $rowMap = [];
                        $maxCol = 0;
                        foreach ($rowNode->c as $cell) {
                            $cellRef = (string)$cell['r'];
                            $colIdx = 0;
                            if (!empty($cellRef)) {
                                preg_match('/^([A-Z]+)/i', $cellRef, $matches);
                                if (!empty($matches[1])) {
                                    $letters = strtoupper($matches[1]);
                                    $len = strlen($letters);
                                    for ($k = 0; $k < $len; $k++) {
                                        $colIdx = $colIdx * 26 + (ord($letters[$k]) - ord('A') + 1);
                                    }
                                    $colIdx -= 1;
                                }
                            } else {
                                $colIdx = $maxCol;
                            }

                            if ($colIdx > 40) {
                                continue;
                            }

                            $val = isset($cell->v) ? (string)$cell->v : '';
                            $type = (string)$cell['t'];
                            if ($type === 's' && isset($sharedStrings[(int)$val])) {
                                $val = $sharedStrings[(int)$val];
                            } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                                $val = (string)$cell->is->t;
                            }
                            $rowMap[$colIdx] = trim($val);
                            if ($colIdx > $maxCol) {
                                $maxCol = min($colIdx, 40);
                            }
                        }

                        if (!empty($rowMap)) {
                            $row = [];
                            $limitCol = min($maxCol, 40);
                            for ($c = 0; $c <= $limitCol; $c++) {
                                $row[$c] = $rowMap[$c] ?? '';
                            }
                            if (!empty(array_filter($row, fn($v) => $v !== ''))) {
                                $rows[] = $row;
                            }
                        }
                    }
                }
            }
            $zip->close();
            return $rows;
        }
    }

    return $rows;
}

/**
 * Ekstraksi Kode Lama (Legacy) dan Tanggal Perolehan dari kolom "NO".
 * Default Kode Lama berasal dari nilai kolom "NO", sedangkan teks setelah "/" diekstrak menjadi tanggal beli.
 */
function extract_legacy_code_and_date(string $rawNo): array
{
    $rawNo = trim($rawNo);
    $legacyCode = $rawNo;
    $installedAt = null;

    if (strpos($rawNo, '/') !== false) {
        $parts = explode('/', $rawNo, 2);
        $afterSlash = trim($parts[1]);

        if (preg_match('/(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})/', $afterSlash, $m)) {
            $d = (int)$m[1];
            $mon = (int)$m[2];
            $y = (int)$m[3];
            if ($y < 100) {
                $y += 2000;
            }
            if ($d > 12 && $mon <= 12) {
                $installedAt = sprintf('%04d-%02d-%02d', $y, $mon, $d);
            } elseif ($mon > 12 && $d <= 12) {
                $installedAt = sprintf('%04d-%02d-%02d', $y, $d, $mon);
            } else {
                $installedAt = sprintf('%04d-%02d-%02d', $y, $mon, $d);
            }
        } elseif (preg_match('/(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})/', $afterSlash, $m)) {
            $installedAt = sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
        }
    }

    return [$legacyCode, $installedAt];
}

/**
 * AI & Smart Engine untuk membedah kolom "NAMA INVENTARIS + SPESIFIKASI":
 * - Mencari Merk (baik dari database `asset_brands`, kamus merk lengkap, maupun pola token setelah jenis barang).
 * - Mengekstrak Model, Serial Number, MAC Address, dan Spesifikasi teknis sesuai Kategori.
 */
function parse_asset_description_by_category(
    PDO $pdo,
    string $desc,
    string $legacyCode,
    string $gCode,
    string $tCode,
    bool $useAi = true,
    int $groupId = 0,
    int $typeId = 0
): array {
    $desc = trim($desc);
    $result = [
        'brand'          => '',
        'model'          => '',
        'asset_name'     => '',
        'serial_number'  => '',
        'mac_address'    => '',
        'specs_map'      => [],
        'specs_summary'  => '',
        'specs_plain'    => '',
        'custodian_name' => '',
        'ai_method'      => 'AI Smart Engine',
    ];

    if ($desc === '') {
        return $result;
    }

    // 1. Ambil daftar merk dari database `asset_brands` + kamus merk industri
    static $dbBrands = null;
    if ($dbBrands === null) {
        try {
            $dbBrands = $pdo->query("SELECT DISTINCT brand_name FROM asset_brands WHERE (is_active = 1 OR is_active IS NULL) AND brand_name <> '' ORDER BY CHAR_LENGTH(brand_name) DESC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) {
            $dbBrands = [];
        }
    }

    $knownBrands = array_unique(array_merge($dbBrands, [
        'Sharp', 'Daikin', 'Panasonic', 'Mitsubishi Electric', 'Mitsubishi', 'LG', 'Gree', 'Samsung', 'AUX',
        'Changhong', 'Toshiba', 'Polytron', 'Haier', 'Aqua', 'Midea', 'TCL', 'Hisense', 'York', 'McQuay',
        'Carrier', 'Trane', 'Sanyo', 'Akari', 'Uchida', 'Denpoo', 'Modena', 'Ariston', 'Rinnai',
        'Electrolux', 'Philips', 'Miyako', 'Cosmos', 'Krisbow', 'Karcher', 'Bosch', 'Makita', 'Hitachi',
        'Lenovo', 'Dell', 'HP', 'Hewlett Packard', 'Asus', 'Acer', 'Apple', 'MSI', 'Gigabyte', 'ASRock',
        'Intel', 'AMD', 'Razer', 'Microsoft', 'Huawei', 'Xiaomi', 'Zyrex', 'Axioo', 'Advan', 'BenQ',
        'ViewSonic', 'AOC', 'Zebra', 'Honeywell', 'Epson', 'Canon', 'Brother', 'Fuji Xerox', 'Ricoh',
        'Kyocera', 'Konica Minolta', 'Lexmark', 'Cisco', 'MikroTik', 'Ruijie', 'Reyee', 'Ubiquiti', 'UniFi',
        'TP-Link', 'D-Link', 'Tenda', 'Totolink', 'Netgear', 'Aruba', 'Fortinet', 'FortiGate', 'Sophos',
        'Juniper', 'Hikvision', 'Dahua', 'Ezviz', 'SPC', 'Bardi', 'APC', 'Eaton', 'Prolink', 'ICA',
        'Laplace', 'Vertiv', 'Liebert', 'Synology', 'QNAP', 'Seagate', 'Western Digital', 'WD', 'SanDisk',
        'Kingston', 'Corsair', 'Crucial', 'Logitech', 'Jabra', 'Poly', 'Plantronics', 'Yealink', 'Fanvil',
        'Grandstream', 'Avaya', 'PABX', 'Perkins', 'Cummins', 'Yanmar', 'Denyo', 'Kubota', 'Caterpillar',
        'Stamford', 'Honda', 'Yamaha', 'Suzuki', 'Toyota', 'Daihatsu', 'Isuzu', 'Mitsubishi Fuso', 'Hino',
        'Wuling', 'Hyundai', 'BYD', 'Informa', 'IKEA', 'Chitose', 'Futura', 'Brother', 'Lion', 'Alba',
        'Daiko', 'Indachi', 'Chairman', 'Donati', 'Savello', 'Subaru', 'Kris', 'Ace',
    ]));

    foreach ($knownBrands as $b) {
        $bTrim = trim((string)$b);
        if ($bTrim === '') continue;
        if (preg_match('/\b' . preg_quote($bTrim, '/') . '\b/i', $desc)) {
            $result['brand'] = $bTrim;
            break;
        }
    }

    // Jika belum terdeteksi dari kamus, AI heuristik mengekstrak token merk setelah kata kunci jenis barang
    if ($result['brand'] === '') {
        $stopWords = ['SPLIT', 'WALL', 'CASSETTE', 'STANDING', 'FLOOR', 'INVERTER', 'STANDARD', 'LOW', 'WATT', 'PK', 'BTU', 'CORE', 'INTEL', 'AMD', 'RYZEN', 'RAM', 'SSD', 'HDD', 'DAN', 'DENGAN', 'UNTUK', 'BARU', 'LAMA', 'PUTIH', 'HITAM', 'SILVER', ' R32', 'R410A', 'R22'];
        if (preg_match('/^(?:AC|LAPTOP|NOTEBOOK|PC|KOMPUTER|DESKTOP|MONITOR|PRINTER|SCANNER|UPS|ROUTER|SWITCH|MODEM|CCTV|TV|TELEVISI|KULKAS|DISPENSER|GENSET|POMPA|MESIN|KIPAS|EXHAUST|KURSI|MEJA|LEMARI|BRANKAS)\s+([A-Za-z][A-Za-z0-9\-]{2,18})\b/i', $desc, $mGuess)) {
            $candidate = strtoupper(trim($mGuess[1]));
            if (!in_array($candidate, $stopWords, true) && !preg_match('/^\d/', $candidate)) {
                $result['brand'] = ucfirst(strtolower($candidate));
            }
        }
    }

    // 2. Deteksi Serial Number & MAC Address
    if (preg_match('/\b(?:S\/N|SN|Serial\s*(?:Number|No)?|Service\s*Tag)\s*[:#-]?\s*([A-Za-z0-9_-]{4,36})\b/i', $desc, $mSn)) {
        $result['serial_number'] = trim($mSn[1]);
    }
    if (preg_match('/([0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2})/', $desc, $mMac)) {
        $result['mac_address'] = strtoupper(trim($mMac[1]));
    }

    $specs = [];

    // 3. Ekstraksi Spesifikasi & Model berdasarkan Kategori
    if ($tCode === 'AC' || preg_match('/\b(AC|PK|BTU|R32|R410A|R22|FREON)\b/i', $desc)) {
        if (preg_match('/(\d+(?:\s+\d+\/\d+|\s*\/\s*\d+|\.\d+|\,\d+)?\s*(?:PK|BTU(?:\/H)?))/i', $desc, $mPk)) {
            $specs['COOLING_CAP'] = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(',', '.', $mPk[1]))));
            $result['model'] = $specs['COOLING_CAP'];
        }
        if (preg_match('/\b(Split(?:\s*Wall)?|Cassette|Floor\s*Standing|Standing\s*Floor|Ducting|Portable|Central|Window|Ceiling)\b/i', $desc, $mType)) {
            $specs['AC_TYPE'] = ucwords(strtolower(trim($mType[1])));
        } else {
            $specs['AC_TYPE'] = 'Split Wall';
        }
        if (preg_match('/\b(R32|R410A|R22|R290|R134A)\b/i', $desc, $mFreon)) {
            $specs['REFRIGERANT'] = strtoupper(trim($mFreon[1]));
        }
        $specs['INVERTER'] = (stripos($desc, 'inverter') !== false) ? 'Inverter' : 'Standard';
        if (preg_match('/(\d+\s*(?:Watt|W))\b/i', $desc, $mWatt)) {
            $specs['POWER_WATT'] = trim($mWatt[1]);
        }
        if (preg_match('/\b([A-Z]{2,4}[-\s]?[A-Z0-9]{3,10})\b/', strtoupper($desc), $mModelCode)) {
            $candModel = trim($mModelCode[1]);
            if (!in_array($candModel, ['R32', 'R410A', 'R22', 'PK', 'BTU', strtoupper($result['brand'])], true)) {
                $result['model'] = trim(($result['model'] ? $result['model'] . ' ' : '') . $candModel);
            }
        }
        $cleanNameParts = ['AC'];
        if (!empty($result['brand'])) $cleanNameParts[] = $result['brand'];
        if (!empty($specs['COOLING_CAP'])) $cleanNameParts[] = $specs['COOLING_CAP'];
        if (!empty($specs['REFRIGERANT'])) $cleanNameParts[] = $specs['REFRIGERANT'];
        if (($specs['INVERTER'] ?? '') === 'Inverter') $cleanNameParts[] = 'Inverter';
        $result['asset_name'] = implode(' ', $cleanNameParts);
    } elseif (in_array($tCode, ['CMP', 'NBK', 'SRV'], true) || preg_match('/\b(Core\s*i[3579]|Ryzen|Xeon|RAM|SSD|HDD|ThinkPad|Latitude|OptiPlex|ProBook|EliteBook|MacBook|IdeaPad|VivoBook|Inspiron|Vostro)\b/i', $desc)) {
        if (preg_match('/\b(ThinkPad\s+[A-Za-z0-9-]+|IdeaPad\s+[A-Za-z0-9-]+|Latitude\s+[A-Za-z0-9-]+|OptiPlex\s+[A-Za-z0-9-]+|Inspiron\s+[A-Za-z0-9-]+|Vostro\s+[A-Za-z0-9-]+|ProBook\s+[A-Za-z0-9-]+|EliteBook\s+[A-Za-z0-9-]+|Pavilion\s+[A-Za-z0-9-]+|MacBook\s+(?:Air|Pro)[\w\s-]*|VivoBook\s+[A-Za-z0-9-]+|ZenBook\s+[A-Za-z0-9-]+|Aspire\s+[A-Za-z0-9-]+|TravelMate\s+[A-Za-z0-9-]+)\b/i', $desc, $mSeries)) {
            $result['model'] = trim($mSeries[1]);
        }
        if (preg_match('/\b(Intel\s+(?:Core\s+)?[iI][3579][\w-]*|Core\s+[iI][3579][\w-]*|Ryzen\s+[3579][\w-]*|Xeon[\w-]*|Celeron[\w-]*|Pentium[\w-]*|Apple\s+M[1234][\w\s]*)\b/i', $desc, $mCpu)) {
            $specs['PROCESSOR'] = trim($mCpu[1]);
        }
        if (preg_match('/\b(?:RAM\s*:?\s*)?(\d+\s*(?:GB|MB)(?:\s*DDR\d)?)\b/i', $desc, $mRam)) {
            $specs['RAM'] = strtoupper(trim($mRam[1]));
        }
        if (preg_match('/\b((?:SSD|HDD|NVMe)\s*\d+\s*(?:GB|TB)|\d+\s*(?:GB|TB)\s*(?:SSD|HDD|NVMe))\b/i', $desc, $mSto)) {
            $specs['STORAGE'] = strtoupper(trim($mSto[1]));
        }
        if (preg_match('/\b(Windows\s+(?:11|10|8|7|Server)\s*(?:Pro|Home|Enterprise)?|Ubuntu|macOS|Linux)\b/i', $desc, $mOs)) {
            $specs['OS'] = trim($mOs[1]);
        }
        $prefix = ($tCode === 'NBK' || stripos($desc, 'laptop') !== false || stripos($desc, 'notebook') !== false) ? 'Laptop' : 'PC Desktop';
        $nameParts = [$prefix];
        if (!empty($result['brand'])) $nameParts[] = $result['brand'];
        if (!empty($result['model'])) $nameParts[] = $result['model'];
        if (!empty($specs['PROCESSOR'])) $nameParts[] = $specs['PROCESSOR'];
        if (!empty($specs['RAM'])) $nameParts[] = $specs['RAM'];
        if (!empty($specs['STORAGE'])) $nameParts[] = $specs['STORAGE'];
        $result['asset_name'] = implode(' ', $nameParts);
    } elseif (in_array($tCode, ['VEH', 'GEN', 'MAC'], true) || stripos($desc, 'kva') !== false || stripos($desc, 'genset') !== false) {
        if (preg_match('/(\d+(?:\.\d+)?\s*(?:KVA|KW|HP))\b/i', $desc, $mKva)) {
            $specs['POWER_CAP'] = strtoupper(trim($mKva[1]));
            $result['model'] = $specs['POWER_CAP'];
        }
        if (preg_match('/(\d+\s*cc)\b/i', $desc, $mCc)) {
            $specs['ENGINE_CC'] = trim($mCc[1]);
        }
        if (preg_match('/\b(?:No\.?\s*(?:Rangka|Mesin|Polisi)|Plat)\s*[:#]?\s*([A-Za-z0-9\s]{4,15})\b/i', $desc, $mPlat)) {
            $specs['NO_REG'] = trim($mPlat[1]);
        }
        $result['asset_name'] = trim(($result['brand'] ?: '') . ' ' . ($specs['POWER_CAP'] ?? $desc));
    } else {
        // Kategori umum: ekstrak model/spesifikasi setelah nama merk
        if ($result['brand'] !== '') {
            $afterBrand = trim(preg_replace('/^.*?\b' . preg_quote($result['brand'], '/') . '\b[\s:,-]*/i', '', $desc));
            $afterBrand = trim(preg_replace('/\b(?:S\/N|SN|Serial\s*(?:Number|No)?)\s*[:#-]?\s*[A-Za-z0-9_-]+/i', '', $afterBrand));
            if ($afterBrand !== '') {
                $result['model'] = mb_strimwidth($afterBrand, 0, 60, '');
            }
        }
        $result['asset_name'] = mb_strimwidth($desc, 0, 80, '');
    }

    if (empty($result['asset_name'])) {
        $result['asset_name'] = mb_strimwidth($desc, 0, 80, '');
    }

    $result['specs_map'] = $specs;
    $summaryLines = [];
    $plainLines = [];
    foreach ($specs as $k => $v) {
        $summaryLines[] = "<strong>{$k}:</strong> " . e((string)$v);
        $plainLines[] = "{$k}: {$v}";
    }
    $result['specs_summary'] = implode(' &bull; ', $summaryLines);
    $result['specs_plain'] = !empty($plainLines) ? implode(', ', $plainLines) : $desc;

    return $result;
}

/**
 * Merender Step 1: Form Pemilihan Komoditas & Kategori (Dropdown Dependen) dan Upload File.
 */
function render_legacy_import_init_form_html(PDO $pdo): void
{
    $groups = $pdo->query("SELECT id, group_code, group_name FROM asset_groups ORDER BY group_name")->fetchAll(PDO::FETCH_ASSOC);
    $types = $pdo->query("SELECT id, asset_group_id, type_code, type_name FROM asset_types WHERE (is_active=1 OR is_active IS NULL) ORDER BY type_name")->fetchAll(PDO::FETCH_ASSOC);

    $jsonTypes = json_encode($types, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    $companies = company_options($pdo, 0, true, '- Pilih Company -');

    echo '<section class="panel">'
        . '<div class="split" style="align-items:center;margin-bottom:14px;">'
        . '  <div>'
        . '    <h1 style="margin:0;">📥 Import Data Aset Lama (Legacy)</h1>'
        . '    <p class="muted" style="margin:4px 0 0 0;">Pilih Komoditas dan Kategori tujuan terlebih dahulu, kemudian upload file Excel (.xlsx) / CSV inventaris lama Anda untuk membuka Form Konfirmasi & Editing.</p>'
        . '  </div>'
        . '  <div>'
        . '    <a class="btn" href="' . route_url('asset_legacy_import', ['action' => 'download_template']) . '" style="background:#0284c7;color:#fff;border-color:#0284c7;font-weight:600;">📥 Download Template Excel / CSV</a>'
        . '  </div>'
        . '</div>';

    echo '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px 18px;margin-bottom:20px;font-size:13px;color:#1e40af;line-height:1.7;">'
        . '📌 <strong>Alur Otomatisasi AI & Form Konfirmasi setelah Upload:</strong><br>'
        . '&bull; <strong>Kode Lama (Legacy) Unit Aset:</strong> Otomatis diambil default dari kolom <code>NO</code> (misal <code>1 /21-2-2023</code>) dan dapat diedit pada form konfirmasi. Tanggal setelah tanda <code>/</code> otomatis diekstrak menjadi <strong>Tanggal Perolehan</strong>.<br>'
        . '&bull; <strong>AI Merk & Rancangan Master Barang:</strong> Dari kolom <code>NAMA INVENTARIS + SPESIFIKASI</code>, AI otomatis mencari <strong>Merk</strong>, memasukkannya ke database <strong>Master Brand (`asset_brands`)</strong> serta memilihnya, lalu merancang kombinasi (<em>Komoditas + Kategori + Merk + Spesifikasi/Model</em>) sebagai <strong>Master Barang (`asset_master_items`)</strong>.<br>'
        . '&bull; <strong>Ajuan Rancangan Lokasi Baru:</strong> Dari kolom <code>RUKO</code>, <code>LANTAI</code>, dan <code>RUANGAN</code>, sistem menyusun ajuan lokasi baru untuk dimasukkan ke <strong>Master Lokasi (`asset_locations`)</strong>, lengkap dengan akses tombol <strong>Buka Menu Master Lokasi</strong> atau pilih langsung dari <strong>Master Lokasi</strong> eksisting.'
        . '</div>';

    echo '<form method="post" enctype="multipart/form-data" action="' . route_url('asset_legacy_import', ['action' => 'upload_preview']) . '">'
        . '<input type="hidden" name="csrf" value="' . csrf_token() . '">'
        . '<div class="grid two" style="background:#f8fafc;padding:16px;border:1px solid #cbd5e1;border-radius:8px;margin-bottom:16px;">'
        . '  <label style="font-weight:700;color:#0f172a;">1. Pilih Komoditas Aset *'
        . '    <select id="importGroupSelect" name="asset_group_id" required onchange="filterImportCategoryOptions()" style="height:42px;margin-top:6px;font-size:14px;background:#fff;border:2px solid #2563eb;">'
        . '      <option value="">-- Pilih Komoditas Terlebih Dahulu --</option>';
    foreach ($groups as $g) {
        echo '<option value="' . (int)$g['id'] . '">' . e($g['group_code'] . ' - ' . $g['group_name']) . '</option>';
    }
    echo '    </select>'
        . '  </label>'
        . '  <label style="font-weight:700;color:#0f172a;">2. Pilih Kategori Aset *'
        . '    <select id="importTypeSelect" name="asset_type_id" required style="height:42px;margin-top:6px;font-size:14px;background:#fff;border:2px solid #2563eb;">'
        . '      <option value="">-- Pilih Komoditas Terlebih Dahulu --</option>'
        . '    </select>'
        . '  </label>'
        . '</div>'
        . '<div class="grid two">'
        . '  <label>File Excel (.xlsx) / CSV (.csv) *<input type="file" name="import_file" accept=".xlsx,.csv,.txt" required style="padding:8px;background:#fff;"></label>'
        . '  <label>Company Pemilik Aset<select name="company_id">' . $companies . '</select></label>'
        . '</div>'
        . '<div style="background:#f8fafc;padding:12px 16px;border:1px solid #e2e8f0;border-radius:6px;margin:14px 0;">'
        . '  <label style="display:flex;align-items:center;gap:10px;margin:0;cursor:pointer;font-weight:600;color:#0f172a;">'
        . '    <input type="checkbox" name="use_ai" value="1" checked style="width:18px;height:18px;accent-color:#2563eb;">'
        . '    <span>🤖 Aktifkan AI Ekstraksi Merk, Perancangan Kombinasi Master Barang & Ajuan Lokasi Baru</span>'
        . '  </label>'
        . '</div>'
        . '<div class="actions" style="margin-top:20px;">'
        . '  <button type="submit" class="btn primary" style="padding:10px 24px;font-size:14px;font-weight:700;">🔍 Upload & Buka List Pilihan Import</button>'
        . '  <a class="btn" href="' . route_url('asset_items') . '">Kembali ke Daftar Aset</a>'
        . '</div>'
        . '</form>'
        . '</section>';

    $jsonCatMasters = json_encode($categoryMasters, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    $jsonAllLocs = json_encode($allLocations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    $jsonPlanMasters = json_encode($planMasters, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

    echo '<script>
    window._allCategoryMasters = ' . $jsonCatMasters . ';
    window._allCategoryLocations = ' . $jsonAllLocs . ';
    window._planMastersData = ' . $jsonPlanMasters . ';
    var allTypes = ' . $jsonTypes . ';
    function filterImportCategoryOptions() {
        var gSel = document.getElementById("importGroupSelect");
        var tSel = document.getElementById("importTypeSelect");
        if (!gSel || !tSel) return;
        var gid = parseInt(gSel.value, 10) || 0;
        tSel.innerHTML = "";
        if (gid <= 0) {
            tSel.innerHTML = "<option value=\"\">-- Pilih Komoditas Terlebih Dahulu --</option>";
            return;
        }
        var filtered = allTypes.filter(function(t) {
            return parseInt(t.asset_group_id, 10) === gid;
        });
        if (filtered.length === 0) {
            tSel.innerHTML = "<option value=\"\">-- Belum ada kategori di komoditas ini --</option>";
            return;
        }
        tSel.innerHTML = "<option value=\"\">-- Pilih Kategori Aset --</option>";
        filtered.forEach(function(t) {
            var opt = document.createElement("option");
            opt.value = t.id;
            opt.textContent = t.type_code + " - " + t.type_name;
            tSel.appendChild(opt);
        });
    }
    </script>';
}

/**
 * Merender Step 2: Form Konfirmasi dan Editing Interaktif setelah "Upload & Buka List Pilihan Import".
 * Meliputi:
 * - Panel 1: Konfirmasi Merk (`asset_brands`) & Rancangan AI Master Barang (`asset_master_items`)
 * - Panel 2: Ajuan Rancangan Lokasi Baru dari Kolom "Ruko", "Lantai", dan "Ruangan" ke Master Lokasi (`asset_locations`)
 * - Panel 3: Tabel Konfirmasi & Editing Baris Unit Aset (Kode Lama Legacy dari kolom "No", Tgl Perolehan, Merk, Master Barang, SN, dan Lokasi)
 */
function render_legacy_import_preview_selection_html(PDO $pdo, array $previewList, array $opts, array $plan = []): void
{
    $totalRows = count($previewList);
    $dupCount = count(array_filter($previewList, fn($x) => !empty($x['is_duplicate'])));
    $groupId = (int)($opts['asset_group_id'] ?? 0);
    $typeId = (int)($opts['asset_type_id'] ?? 0);
    $targetGroupName = (string)($opts['group_name'] ?? 'Facility');
    $targetTypeName = (string)($opts['type_name'] ?? 'AC / Pendingin');
    $autoBrands = (int)($opts['auto_brands'] ?? 0);

    $planMasters = (array)($plan['masters'] ?? []);
    $planLocations = (array)($plan['locations'] ?? []);

    // Muat daftar Master Lokasi, Master Merk (pada kategori ini), dan Master Barang (pada kategori ini)
    $allLocations = $pdo->query("SELECT id, location_code, location_name FROM asset_locations WHERE (is_active = 1 OR is_active IS NULL) ORDER BY location_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    $stBrands = $pdo->prepare("SELECT id, brand_code, brand_name FROM asset_brands WHERE asset_group_id = ? AND asset_type_id = ? AND (is_active = 1 OR is_active IS NULL) ORDER BY brand_name ASC");
    $stBrands->execute([$groupId, $typeId]);
    $categoryBrands = $stBrands->fetchAll(PDO::FETCH_ASSOC);

    $stMasters = $pdo->prepare("SELECT m.id, m.item_code, m.item_name, m.model_name, m.brand_id, COALESCE(b.brand_name, '') AS brand_name
                                FROM asset_master_items m
                                LEFT JOIN asset_brands b ON b.id = m.brand_id
                                WHERE m.asset_group_id = ? AND m.asset_type_id = ? AND (m.is_active = 1 OR m.is_active IS NULL)
                                ORDER BY m.item_name ASC");
    $stMasters->execute([$groupId, $typeId]);
    $categoryMasters = $stMasters->fetchAll(PDO::FETCH_ASSOC);

    echo '<section class="panel">'
        . '<div class="split" style="align-items:center;margin-bottom:14px;">'
        . '  <div>'
        . '    <h1 style="margin:0;">🛠️ Form Konfirmasi & Editing Import Data Aset Lama (Legacy)</h1>'
        . '    <p class="muted" style="margin:4px 0 0 0;">Tinjau & edit hasil deteksi AI untuk <strong>Merk & Rancangan Master Barang</strong>, <strong>Ajuan Lokasi Baru (Ruko, Lantai, Ruangan)</strong>, serta <strong>Kode Lama (Legacy)</strong> tiap unit sebelum disimpan.</p>'
        . '  </div>'
        . '  <div style="display:flex;gap:8px;flex-wrap:wrap;">'
        . '    <span class="badge ok" style="font-size:12.5px;padding:6px 12px;">Terbaca: ' . $totalRows . ' Baris</span>'
        . '    <span class="badge" style="background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;font-size:12.5px;padding:6px 12px;">🤖 ' . count($planMasters) . ' Rancangan Master Barang</span>'
        . '    <span class="badge" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;font-size:12.5px;padding:6px 12px;">📍 ' . count($planLocations) . ' Ajuan Lokasi</span>'
        . ($dupCount > 0 ? ('<span class="badge danger" style="font-size:12.5px;padding:6px 12px;">Duplikat Kode Lama: ' . $dupCount . '</span>') : '')
        . '  </div>'
        . '</div>';

    // Banner Komoditas & Kategori Aktif
    echo '<div style="background:#eff6ff;border:2px solid #3b82f6;border-radius:8px;padding:12px 18px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">'
        . '<div>'
        . '  <span style="font-size:11px;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:0.5px;">Target Komoditas & Kategori Import:</span><br>'
        . '  <strong style="font-size:15.5px;color:#1e3a8a;">📁 Komoditas: ' . e($targetGroupName) . ' &bull; 🏷️ Kategori: ' . e($targetTypeName) . '</strong>'
        . ($autoBrands > 0 ? ' <span style="display:inline-block;margin-left:8px;padding:2px 8px;border-radius:99px;background:#dcfce7;color:#166534;font-size:11.5px;font-weight:700;">✓ ' . $autoBrands . ' Merk Baru Otomatis Dimasukkan ke Database Merk</span>' : '')
        . '</div>'
        . '<div style="display:flex;gap:8px;flex-wrap:wrap;">'
        . '  <a class="btn" href="' . route_url('asset_legacy_import', ['action' => 'reset']) . '" style="background:#fff;color:#dc2626;border-color:#fca5a5;font-size:12.5px;">🔄 Ganti Kategori / Upload File Lain</a>'
        . '</div>'
        . '</div>';

    echo '<form id="importExecForm" method="post" action="' . route_url('asset_legacy_import', ['action' => 'execute_import']) . '">'
        . '<input type="hidden" id="importCsrfToken" name="csrf" value="' . csrf_token() . '">'
        . '<input type="hidden" name="company_id" value="' . (int)($opts['company_id'] ?? 0) . '">'
        . '<input type="hidden" id="importGroupIdHidden" name="asset_group_id" value="' . $groupId . '">'
        . '<input type="hidden" id="importTypeIdHidden" name="asset_type_id" value="' . $typeId . '">';

    // =========================================================================
    // PANEL 1: AI EKSTRAKSI MERK & RANCANGAN KOMBINASI MASTER BARANG
    // =========================================================================
    echo '<div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:10px;padding:16px 18px;margin-bottom:20px;">'
        . '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:12px;">'
        . '  <div>'
        . '    <h2 style="margin:0;font-size:16px;color:#0f172a;">🤖 1. Konfirmasi Merk & Rancangan AI Master Barang (Kombinasi Komoditas + Kategori + Merk + Spesifikasi)</h2>'
        . '    <p class="muted" style="margin:4px 0 0 0;font-size:12.5px;">Dari kolom <strong>"Nama Inventaris + Spesifikasi"</strong>, AI mendeteksi <strong>Merk</strong>, memasukkannya ke database <strong>Master Brand</strong> serta memilihnya, dan merancang kombinasi <strong>Master Barang</strong> di bawah ini.</p>'
        . '  </div>'
        . '  <div style="display:flex;gap:8px;flex-wrap:wrap;">'
        . '    <a class="btn" href="' . route_url('asset_brands') . '" target="_blank" style="background:#fff;color:#0369a1;border-color:#bae6fd;font-size:12px;font-weight:600;">🏷️ Buka Master Brand ↗</a>'
        . '    <a class="btn" href="' . route_url('asset_master_items') . '" target="_blank" style="background:#fff;color:#0369a1;border-color:#bae6fd;font-size:12px;font-weight:600;">📦 Buka Master Barang ↗</a>'
        . '    <button type="button" class="btn" onclick="refreshMasterAndLocationDropdowns()" style="background:#e0f2fe;color:#0369a1;border-color:#7dd3fc;font-size:12px;font-weight:700;">🔄 Muat Ulang Master</button>'
        . '  </div>'
        . '</div>';

    echo '<div style="overflow-x:auto;border:1px solid #e2e8f0;border-radius:8px;background:#fff;">'
        . '<table style="margin:0;font-size:12.5px;min-width:1150px;">'
        . '<thead><tr style="background:#f1f5f9;">'
        . '<th style="width:160px;">Komoditas & Kategori</th>'
        . '<th style="width:180px;">Kode Lama (Legacy)</th>'
        . '<th style="width:200px;">Merk Terdeteksi AI (Database Merk)</th>'
        . '<th>Rancangan Kombinasi Master Barang (Nama Barang, Model & Spesifikasi)</th>'
        . '<th style="width:310px;">Tindakan Master Barang (Buat Baru / Pilih Eksisting)</th>'
        . '</tr></thead><tbody>';

    foreach ($planMasters as $mKey => $mCfg) {
        $isExistingM = (($mCfg['mode'] ?? 'new') === 'existing' && (int)($mCfg['master_item_id'] ?? 0) > 0);
        $selBrandId = (int)($mCfg['brand_id'] ?? 0);

        $brandOptionsHtml = '<option value="0">-- Pilih / Ketik Merk Baru --</option>';
        foreach ($categoryBrands as $b) {
            $sel = ((int)$b['id'] === $selBrandId) ? ' selected' : '';
            $brandOptionsHtml .= '<option value="' . (int)$b['id'] . '"' . $sel . '>' . e($b['brand_name'] . ' (' . $b['brand_code'] . ')') . '</option>';
        }

        $masterOptionsHtml = '<option value="0">-- Pilih dari Master Barang --</option>';
        foreach ($categoryMasters as $cm) {
            $selM = ((int)$cm['id'] === (int)($mCfg['master_item_id'] ?? 0)) ? ' selected' : '';
            $lblM = $cm['item_code'] . ' - ' . $cm['item_name'] . ($cm['brand_name'] ? ' [' . $cm['brand_name'] . ']' : '');
            $masterOptionsHtml .= '<option value="' . (int)$cm['id'] . '"' . $selM . '>' . e($lblM) . '</option>';
        }

        $statusTagId = 'masterStatusTag_' . e($mKey);
        $statusTagHtml = $isExistingM
            ? '<span id="' . $statusTagId . '" class="badge ok" style="font-size:11px;">✓ Terhubung ke Master Barang (' . e($mCfg['existing_code'] ?: 'Eksisting') . ')</span>'
            : '<span id="' . $statusTagId . '" class="badge" style="background:#dbeafe;color:#1e40af;font-size:11px;">✨ Rancangan Baru (SKU: ' . e($mCfg['proposed_code'] ?? '-') . ')</span>';

        $masterLegacyBadges = legacy_render_code_badges((array)($mCfg['legacy_codes'] ?? []));
        echo '<tr id="masterPlanRow_' . e($mKey) . '">'
            . '<td style="vertical-align:top;">'
            . '  <div style="font-weight:700;color:#1e3a8a;">' . e($mCfg['group_name']) . '</div>'
            . '  <div style="font-size:12px;color:#0284c7;font-weight:600;">' . e($mCfg['type_name']) . '</div>'
            . '  <div class="muted" style="font-size:11px;margin-top:4px;">Dipakai oleh <strong>' . (int)$mCfg['row_count'] . ' baris</strong> unit</div>'
            . '</td>'
            . '<td style="vertical-align:top;">'
            . '  <div style="font-size:10.5px;font-weight:700;color:#92400e;text-transform:uppercase;margin-bottom:3px;">Kode Lama Unit:</div>'
            . '  <div style="max-height:90px;overflow-y:auto;padding:2px 0;">' . $masterLegacyBadges . '</div>'
            . '</td>'
            . '<td style="vertical-align:top;">'
            . '  <select id="planMasterBrand_' . e($mKey) . '" name="plan_master[' . e($mKey) . '][brand_id]" class="js-brand-select" style="width:100%;padding:5px 7px;font-size:12px;border:1px solid #cbd5e1;border-radius:5px;background:#fff;font-weight:600;">'
            .      $brandOptionsHtml
            . '  </select>'
            . '  <input type="text" id="planMasterBrandCustom_' . e($mKey) . '" name="plan_master[' . e($mKey) . '][brand_custom]" value="" placeholder="Atau ketik merk baru..." style="margin-top:5px;width:100%;padding:4px 7px;font-size:11.5px;border:1px dashed #94a3b8;border-radius:5px;box-sizing:border-box;">'
            . ($selBrandId > 0 ? '<div style="font-size:11px;color:#166534;margin-top:3px;font-weight:600;">✓ Terdaftar & Terpilih di Database Merk</div>' : '<div style="font-size:11px;color:#b45309;margin-top:3px;">⚠️ Merk belum terdeteksi, silakan pilih/isi</div>')
            . '</td>'
            . '<td style="vertical-align:top;">'
            . '  <div style="margin-bottom:5px;display:flex;justify-content:space-between;align-items:center;gap:6px;flex-wrap:wrap;">'
            . '    <span style="font-size:11px;color:#475569;">Contoh Sumber: <em>"' . e(mb_strimwidth((string)$mCfg['sample_desc'], 0, 75, '...')) . '"</em></span>'
            .      $statusTagHtml
            . '  </div>'
            . '  <div class="grid two" style="gap:6px;margin-bottom:5px;">'
            . '    <div>'
            . '      <span style="font-size:10.5px;font-weight:700;color:#334155;display:block;margin-bottom:2px;">Nama Rancangan Master Barang:</span>'
            . '      <input type="text" id="planMasterName_' . e($mKey) . '" name="plan_master[' . e($mKey) . '][item_name]" value="' . e($mCfg['item_name']) . '" placeholder="Nama Master Barang" style="width:100%;padding:5px 8px;font-size:12.5px;font-weight:700;border:1px solid #93c5fd;border-radius:5px;box-sizing:border-box;">'
            . '    </div>'
            . '    <div>'
            . '      <span style="font-size:10.5px;font-weight:700;color:#334155;display:block;margin-bottom:2px;">Model / Tipe / Kapasitas:</span>'
            . '      <input type="text" id="planMasterModel_' . e($mKey) . '" name="plan_master[' . e($mKey) . '][model]" value="' . e($mCfg['model']) . '" placeholder="Model / Tipe" style="width:100%;padding:5px 8px;font-size:12px;border:1px solid #cbd5e1;border-radius:5px;box-sizing:border-box;">'
            . '    </div>'
            . '  </div>'
            . '  <div>'
            . '    <span style="font-size:10.5px;font-weight:700;color:#334155;display:block;margin-bottom:2px;">Kombinasi Spesifikasi Teknis:</span>'
            . '    <input type="text" id="planMasterSpecs_' . e($mKey) . '" name="plan_master[' . e($mKey) . '][specs_text]" value="' . e($mCfg['specs_text']) . '" placeholder="Spesifikasi hasil ekstraksi AI" style="width:100%;padding:4px 8px;font-size:11.5px;border:1px solid #e2e8f0;border-radius:5px;box-sizing:border-box;">'
            . '  </div>'
            . '</td>'
            . '<td style="vertical-align:top;">'
            . '  <select id="planMasterMode_' . e($mKey) . '" name="plan_master[' . e($mKey) . '][mode]" onchange="toggleMasterPlanMode(\'' . e($mKey) . '\')" style="width:100%;padding:5px 7px;font-size:12px;font-weight:700;border:1px solid #3b82f6;border-radius:5px;background:#eff6ff;color:#1e3a8a;margin-bottom:6px;">'
            . '    <option value="new"' . (!$isExistingM ? ' selected' : '') . '>➕ Simpan sebagai Master Barang Baru</option>'
            . '    <option value="existing"' . ($isExistingM ? ' selected' : '') . '>📦 Pilih dari Master Barang Eksisting</option>'
            . '    <option value="skip">⏭️ Jangan Hubungkan Master Barang</option>'
            . '  </select>'
            . '  <div id="planMasterExistingWrap_' . e($mKey) . '" style="' . ($isExistingM ? '' : 'display:none;') . 'margin-bottom:6px;">'
            . '    <select id="planMasterSelect_' . e($mKey) . '" name="plan_master[' . e($mKey) . '][master_item_id]" class="js-master-select" onchange="onPlanMasterSelectedChange(\'' . e($mKey) . '\', this.value)" style="width:100%;padding:5px 7px;font-size:12px;border:1px solid #cbd5e1;border-radius:5px;background:#fff;">'
            .        $masterOptionsHtml
            . '    </select>'
            . '  </div>'
            . '  <button type="button" id="btnSaveMasterNow_' . e($mKey) . '" class="btn" onclick="saveMasterItemProposalNow(\'' . e($mKey) . '\')" style="width:100%;background:#0284c7;color:#fff;border-color:#0284c7;font-size:11.5px;padding:6px 10px;font-weight:700;' . ($isExistingM ? 'display:none;' : '') . '">⚡ Simpan ke Master Barang Sekarang</button>'
            . '</td>'
            . '</tr>';
    }

    echo '</tbody></table></div></div>';

    // =========================================================================
    // PANEL 2: AJUAN RANCANGAN LOKASI BARU DARI KOLOM "RUKO", "LANTAI", DAN "RUANGAN"
    // =========================================================================
    echo '<div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;padding:16px 18px;margin-bottom:20px;">'
        . '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:12px;">'
        . '  <div>'
        . '    <h2 style="margin:0;font-size:16px;color:#78350f;">📍 2. Ajuan Rancangan Lokasi Baru (dari Kolom "Ruko", "Lantai", dan "Ruangan") & Master Lokasi</h2>'
        . '    <p class="muted" style="margin:4px 0 0 0;font-size:12.5px;color:#92400e;">Dari data import kolom <strong>Ruko</strong>, <strong>Lantai</strong>, dan <strong>Ruangan</strong>, berikut ajuan rancangan lokasi baru yang akan dimasukkan ke <strong>Master Lokasi</strong>. Anda dapat langsung menyimpannya, membuka menu <strong>Master Lokasi</strong>, atau memilih dari <strong>Master Lokasi</strong> yang sudah ada.</p>'
        . '  </div>'
        . '  <div style="display:flex;gap:8px;flex-wrap:wrap;">'
        . '    <a class="btn" href="' . route_url('asset_locations') . '" target="_blank" style="background:#d97706;color:#fff;border-color:#b45309;font-size:12px;font-weight:700;">🗺️ Buka Menu Master Lokasi ↗</a>'
        . '    <button type="button" class="btn" onclick="refreshMasterAndLocationDropdowns()" style="background:#fff;color:#92400e;border-color:#f59e0b;font-size:12px;font-weight:700;">🔄 Muat Ulang Daftar Master Lokasi</button>'
        . '  </div>'
        . '</div>';

    echo '<div style="overflow-x:auto;border:1px solid #fde68a;border-radius:8px;background:#fff;">'
        . '<table style="margin:0;font-size:12.5px;min-width:1150px;">'
        . '<thead><tr style="background:#fef3c7;color:#78350f;">'
        . '<th style="width:220px;">Data Import (Ruko / Lantai / Ruangan)</th>'
        . '<th style="width:180px;">Kode Lama (Legacy)</th>'
        . '<th style="width:210px;">Keputusan Lokasi</th>'
        . '<th>Ajuan Rancangan Lokasi Baru / Pilihan Master Lokasi</th>'
        . '<th style="width:200px;text-align:center;">Aksi Cepat ke Master Lokasi</th>'
        . '</tr></thead><tbody>';

    foreach ($planLocations as $lKey => $lCfg) {
        $isExistingL = (($lCfg['mode'] ?? 'new') === 'existing' && (int)($lCfg['location_id'] ?? 0) > 0);
        $locOptionsHtml = '<option value="0">-- Pilih dari Master Lokasi --</option>';
        foreach ($allLocations as $loc) {
            $selL = ((int)$loc['id'] === (int)($lCfg['location_id'] ?? 0)) ? ' selected' : '';
            $locOptionsHtml .= '<option value="' . (int)$loc['id'] . '"' . $selL . '>' . e($loc['location_name'] . ' (' . $loc['location_code'] . ')') . '</option>';
        }

        $locStatusId = 'locStatusTag_' . e($lKey);
        $locStatusBadge = $isExistingL
            ? '<span id="' . $locStatusId . '" class="badge ok" style="font-size:11px;">✓ Sudah Ada di Master Lokasi</span>'
            : '<span id="' . $locStatusId . '" class="badge" style="background:#fef3c7;color:#92400e;border:1px solid #f59e0b;font-size:11px;">+ Ajuan Lokasi Baru</span>';

        $locLegacyBadges = legacy_render_code_badges((array)($lCfg['legacy_codes'] ?? []));
        echo '<tr id="locPlanRow_' . e($lKey) . '">'
            . '<td style="vertical-align:top;">'
            . '  <div style="font-size:12px;">🏢 <strong>Ruko:</strong> ' . e($lCfg['raw_ruko'] ?: '-') . '</div>'
            . '  <div style="font-size:12px;">🪜 <strong>Lantai:</strong> ' . e($lCfg['raw_lantai'] ?: '-') . '</div>'
            . '  <div style="font-size:12px;">🚪 <strong>Ruangan:</strong> ' . e($lCfg['raw_ruangan'] ?: '-') . '</div>'
            . '  <div class="muted" style="font-size:11px;margin-top:4px;">Digunakan pada <strong>' . (int)$lCfg['row_count'] . ' baris</strong> (' . $locStatusBadge . ')</div>'
            . '</td>'
            . '<td style="vertical-align:top;">'
            . '  <div style="font-size:10.5px;font-weight:700;color:#92400e;text-transform:uppercase;margin-bottom:3px;">Kode Lama Unit:</div>'
            . '  <div style="max-height:90px;overflow-y:auto;padding:2px 0;">' . $locLegacyBadges . '</div>'
            . '</td>'
            . '<td style="vertical-align:top;">'
            . '  <select id="planLocMode_' . e($lKey) . '" name="plan_loc[' . e($lKey) . '][mode]" onchange="toggleLocationPlanMode(\'' . e($lKey) . '\')" style="width:100%;padding:6px 8px;font-size:12px;font-weight:700;border:1px solid #d97706;border-radius:5px;background:#fffbeb;color:#78350f;">'
            . '    <option value="new"' . (!$isExistingL ? ' selected' : '') . '>➕ Buat Master Lokasi Baru dari Ajuan</option>'
            . '    <option value="existing"' . ($isExistingL ? ' selected' : '') . '>📍 Pilih dari Master Lokasi</option>'
            . '  </select>'
            . '  <div style="margin-top:6px;display:flex;gap:4px;flex-wrap:wrap;" id="planLocFormatBtns_' . e($lKey) . '">'
            . '    <button type="button" class="btn" onclick="setProposedLocationText(\'' . e($lKey) . '\', ' . e(json_encode($lCfg['full_name'])) . ')" style="padding:2px 7px;font-size:10.5px;background:#f8fafc;">Format Lengkap (Ruko+Lt+Ruang)</button>'
            . '    <button type="button" class="btn" onclick="setProposedLocationText(\'' . e($lKey) . '\', ' . e(json_encode($lCfg['ruko_only_name'])) . ')" style="padding:2px 7px;font-size:10.5px;background:#f8fafc;">Hanya Nama Ruko</button>'
            . '  </div>'
            . '</td>'
            . '<td style="vertical-align:top;">'
            . '  <div id="planLocNewWrap_' . e($lKey) . '" style="' . ($isExistingL ? 'display:none;' : '') . '">'
            . '    <div style="display:grid;grid-template-columns:150px 1fr;gap:8px;">'
            . '      <div>'
            . '        <span style="font-size:10.5px;font-weight:700;color:#78350f;display:block;margin-bottom:2px;">Kode Lokasi Baru:</span>'
            . '        <input type="text" id="planLocCode_' . e($lKey) . '" name="plan_loc[' . e($lKey) . '][code]" value="' . e($lCfg['code']) . '" placeholder="Kode Lokasi" style="width:100%;padding:5px 8px;font-size:12px;font-family:monospace;font-weight:700;border:1px solid #cbd5e1;border-radius:5px;box-sizing:border-box;">'
            . '      </div>'
            . '      <div>'
            . '        <span style="font-size:10.5px;font-weight:700;color:#78350f;display:block;margin-bottom:2px;">Nama Ajuan Master Lokasi Baru (Ruko + Lantai + Ruangan):</span>'
            . '        <input type="text" id="planLocName_' . e($lKey) . '" name="plan_loc[' . e($lKey) . '][name]" value="' . e($lCfg['name']) . '" placeholder="Nama Master Lokasi Baru" style="width:100%;padding:5px 8px;font-size:12.5px;font-weight:700;border:1px solid #f59e0b;border-radius:5px;box-sizing:border-box;">'
            . '      </div>'
            . '    </div>'
            . '  </div>'
            . '  <div id="planLocExistingWrap_' . e($lKey) . '" style="' . ($isExistingL ? '' : 'display:none;') . '">'
            . '    <span style="font-size:10.5px;font-weight:700;color:#1e3a8a;display:block;margin-bottom:2px;">Pilih Lokasi dari Master Lokasi (`asset_locations`):</span>'
            . '    <select id="planLocSelect_' . e($lKey) . '" name="plan_loc[' . e($lKey) . '][location_id]" class="js-location-select" onchange="onPlanLocationSelectedChange(\'' . e($lKey) . '\', this.value)" style="width:100%;padding:6px 8px;font-size:12.5px;font-weight:600;border:1px solid #3b82f6;border-radius:5px;background:#fff;">'
            .        $locOptionsHtml
            . '    </select>'
            . '  </div>'
            . '</td>'
            . '<td style="vertical-align:top;text-align:center;">'
            . '  <button type="button" id="btnSaveLocNow_' . e($lKey) . '" class="btn" onclick="saveLocationProposalNow(\'' . e($lKey) . '\')" style="width:100%;background:#16a34a;color:#fff;border-color:#15803d;font-size:11.5px;padding:6px 10px;font-weight:700;margin-bottom:5px;' . ($isExistingL ? 'display:none;' : '') . '">➕ Masukkan ke Master Lokasi Sekarang</button>'
            . '  <a class="btn" href="' . route_url('asset_locations') . '" target="_blank" style="width:100%;box-sizing:border-box;display:inline-block;font-size:11px;padding:4px 8px;background:#fff;color:#475569;border-color:#cbd5e1;">🗺️ Buka Master Lokasi ↗</a>'
            . '</td>'
            . '</tr>';
    }

    echo '</tbody></table></div></div>';

    // =========================================================================
    // PANEL 3: TABEL KONFIRMASI & EDITING BARIS UNIT ASET
    // =========================================================================
    echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:12px;flex-wrap:wrap;">'
        . '  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">'
        . '    <strong style="font-size:14px;color:#0f172a;">📋 3. Daftar Baris Unit Aset (Pilih & Edit Sebelum Import):</strong>'
        . '    <button type="button" class="btn" onclick="toggleSelectAll(true)" style="padding:6px 12px;font-size:12px;background:#fff;">✓ Pilih Semua</button>'
        . '    <button type="button" class="btn" onclick="toggleSelectAll(false)" style="padding:6px 12px;font-size:12px;background:#fff;">✕ Batal Pilih Semua</button>'
        . '    <span id="selectedCountDisplay" style="font-size:13px;font-weight:700;color:#0284c7;margin-left:4px;">0 Item Terpilih</span>'
        . '  </div>'
        . '  <div style="flex:1;max-width:340px;">'
        . '    <input type="text" id="filterInput" placeholder="🔍 Filter kode lama / nama barang / merk / lokasi..." oninput="filterTableRows(this.value)" style="padding:7px 12px;width:100%;box-sizing:border-box;font-size:13px;">'
        . '  </div>'
        . '</div>';

    echo '<div style="overflow-x:auto;max-height:600px;border:1px solid #cbd5e1;border-radius:8px;">'
        . '<table id="previewTable" style="margin:0;font-size:12.5px;min-width:1280px;">'
        . '<thead><tr style="position:sticky;top:0;background:#f8fafc;z-index:10;box-shadow:0 1px 2px rgba(0,0,0,0.06);">'
        . '<th style="width:38px;text-align:center;"><input type="checkbox" id="masterCheckbox" onclick="toggleSelectAll(this.checked)"></th>'
        . '<th style="width:38px;text-align:center;">No</th>'
        . '<th style="width:165px;">Kode Lama (Legacy)<br><span class="muted" style="font-weight:400;font-size:10.5px;">(Default dari Kolom "No")</span></th>'
        . '<th style="width:125px;">Tgl Perolehan</th>'
        . '<th style="width:280px;">Nama Barang, Merk & Master Barang</th>'
        . '<th style="width:240px;">Spesifikasi & Serial Number</th>'
        . '<th style="width:270px;">Lokasi (Ajuan Ruko/Lt/Ruang atau Master Lokasi)</th>'
        . '<th style="width:95px;text-align:center;">Status</th>'
        . '</tr></thead><tbody>';

    $no = 0;
    foreach ($previewList as $idx => $item) {
        $no++;
        $isDup = !empty($item['is_duplicate']);
        $rowBg = $isDup ? 'background:#fff1f2;' : ($no % 2 === 0 ? 'background:#f8fafc;' : 'background:#fff;');
        $isChecked = ''; // Secara default tidak terselect semua agar dikonfirmasi terlebih dahulu

        $statusBadge = $isDup
            ? '<span class="badge danger" title="Kode Lama sudah terdaftar pada unit ' . e($item['dup_asset_code']) . '">Duplikat</span>'
            : '<span class="badge ok">✓ Siap Impor</span>';

        // 1. Input Edit Kode Lama (Legacy) (default dari kolom "NO" import)
        $legacyInput = '<input type="text" name="items[' . $idx . '][legacy_code]" value="' . e($item['legacy_code']) . '" placeholder="Kode Lama (Legacy)" style="padding:5px 7px;font-size:12.5px;font-family:monospace;font-weight:700;color:#92400e;background:#fef3c7;border:1px solid #f59e0b;border-radius:4px;width:100%;box-sizing:border-box;">'
            . '<div class="muted" style="font-size:10px;margin-top:2px;">Kolom No: <code>' . e($item['raw_no'] ?: '-') . '</code></div>';

        // 2. Input Edit Tanggal Perolehan
        $installedVal = !empty($item['installed_at']) ? date('Y-m-d', strtotime($item['installed_at'])) : '';
        $dateInput = '<input type="date" name="items[' . $idx . '][installed_at]" value="' . e($installedVal) . '" style="padding:4px 6px;font-size:12px;width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:4px;">';

        // 3. Input Edit Nama Barang, Merk (terhubung database merk), Model & Master Barang
        $rowBrandId = (int)($item['brand_id'] ?? 0);
        $rowBrandSelect = '<select name="items[' . $idx . '][brand_id]" class="js-brand-select" onchange="syncRowBrandText(this, ' . $idx . ')" style="padding:3px 6px;font-size:11.5px;width:48%;border:1px solid #cbd5e1;border-radius:4px;background:#fff;">'
            . '<option value="0">-- Pilih Merk --</option>';
        foreach ($categoryBrands as $b) {
            $selB = ((int)$b['id'] === $rowBrandId) ? ' selected' : '';
            $rowBrandSelect .= '<option value="' . (int)$b['id'] . '"' . $selB . '>' . e($b['brand_name']) . '</option>';
        }
        $rowBrandSelect .= '</select>';

        $rowMasterSelect = '<select name="items[' . $idx . '][row_master_choice]" class="js-row-master-select" data-master-key="' . e($item['master_key']) . '" style="margin-top:4px;padding:3px 6px;font-size:11px;width:100%;border:1px solid #93c5fd;border-radius:4px;background:#eff6ff;color:#1e3a8a;font-weight:600;">'
            . '<option value="plan">🤖 Ikuti Rancangan AI Master Barang (' . e(mb_strimwidth((string)$item['asset_name'], 0, 32, '...')) . ')</option>';
        foreach ($categoryMasters as $cm) {
            $rowMasterSelect .= '<option value="existing:' . (int)$cm['id'] . '">📦 Master: ' . e($cm['item_code'] . ' - ' . $cm['item_name']) . '</option>';
        }
        $rowMasterSelect .= '<option value="none">⏭️ Tanpa Master Barang</option></select>';

        $nameInput = '<input type="text" name="items[' . $idx . '][asset_name]" value="' . e($item['asset_name'] ?: '') . '" placeholder="Nama Unit Aset" style="padding:4px 6px;font-size:12px;font-weight:700;width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:4px;">'
            . '<div style="display:flex;gap:4%;margin-top:4px;">'
            .   $rowBrandSelect
            . ' <input type="text" id="rowBrandText_' . $idx . '" name="items[' . $idx . '][brand]" value="' . e($item['brand'] ?: '') . '" placeholder="Merk" style="padding:3px 6px;font-size:11.5px;width:48%;box-sizing:border-box;border:1px solid #e2e8f0;border-radius:4px;">'
            . '</div>'
            . '<input type="text" name="items[' . $idx . '][model]" value="' . e($item['model'] ?? '') . '" placeholder="Model / Tipe / Kapasitas" style="margin-top:4px;padding:3px 6px;font-size:11px;width:100%;box-sizing:border-box;border:1px solid #e2e8f0;border-radius:4px;">'
            . $rowMasterSelect;

        // 4. Spesifikasi & Serial Number
        $specText = !empty($item['specs_summary']) ? ('<div style="font-size:11.5px;color:#334155;margin-bottom:4px;">' . $item['specs_summary'] . '</div>') : '';
        $specText .= '<input type="text" name="items[' . $idx . '][serial_number]" value="' . e($item['serial_number'] ?? '') . '" placeholder="Serial Number (SN)" style="padding:4px 6px;font-size:11.5px;width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:4px;">';
        $specText .= '<div class="muted" style="font-size:10.5px;margin-top:3px;" title="' . e((string)$item['raw_description']) . '">Asli: ' . e(mb_strimwidth((string)$item['raw_description'], 0, 55, '...')) . '</div>';

        // 5. Alokasi Lokasi per Baris (Ikuti Ajuan Ruko/Lantai/Ruangan atau Pilih langsung dari Master Lokasi)
        $rowLocSelect = '<select name="items[' . $idx . '][row_location_choice]" class="js-row-location-select" data-loc-key="' . e($item['loc_key']) . '" style="padding:4px 6px;font-size:11.5px;width:100%;border:1px solid #f59e0b;border-radius:4px;background:#fffbeb;color:#78350f;font-weight:600;">'
            . '<option value="plan">📍 Ikuti Ajuan Lokasi: ' . e(mb_strimwidth((string)$item['loc_master_name'], 0, 38, '...')) . '</option>';
        foreach ($allLocations as $loc) {
            $rowLocSelect .= '<option value="existing:' . (int)$loc['id'] . '">🏢 Master Lokasi: ' . e($loc['location_name'] . ' (' . $loc['location_code'] . ')') . '</option>';
        }
        $rowLocSelect .= '</select>';

        $locInput = $rowLocSelect
            . '<input type="text" name="items[' . $idx . '][loc_label]" value="' . e($item['loc_label'] ?? '') . '" placeholder="Detail Lantai / Ruangan" style="margin-top:4px;padding:3px 6px;font-size:11px;width:100%;box-sizing:border-box;border:1px solid #e2e8f0;border-radius:4px;">'
            . '<div class="muted" style="font-size:10px;margin-top:2px;">Import: Ruko <strong>' . e($item['raw_ruko'] ?: '-') . '</strong> &bull; Lt <strong>' . e($item['raw_lantai'] ?: '-') . '</strong> &bull; Rg <strong>' . e($item['raw_ruangan'] ?: '-') . '</strong></div>';

        echo '<tr class="preview-row" style="' . $rowBg . '" data-search="' . e(strtolower($item['legacy_code'] . ' ' . $item['asset_name'] . ' ' . $item['brand'] . ' ' . $item['loc_master_name'] . ' ' . $item['loc_label'] . ' ' . $item['raw_description'])) . '">'
            . '<td style="text-align:center;vertical-align:top;padding-top:10px;"><input type="checkbox" name="selected_rows[]" value="' . $idx . '" ' . $isChecked . ' onchange="updateSelectedCount()"></td>'
            . '<td style="text-align:center;vertical-align:top;padding-top:10px;">' . $no . '</td>'
            . '<td style="vertical-align:top;">' . $legacyInput . '</td>'
            . '<td style="vertical-align:top;">' . $dateInput . '</td>'
            . '<td style="vertical-align:top;">' . $nameInput . '</td>'
            . '<td style="vertical-align:top;">' . $specText . '</td>'
            . '<td style="vertical-align:top;">' . $locInput . '</td>'
            . '<td style="text-align:center;vertical-align:top;padding-top:10px;">' . $statusBadge . '</td>'
            . '</tr>';
    }

    echo '</tbody></table></div>';

    echo '<div class="actions" style="margin-top:20px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">'
        . '  <button type="submit" class="btn primary" style="padding:12px 28px;font-size:15px;font-weight:700;">💾 Konfirmasi & Import Unit Terpilih ke \'' . e($targetTypeName) . '\'</button>'
        . '  <a class="btn" href="' . route_url('asset_locations') . '" target="_blank" style="background:#fff;color:#b45309;border-color:#f59e0b;font-weight:600;">🗺️ Buka Menu Master Lokasi ↗</a>'
        . '  <a class="btn" href="' . route_url('asset_master_items') . '" target="_blank" style="background:#fff;color:#0369a1;border-color:#7dd3fc;font-weight:600;">📦 Buka Menu Master Barang ↗</a>'
        . '  <a class="btn" href="' . route_url('asset_legacy_import', ['action' => 'reset']) . '">← Batal / Ganti Kategori & File</a>'
        . '</div>'
        . '</form>'
        . '</section>';

    echo '<script>
    function updateSelectedCount() {
        var cbs = document.querySelectorAll("#previewTable tbody input[type=\'checkbox\']");
        var cnt = 0;
        cbs.forEach(function(cb) {
            if (cb.checked) cnt++;
        });
        var disp = document.getElementById("selectedCountDisplay");
        if (disp) disp.textContent = cnt + " Item Terpilih";
    }

    function toggleSelectAll(status) {
        var cbs = document.querySelectorAll("#previewTable tbody tr:not([style*=\'display: none\']) input[type=\'checkbox\']");
        cbs.forEach(function(cb) {
            cb.checked = status;
        });
        var mCb = document.getElementById("masterCheckbox");
        if (mCb) mCb.checked = status;
        updateSelectedCount();
    }

    function filterTableRows(q) {
        q = (q || "").toLowerCase().trim();
        var rows = document.querySelectorAll("#previewTable tbody tr.preview-row");
        rows.forEach(function(r) {
            var searchTxt = r.getAttribute("data-search") || "";
            if (!q || searchTxt.includes(q)) {
                r.style.display = "";
            } else {
                r.style.display = "none";
            }
        });
    }

    function syncRowBrandText(selEl, rowIdx) {
        var txtIn = document.getElementById("rowBrandText_" + rowIdx);
        if (!selEl || !txtIn) return;
        var opt = selEl.options[selEl.selectedIndex];
        if (opt && parseInt(selEl.value, 10) > 0) {
            txtIn.value = opt.text.replace(/\s*\([^)]*\)\s*$/, "").trim();
        }
    }

    


    function onPlanMasterSelectedChange(mKey, masterId) {
        masterId = parseInt(masterId, 10) || 0;
        var tagEl = document.getElementById("masterStatusTag_" + mKey);
        var nameIn = document.getElementById("planMasterName_" + mKey);
        var modelIn = document.getElementById("planMasterModel_" + mKey);
        var brandSel = document.getElementById("planMasterBrand_" + mKey);

        if (masterId <= 0) {
            if (tagEl) {
                tagEl.className = "badge";
                tagEl.style.background = "#dbeafe";
                tagEl.style.color = "#1e40af";
                tagEl.textContent = "📦 Belum Memilih Master Barang";
            }
            return;
        }

        var found = (window._allCategoryMasters || []).find(function(m) {
            return parseInt(m.id, 10) === masterId;
        });

        if (found) {
            if (tagEl) {
                tagEl.className = "badge ok";
                tagEl.textContent = "✓ Terhubung ke Master: " + found.item_code;
            }
            if (nameIn && found.item_name) {
                nameIn.value = found.item_name;
            }
            if (modelIn && found.model_name) {
                modelIn.value = found.model_name;
            }
            if (brandSel && found.brand_id) {
                brandSel.value = String(found.brand_id);
            }

            // Sync ke dropdown baris unit aset yang mengikuti plan master ini
            var rowSels = document.querySelectorAll("select.js-row-master-select[data-master-key=\"" + mKey + "\"]");
            rowSels.forEach(function(rSel) {
                var firstOpt = rSel.options[0];
                if (firstOpt) {
                    firstOpt.textContent = "🤖 Ikuti Master Terpilih: " + found.item_code + " - " + found.item_name;
                }
            });
        }
    }

    function onPlanLocationSelectedChange(lKey, locationId) {
        locationId = parseInt(locationId, 10) || 0;
        var tagEl = document.getElementById("locStatusTag_" + lKey);
        if (locationId <= 0) return;

        var found = (window._allCategoryLocations || []).find(function(l) {
            return parseInt(l.id, 10) === locationId;
        });

        if (found) {
            if (tagEl) {
                tagEl.className = "badge ok";
                tagEl.textContent = "✓ Master Lokasi: " + found.location_code;
            }
            var rowSels = document.querySelectorAll("select.js-row-location-select[data-loc-key=\"" + lKey + "\"]");
            rowSels.forEach(function(rSel) {
                var firstOpt = rSel.options[0];
                if (firstOpt) {
                    firstOpt.textContent = "📍 Ikuti Master: " + found.location_name + " (" + found.location_code + ")";
                }
            });
        }
    }

    function toggleMasterPlanMode(mKey) {
        var modeEl = document.getElementById("planMasterMode_" + mKey);
        var exWrap = document.getElementById("planMasterExistingWrap_" + mKey);
        var btnSave = document.getElementById("btnSaveMasterNow_" + mKey);
        var selEl = document.getElementById("planMasterSelect_" + mKey);
        var tagEl = document.getElementById("masterStatusTag_" + mKey);
        if (!modeEl) return;
        var mode = modeEl.value;
        if (exWrap) exWrap.style.display = (mode === "existing") ? "" : "none";
        if (btnSave) btnSave.style.display = (mode === "new") ? "" : "none";

        if (mode === "existing") {
            if (selEl && parseInt(selEl.value, 10) > 0) {
                onPlanMasterSelectedChange(mKey, selEl.value);
            } else if (tagEl) {
                tagEl.className = "badge";
                tagEl.style.background = "#dbeafe";
                tagEl.style.color = "#1e40af";
                tagEl.textContent = "📦 Silakan Pilih Master Barang Eksisting";
            }
        } else if (mode === "new") {
            var orig = (window._planMastersData && window._planMastersData[mKey]) ? window._planMastersData[mKey] : null;
            if (tagEl) {
                tagEl.className = "badge";
                tagEl.style.background = "#dbeafe";
                tagEl.style.color = "#1e40af";
                tagEl.textContent = "✨ Rancangan Baru (SKU: " + (orig ? orig.proposed_code : "-") + ")";
            }
        }
    }

    function toggleLocationPlanMode(lKey) {
        var modeEl = document.getElementById("planLocMode_" + lKey);
        var newWrap = document.getElementById("planLocNewWrap_" + lKey);
        var exWrap = document.getElementById("planLocExistingWrap_" + lKey);
        var btnSave = document.getElementById("btnSaveLocNow_" + lKey);
        var fmtBtns = document.getElementById("planLocFormatBtns_" + lKey);
        if (!modeEl) return;
        var isEx = (modeEl.value === "existing");
        if (newWrap) newWrap.style.display = isEx ? "none" : "";
        if (exWrap) exWrap.style.display = isEx ? "" : "none";
        if (btnSave) btnSave.style.display = isEx ? "none" : "";
        if (fmtBtns) fmtBtns.style.display = isEx ? "none" : "flex";
    }

    function setProposedLocationText(lKey, textVal) {
        var nameIn = document.getElementById("planLocName_" + lKey);
        if (nameIn) nameIn.value = textVal || "";
    }

    async function saveLocationProposalNow(lKey) {
        var nameIn = document.getElementById("planLocName_" + lKey);
        var codeIn = document.getElementById("planLocCode_" + lKey);
        var csrfIn = document.getElementById("importCsrfToken");
        var btn = document.getElementById("btnSaveLocNow_" + lKey);
        if (!nameIn || !nameIn.value.trim()) {
            alert("Nama ajuan lokasi baru tidak boleh kosong.");
            return;
        }
        var oldTxt = btn ? btn.textContent : "";
        if (btn) {
            btn.disabled = true;
            btn.textContent = "⏳ Menyimpan ke Master Lokasi...";
        }
        try {
            var fd = new FormData();
            fd.append("csrf", csrfIn ? csrfIn.value : "");
            fd.append("loc_key", lKey);
            fd.append("location_name", nameIn.value.trim());
            fd.append("location_code", codeIn ? codeIn.value.trim() : "");
            var resp = await fetch("index.php?route=asset_legacy_import&action=ajax_save_location", {
                method: "POST",
                body: fd
            });
            var data = await resp.json();
            if (!data || !data.ok) {
                alert("Gagal menyimpan lokasi: " + ((data && data.error) ? data.error : "Unknown error"));
                if (btn) { btn.disabled = false; btn.textContent = oldTxt; }
                return;
            }
            if (Array.isArray(data.locations)) {
                populateAllLocationDropdowns(data.locations);
            }
            var modeEl = document.getElementById("planLocMode_" + lKey);
            var selEl = document.getElementById("planLocSelect_" + lKey);
            var tagEl = document.getElementById("locStatusTag_" + lKey);
            if (modeEl) {
                modeEl.value = "existing";
                toggleLocationPlanMode(lKey);
            }
            if (selEl && data.location && data.location.id) {
                selEl.value = String(data.location.id);
            }
            if (tagEl && data.location) {
                tagEl.className = "badge ok";
                tagEl.textContent = "✓ Tersimpan di Master Lokasi (" + data.location.location_code + ")";
            }
        } catch (err) {
            alert("Terjadi kesalahan jaringan saat menyimpan lokasi.");
            if (btn) { btn.disabled = false; btn.textContent = oldTxt; }
        }
    }

    async function saveMasterItemProposalNow(mKey) {
        var nameIn = document.getElementById("planMasterName_" + mKey);
        var modelIn = document.getElementById("planMasterModel_" + mKey);
        var specsIn = document.getElementById("planMasterSpecs_" + mKey);
        var brandSel = document.getElementById("planMasterBrand_" + mKey);
        var brandCustomIn = document.getElementById("planMasterBrandCustom_" + mKey);
        var csrfIn = document.getElementById("importCsrfToken");
        var gIn = document.getElementById("importGroupIdHidden");
        var tIn = document.getElementById("importTypeIdHidden");
        var btn = document.getElementById("btnSaveMasterNow_" + mKey);

        if (!nameIn || !nameIn.value.trim()) {
            alert("Nama Master Barang wajib diisi.");
            return;
        }
        var oldTxt = btn ? btn.textContent : "";
        if (btn) {
            btn.disabled = true;
            btn.textContent = "⏳ Menyimpan ke Master Barang...";
        }
        try {
            var fd = new FormData();
            fd.append("csrf", csrfIn ? csrfIn.value : "");
            fd.append("master_key", mKey);
            fd.append("asset_group_id", gIn ? gIn.value : "0");
            fd.append("asset_type_id", tIn ? tIn.value : "0");
            fd.append("brand_id", brandSel ? brandSel.value : "0");
            fd.append("brand_name", brandCustomIn ? brandCustomIn.value.trim() : "");
            fd.append("item_name", nameIn.value.trim());
            fd.append("model_name", modelIn ? modelIn.value.trim() : "");
            fd.append("specs_text", specsIn ? specsIn.value.trim() : "");

            var resp = await fetch("index.php?route=asset_legacy_import&action=ajax_save_master_item", {
                method: "POST",
                body: fd
            });
            var data = await resp.json();
            if (!data || !data.ok) {
                alert("Gagal menyimpan Master Barang: " + ((data && data.error) ? data.error : "Unknown error"));
                if (btn) { btn.disabled = false; btn.textContent = oldTxt; }
                return;
            }
            if (Array.isArray(data.masters)) {
                populateAllMasterDropdowns(data.masters);
            }
            var modeEl = document.getElementById("planMasterMode_" + mKey);
            var selEl = document.getElementById("planMasterSelect_" + mKey);
            var tagEl = document.getElementById("masterStatusTag_" + mKey);
            if (modeEl) {
                modeEl.value = "existing";
                toggleMasterPlanMode(mKey);
            }
            if (selEl && data.master && data.master.id) {
                selEl.value = String(data.master.id);
            }
            if (tagEl && data.master) {
                tagEl.className = "badge ok";
                tagEl.textContent = "✓ Tersimpan di Master Barang (" + data.master.item_code + ")";
            }
        } catch (err) {
            alert("Terjadi kesalahan jaringan saat menyimpan Master Barang.");
            if (btn) { btn.disabled = false; btn.textContent = oldTxt; }
        }
    }

    function populateAllLocationDropdowns(locations) {
        window._allCategoryLocations = locations || [];
        var planSels = document.querySelectorAll("select.js-location-select");
        planSels.forEach(function(sel) {
            var cur = sel.value;
            var html = "<option value=\"0\">-- Pilih dari Master Lokasi --</option>";
            locations.forEach(function(l) {
                var s = (String(l.id) === String(cur)) ? " selected" : "";
                html += "<option value=\"" + l.id + "\"" + s + ">" + escapeHtmlJs(l.location_name + " (" + l.location_code + ")") + "</option>";
            });
            sel.innerHTML = html;
        });

        var rowSels = document.querySelectorAll("select.js-row-location-select");
        rowSels.forEach(function(sel) {
            var cur = sel.value;
            var firstOpt = sel.options[0] ? sel.options[0].outerHTML : "<option value=\"plan\">📍 Ikuti Ajuan Lokasi</option>";
            var html = firstOpt;
            locations.forEach(function(l) {
                var val = "existing:" + l.id;
                var s = (val === cur) ? " selected" : "";
                html += "<option value=\"" + val + "\"" + s + ">🏢 Master Lokasi: " + escapeHtmlJs(l.location_name + " (" + l.location_code + ")") + "</option>";
            });
            sel.innerHTML = html;
        });
    }

    function populateAllMasterDropdowns(masters) {
        window._allCategoryMasters = masters || [];
        var planSels = document.querySelectorAll("select.js-master-select");
        planSels.forEach(function(sel) {
            var cur = sel.value;
            var html = "<option value=\"0\">-- Pilih dari Master Barang --</option>";
            masters.forEach(function(m) {
                var s = (String(m.id) === String(cur)) ? " selected" : "";
                var lbl = m.item_code + " - " + m.item_name + (m.brand_name ? " [" + m.brand_name + "]" : "");
                html += "<option value=\"" + m.id + "\"" + s + ">" + escapeHtmlJs(lbl) + "</option>";
            });
            sel.innerHTML = html;
        });

        var rowSels = document.querySelectorAll("select.js-row-master-select");
        rowSels.forEach(function(sel) {
            var cur = sel.value;
            var firstOpt = sel.options[0] ? sel.options[0].outerHTML : "<option value=\"plan\">🤖 Ikuti Rancangan AI Master Barang</option>";
            var html = firstOpt;
            masters.forEach(function(m) {
                var val = "existing:" + m.id;
                var s = (val === cur) ? " selected" : "";
                html += "<option value=\"" + val + "\"" + s + ">📦 Master: " + escapeHtmlJs(m.item_code + " - " + m.item_name) + "</option>";
            });
            html += "<option value=\"none\"" + (cur === "none" ? " selected" : "") + ">⏭️ Tanpa Master Barang</option>";
            sel.innerHTML = html;
        });
    }

    async function refreshMasterAndLocationDropdowns() {
        var gIn = document.getElementById("importGroupIdHidden");
        var tIn = document.getElementById("importTypeIdHidden");
        var gid = gIn ? gIn.value : "0";
        var tid = tIn ? tIn.value : "0";
        try {
            var resp = await fetch("index.php?route=asset_legacy_import&action=locations_json&group_id=" + encodeURIComponent(gid) + "&type_id=" + encodeURIComponent(tid));
            var data = await resp.json();
            if (data && data.ok) {
                if (Array.isArray(data.locations)) populateAllLocationDropdowns(data.locations);
                if (Array.isArray(data.masters)) populateAllMasterDropdowns(data.masters);
            }
        } catch (e) {}
    }

    function escapeHtmlJs(str) {
        return String(str || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/\x27/g, "&#39;");
    }
    </script>';
}
