<?php

declare(strict_types=1);

/**
 * Modul: Import Data Aset Lama (Legacy) dengan AI-Assisted Parsing
 * 
 * Alur Proses Terstruktur:
 * 1. Step 1 (Pemilihan Awal):
 *    - User memilih Komoditas (Grup Aset) & Kategori (Tipe Aset) terlebih dahulu.
 *    - User mengunggah file Excel (.xlsx) / CSV (.csv) data inventaris lama.
 * 2. Step 2 (Preview & Seleksi Interaktif):
 *    - Kolom 'NO' dibaca sebagai Kode Legacy Lama (alias_code).
 *    - Tanggal perolehan/beli otomatis diekstrak dari teks setelah tanda '/' pada kolom 'NO'.
 *    - Kolom 'NAMA INVENTARIS + SPESIFIKASI' dibedah oleh AI / Smart Heuristic sesuai Kategori yang dipilih.
 *    - Kolom 'RUKO', 'LANTAI', 'RUANGAN' dialokasikan ke Master Lokasi (dibuatkan baru jika belum ada)
 *      serta mengisi detail ruangan pada location_label.
 *    - Menampilkan list interaktif dengan checkbox checklist agar user bisa memilih baris mana saja
 *      yang akan diimpor ke Komoditas & Kategori tersebut.
 * 3. Step 3 (Eksekusi Simpan):
 *    - Menyimpan seluruh item terpilih ke database `asset_items` lengkap dengan `alias_code`,
 *      relasi Master Lokasi, `asset_identifiers`, dan `asset_specifications`.
 */

/**
 * Handler Rute: Import Data Aset Lama (Legacy) dengan AI / Smart Heuristic Parsing
 * 
 * [PROSES & REGULASI HAK AKSES]:
 * 1. Regulasi Hak Akses:
 *    - Terintegrasi penuh di bawah izin Tambah (Create) pada Unit Aset (`asset_items`).
 *    - Hanya dapat diakses dan dieksekusi oleh pengguna yang memiliki hak Tambah pada Unit Aset atau Administrator.
 * 2. Step 1: Pemilihan Komoditas (Grup Aset) & Kategori (Tipe Aset) tujuan via dropdown dependen + upload file.
 * 3. Step 2: Preview interaktif checklist dengan ekstraksi tanggal beli setelah tanda '/', bedah spesifikasi AI,
 *    dan alokasi Master Lokasi (ruko/lantai/ruangan).
 * 4. Step 3: Eksekusi transaksi simpan ke asset_items, asset_identifiers, dan asset_specifications.
 */
function handle_route_asset_legacy_import(PDO $pdo): void
{
    $user = require_login();
    if (!is_full_admin($user) && !has_regulation('asset_items', 'create', $user)) {
        http_response_code(403);
        exit('Akses ditolak ke menu Import Aset Lama.');
    }

    $action = (string)($_GET['action'] ?? ($_POST['action'] ?? ''));

    // 1. Download Template CSV Contoh
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

    // Reset Session Preview
    if ($action === 'reset') {
        unset($_SESSION['asset_legacy_import_preview'], $_SESSION['asset_legacy_import_opts']);
        redirect_to('asset_legacy_import');
    }

    // 2. Eksekusi Simpan Data Terpilih ke Database (Step 3)
    if ($action === 'execute_import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if (!is_full_admin($user) && !has_regulation('asset_items', 'create', $user)) {
            http_response_code(403);
            exit('Akses ditolak. Role Anda tidak memiliki izin untuk menyimpan data hasil import aset lama.');
        }
        $previewData = $_SESSION['asset_legacy_import_preview'] ?? [];
        if (empty($previewData)) {
            flash('Tidak ada data preview yang siap diimpor. Silakan upload file kembali.', 'err');
            redirect_to('asset_legacy_import');
        }

        $selectedIndexes = $_POST['selected_rows'] ?? [];
        if (empty($selectedIndexes)) {
            flash('Tidak ada item yang dipilih untuk diimpor. Berikan tanda centang pada item yang ingin diimpor.', 'err');
            redirect_to('asset_legacy_import');
        }

        $companyId = (int)($_POST['company_id'] ?? 0) ?: null;
        $groupId = (int)($_POST['asset_group_id'] ?? 0);
        $typeId = (int)($_POST['asset_type_id'] ?? 0);

        if ($groupId <= 0 || $typeId <= 0) {
            flash('Komoditas dan Kategori tujuan wajib dipilih.', 'err');
            redirect_to('asset_legacy_import');
        }

        // Ambil info Group & Type
        $gStmt = $pdo->prepare("SELECT group_code, group_name FROM asset_groups WHERE id = ?");
        $gStmt->execute([$groupId]);
        $gRow = $gStmt->fetch(PDO::FETCH_ASSOC);
        $gCode = strtoupper(trim((string)($gRow['group_code'] ?? 'IT')));

        $tStmt = $pdo->prepare("SELECT type_code, type_name FROM asset_types WHERE id = ?");
        $tStmt->execute([$typeId]);
        $tRow = $tStmt->fetch(PDO::FETCH_ASSOC);
        $tCode = strtoupper(trim((string)($tRow['type_code'] ?? 'CMP')));
        $typeName = (string)($tRow['type_name'] ?? 'Computer');

        // Status aktif ID
        $activeStatusId = (int)($pdo->query("SELECT id FROM asset_statuses WHERE status_code = 'ACTIVE' LIMIT 1")->fetchColumn() ?: 1);

        $importedCount = 0;
        $skippedCount = 0;

        $pdo->beginTransaction();
        try {
            foreach ($selectedIndexes as $idxStr) {
                $idx = (int)$idxStr;
                if (!isset($previewData[$idx])) {
                    continue;
                }
                $row = $previewData[$idx];
                if (isset($_POST['items'][$idx]) && is_array($_POST['items'][$idx])) {
                    $pItem = $_POST['items'][$idx];
                    if (!empty($pItem['legacy_code'])) $row['legacy_code'] = trim((string)$pItem['legacy_code']);
                    if (isset($pItem['asset_name']) && trim((string)$pItem['asset_name']) !== '') $row['asset_name'] = trim((string)$pItem['asset_name']);
                    if (isset($pItem['brand'])) $row['brand'] = trim((string)$pItem['brand']);
                    if (isset($pItem['model'])) $row['model'] = trim((string)$pItem['model']);
                    if (isset($pItem['installed_at'])) $row['installed_at'] = trim((string)$pItem['installed_at']) ?: null;
                    if (isset($pItem['serial_number'])) $row['serial_number'] = trim((string)$pItem['serial_number']);
                    if (isset($pItem['loc_label'])) $row['loc_label'] = trim((string)$pItem['loc_label']);
                }
                $legacyCode = trim((string)($row['legacy_code'] ?? ''));

                if ($legacyCode === '') {
                    $skippedCount++;
                    continue;
                }

                // Cek duplikasi kode legacy di database
                $checkStmt = $pdo->prepare("SELECT id FROM asset_items WHERE alias_code = ? LIMIT 1");
                $checkStmt->execute([$legacyCode]);
                if ($checkStmt->fetchColumn()) {
                    $skippedCount++;
                    continue;
                }

                // Resolusi / Buat Baru Master Lokasi (Ruko / Gedung)
                $locResolution = resolve_or_create_asset_location($pdo, (string)($row['ruko'] ?? ''), (string)($row['lantai'] ?? ''), (string)($row['ruangan'] ?? ''));
                $locId = $locResolution['location_id'];
                $locLabel = $locResolution['location_label'];

                // Generate Kode Aset Baru
                $newAssetCode = generate_next_asset_code($pdo, $gCode, $tCode);

                // Resolusi Brand
                $brandId = null;
                $brandName = trim((string)($row['brand'] ?? ''));
                if ($brandName !== '') {
                    $bStmt = $pdo->prepare("SELECT id, brand_name FROM asset_brands WHERE UPPER(brand_name) = UPPER(?) LIMIT 1");
                    $bStmt->execute([$brandName]);
                    $bRow = $bStmt->fetch(PDO::FETCH_ASSOC);
                    if ($bRow) {
                        $brandId = (int)$bRow['id'];
                        $brandName = $bRow['brand_name'];
                    } else {
                        $insB = $pdo->prepare("INSERT INTO asset_brands (brand_name, is_active) VALUES (?, 1)");
                        $insB->execute([$brandName]);
                        $brandId = (int)$pdo->lastInsertId();
                    }
                }

                $assetName = trim((string)($row['asset_name'] ?? ''));
                if ($assetName === '') {
                    $assetName = ($brandName ? $brandName . ' ' : '') . trim((string)($row['model'] ?? '')) ?: $newAssetCode;
                }
                $model = trim((string)($row['model'] ?? ''));
                $installedAt = !empty($row['installed_at']) ? $row['installed_at'] : null;
                $serialNumber = trim((string)($row['serial_number'] ?? ''));
                $custodianName = trim((string)($row['custodian_name'] ?? ''));
                $rawNotes = 'Diimpor dari data lama (Legacy). Deskripsi asli: ' . trim((string)($row['raw_description'] ?? ''));

                // Insert ke tabel asset_items
                $insSql = "INSERT INTO asset_items (
                    master_item_id, brand_id, asset_code, alias_code, asset_group_id, asset_type_id,
                    asset_status_id, asset_mode, asset_name, brand, model, serial_number,
                    custodian_name, notes, company_id, location_id, location_label,
                    installed_at, asset_type, asset_category, status
                ) VALUES (
                    NULL, ?, ?, ?, ?, ?,
                    ?, 'standalone', ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, 'active'
                )";
                $insStmt = $pdo->prepare($insSql);
                $insStmt->execute([
                    $brandId,
                    $newAssetCode,
                    $legacyCode,
                    $groupId,
                    $typeId,
                    $activeStatusId,
                    $assetName,
                    $brandName,
                    $model,
                    $serialNumber ?: null,
                    $custodianName ?: null,
                    $rawNotes,
                    $companyId,
                    $locId,
                    $locLabel ?: null,
                    $installedAt,
                    $typeName,
                    $typeName
                ]);
                $newId = (int)$pdo->lastInsertId();

                // Simpan Identifiers (Serial Number, MAC, Tag No, dll.)
                if (!empty($serialNumber)) {
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

                // Simpan Spesifikasi (Disesuaikan dengan Kategori)
                $specsToSave = $row['specs_map'] ?? [];
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
            unset($_SESSION['asset_legacy_import_preview'], $_SESSION['asset_legacy_import_opts']);
            flash("Berhasil mengimpor {$importedCount} unit aset ke kategori '{$typeName}'!" . ($skippedCount > 0 ? " ({$skippedCount} baris dilewati karena duplikat atau kode kosong)." : ""));
            redirect_to('asset_items');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash("Gagal memproses import data aset: " . $e->getMessage(), 'err');
            redirect_to('asset_legacy_import');
        }
    }

    // 3. Upload File & Proses Preview dengan AI / Smart Heuristic (Step 2)
    if ($action === 'upload_preview' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if (!is_full_admin($user) && !has_regulation('asset_items', 'create', $user)) {
            http_response_code(403);
            exit('Akses ditolak. Role Anda tidak memiliki izin untuk mengunggah file import aset lama.');
        }
        @set_time_limit(180);
        @ini_set('memory_limit', '512M');

        $groupId = (int)($_POST['asset_group_id'] ?? 0);
        $typeId = (int)($_POST['asset_type_id'] ?? 0);
        $companyId = (int)($_POST['company_id'] ?? 0);

        if ($groupId <= 0 || $typeId <= 0) {
            flash('Silakan pilih Komoditas dan Kategori aset tujuan terlebih dahulu sebelum mengunggah file.', 'err');
            redirect_to('asset_legacy_import');
        }

        if (empty($_FILES['import_file']['name']) || empty($_FILES['import_file']['tmp_name'])) {
            flash('Silakan pilih file Excel (.xlsx) atau CSV (.csv) untuk diimpor.', 'err');
            redirect_to('asset_legacy_import');
        }

        $fileName = (string)$_FILES['import_file']['name'];
        $tmpPath = (string)$_FILES['import_file']['tmp_name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            flash('Format file tidak didukung. Mohon gunakan file .xlsx atau .csv.', 'err');
            redirect_to('asset_legacy_import');
        }

        $useAi = !empty($_POST['use_ai']);
        $rows = read_uploaded_spreadsheet_rows($tmpPath, $ext);

        if (empty($rows)) {
            flash('File tidak berisi data atau format tabel kosong.', 'err');
            redirect_to('asset_legacy_import');
        }

        // Ambil info Kategori & Komoditas yang dipilih
        $gInfo = $pdo->query("SELECT group_code, group_name FROM asset_groups WHERE id = {$groupId}")->fetch(PDO::FETCH_ASSOC);
        $tInfo = $pdo->query("SELECT type_code, type_name FROM asset_types WHERE id = {$typeId}")->fetch(PDO::FETCH_ASSOC);
        $gCode = strtoupper(trim((string)($gInfo['group_code'] ?? 'IT')));
        $tCode = strtoupper(trim((string)($tInfo['type_code'] ?? 'CMP')));
        $typeName = (string)($tInfo['type_name'] ?? 'Computer');

        // Deteksi Header Baris Pertama
        $headerRow = array_map(function($h) {
            return strtolower(trim((string)$h));
        }, $rows[0]);

        $colNo = -1;
        $colDesc = -1;
        $colRuangan = -1;
        $colLantai = -1;
        $colRuko = -1;

        foreach ($headerRow as $idx => $headerText) {
            // Kolom NO / Kode Legacy
            if ($colNo === -1 && in_array($headerText, ['no', 'no.', 'no aset', 'kode', 'kode lama', 'barcode', 'code'])) {
                $colNo = $idx;
            } elseif ($colNo === -1 && str_starts_with($headerText, 'no')) {
                $colNo = $idx;
            }
            // Kolom Deskripsi / Nama Inventaris + Spesifikasi
            if ($colDesc === -1 && (str_contains($headerText, 'inventaris') || str_contains($headerText, 'spesifikasi') || str_contains($headerText, 'deskripsi') || str_contains($headerText, 'nama') || str_contains($headerText, 'barang'))) {
                $colDesc = $idx;
            }
            // Kolom Ruangan
            if ($colRuangan === -1 && (str_contains($headerText, 'ruang') || str_contains($headerText, 'room'))) {
                $colRuangan = $idx;
            }
            // Kolom Lantai
            if ($colLantai === -1 && (str_contains($headerText, 'lant') || str_contains($headerText, 'floor'))) {
                $colLantai = $idx;
            }
            // Kolom Ruko / Gedung
            if ($colRuko === -1 && (str_contains($headerText, 'ruko') || str_contains($headerText, 'gedung') || str_contains($headerText, 'building') || str_contains($headerText, 'lokasi'))) {
                $colRuko = $idx;
            }
        }

        // Fallback jika header tidak terdeteksi
        if ($colNo === -1) $colNo = 0;
        if ($colDesc === -1) $colDesc = count($headerRow) > 1 ? 1 : 0;
        if ($colRuangan === -1 && count($headerRow) > 2) $colRuangan = 2;
        if ($colLantai === -1 && count($headerRow) > 3) $colLantai = 3;
        if ($colRuko === -1 && count($headerRow) > 4) $colRuko = 4;

        $dataRows = array_slice($rows, 1);
        $previewList = [];
        $checkAliasStmt = $pdo->prepare("SELECT id, asset_code FROM asset_items WHERE alias_code = ? LIMIT 1");

        foreach ($dataRows as $r) {
            $rawNo = trim((string)($r[$colNo] ?? ''));
            $rawDesc = trim((string)($r[$colDesc] ?? ''));
            $rawRuangan = $colRuangan >= 0 ? trim((string)($r[$colRuangan] ?? '')) : '';
            $rawLantai = $colLantai >= 0 ? trim((string)($r[$colLantai] ?? '')) : '';
            $rawRuko = $colRuko >= 0 ? trim((string)($r[$colRuko] ?? '')) : '';

            if ($rawNo === '' && $rawDesc === '') {
                continue;
            }

            // 1. Ekstrak Kode Legacy & Tanggal Beli dari Kolom 'NO'
            [$legacyCode, $installedAt] = extract_legacy_code_and_date($rawNo);

            // 2. AI / Smart Parsing Deskripsi sesuai Kategori yang dipilih
            $parsed = parse_asset_description_by_category($pdo, $rawDesc, $legacyCode, $gCode, $tCode, $useAi);

            // 3. Resolusi Alokasi Lokasi (Ruko + Lantai + Ruangan)
            $locSim = resolve_or_create_asset_location($pdo, $rawRuko, $rawLantai, $rawRuangan, null, true); // simulation

            // 4. Cek apakah kode lama sudah ada di database (Duplikat)
            $isDuplicate = false;
            $dupAssetCode = '';
            if ($legacyCode !== '') {
                $checkAliasStmt->execute([$legacyCode]);
                $dup = $checkAliasStmt->fetch(PDO::FETCH_ASSOC);
                if ($dup) {
                    $isDuplicate = true;
                    $dupAssetCode = $dup['asset_code'];
                }
            }

            $previewList[] = [
                'legacy_code'      => $legacyCode,
                'installed_at'     => $installedAt,
                'raw_description'  => $rawDesc,
                'ruko'             => $rawRuko,
                'lantai'           => $rawLantai,
                'ruangan'          => $rawRuangan,
                'loc_master_name'  => $locSim['location_name'],
                'loc_label'        => $locSim['location_label'],
                'loc_is_new'       => $locSim['is_new'],
                'brand'            => $parsed['brand'] ?? '',
                'model'            => $parsed['model'] ?? '',
                'asset_name'       => $parsed['asset_name'] ?? '',
                'serial_number'    => $parsed['serial_number'] ?? '',
                'mac_address'      => $parsed['mac_address'] ?? '',
                'specs_map'        => $parsed['specs_map'] ?? [],
                'specs_summary'    => $parsed['specs_summary'] ?? '',
                'custodian_name'   => $parsed['custodian_name'] ?? '',
                'ai_method'        => $parsed['ai_method'] ?? 'Smart Engine',
                'is_duplicate'     => $isDuplicate,
                'dup_asset_code'   => $dupAssetCode,
            ];
        }

        $_SESSION['asset_legacy_import_preview'] = $previewList;
        $_SESSION['asset_legacy_import_opts'] = [
            'company_id'     => $companyId,
            'asset_group_id' => $groupId,
            'asset_type_id'  => $typeId,
            'group_code'     => $gCode,
            'group_name'     => $gInfo['group_name'] ?? 'IT Asset',
            'type_code'      => $tCode,
            'type_name'      => $typeName,
        ];
    }

    // TAMPILAN HALAMAN (STEP 1 atau STEP 2 PREVIEW)
    render_header('Import Data Aset Lama (Legacy)', $user);
    echo asset_nav_html();

    $previewList = $_SESSION['asset_legacy_import_preview'] ?? null;
    $opts = $_SESSION['asset_legacy_import_opts'] ?? [];

    if ($previewList !== null && !empty($previewList)) {
        // STEP 2: PREVIEW & SELEKSI CHECKLIST
        render_legacy_import_preview_selection_html($pdo, $previewList, $opts);
    } else {
        // STEP 1: FORM PEMILIHAN KOMODITAS, KATEGORI & UPLOAD
        render_legacy_import_init_form_html($pdo);
    }

    render_footer();
}

/**
 * Membaca baris tabel dari file spreadsheet (.xlsx atau .csv) tanpa library eksternal.
 */
function read_uploaded_spreadsheet_rows(string $filePath, string $ext): array
{
    $rows = [];

    if ($ext === 'csv' || $ext === 'txt') {
        $handle = fopen($filePath, 'r');
        if (!$handle) return [];

        // Deteksi delimiter (koma atau titik koma)
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = (substr_count($firstLine ?: '', ';') > substr_count($firstLine ?: '', ',')) ? ';' : ',';

        while (($data = fgetcsv($handle, 10000, $delimiter)) !== false) {
            // Hilangkan BOM pada sel pertama jika ada
            if (!empty($data[0])) {
                $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]);
            }
            if (!empty(array_filter($data, fn($v) => trim((string)$v) !== ''))) {
                $rows[] = array_map('trim', $data);
            }
        }
        fclose($handle);
        return $rows;
    }

    if ($ext === 'xlsx' && class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return [];
        }

        // 1. Baca shared strings
        $sharedStrings = [];
        $sharedXmlStr = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXmlStr !== false) {
            $xml = @simplexml_load_string($sharedXmlStr);
            if ($xml) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string)$si->t;
                    } elseif (isset($si->r)) {
                        $t = '';
                        foreach ($si->r as $r) {
                            $t .= (string)$r->t;
                        }
                        $sharedStrings[] = $t;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Baca sheet1.xml (atau sheet pertama yang ditemukan)
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
                            continue; // Batasi kolom maksimal 40 agar tidak memakan memori berlebih
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

    return $rows;
}

/**
 * Ekstraksi Tanggal Beli/Perolehan setelah tanda '/' dari kolom 'NO'.
 * Contoh input: '1 /21-2-2023' -> return ['1 /21-2-2023', '2023-02-21']
 */
function extract_legacy_code_and_date(string $rawNo): array
{
    $rawNo = trim($rawNo);
    $legacyCode = $rawNo;
    $installedAt = null;

    if (strpos($rawNo, '/') !== false) {
        $parts = explode('/', $rawNo, 2);
        $afterSlash = trim($parts[1]);

        // Pattern 1: DD-MM-YYYY atau DD/MM/YYYY atau DD.MM.YYYY
        if (preg_match('/(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})/', $afterSlash, $m)) {
            $d = (int)$m[1];
            $mon = (int)$m[2];
            $y = (int)$m[3];
            if ($y < 100) { $y += 2000; }
            if ($d > 12 && $mon <= 12) {
                $installedAt = sprintf('%04d-%02d-%02d', $y, $mon, $d);
            } elseif ($mon > 12 && $d <= 12) {
                $installedAt = sprintf('%04d-%02d-%02d', $y, $d, $mon);
            } else {
                $installedAt = sprintf('%04d-%02d-%02d', $y, $mon, $d);
            }
        } elseif (preg_match('/(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})/', $afterSlash, $m)) {
            // Pattern 2: YYYY-MM-DD
            $installedAt = sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
        }
    }

    return [$legacyCode, $installedAt];
}

/**
 * Resolusi Alokasi Lokasi dari Kolom Ruko, Lantai, dan Ruangan.
 * Menghubungkan ke tabel `asset_locations` (dibuat baru jika belum ada),
 * dan merangkum detail ruangan ke `location_label`.
 */
function resolve_or_create_asset_location(PDO $pdo, string $ruko, string $lantai, string $ruangan, ?int $defaultLocId = null, bool $dryRun = false): array
{
    static $locCache = [];
    $cacheKey = $ruko . '|' . $lantai . '|' . $ruangan . '|' . ($defaultLocId ?? 0) . '|' . ($dryRun ? 1 : 0);
    if (isset($locCache[$cacheKey])) {
        return $locCache[$cacheKey];
    }

    $ruko = trim($ruko);
    $lantai = trim($lantai);
    $ruangan = trim($ruangan);

    $locationId = $defaultLocId ?: null;
    $locationName = '-';
    $isNew = false;

    if ($ruko !== '') {
        // Standarisasi nama Master Lokasi (misal: "Ruko BLOK AY NO. 16 A" atau sesuai teks)
        $cleanMasterName = (stripos($ruko, 'ruko') === false && stripos($ruko, 'gedung') === false && stripos($ruko, 'kantor') === false)
            ? 'Ruko ' . $ruko
            : $ruko;

        // Cari di asset_locations
        $stmt = $pdo->prepare("SELECT id, location_name FROM asset_locations WHERE UPPER(TRIM(location_name)) = UPPER(TRIM(?)) OR UPPER(TRIM(location_name)) = UPPER(TRIM(?)) LIMIT 1");
        $stmt->execute([$cleanMasterName, $ruko]);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($found) {
            $locationId = (int)$found['id'];
            $locationName = $found['location_name'];
            $isNew = false;
        } else {
            // Cek fuzzy like
            $stmtFuzzy = $pdo->prepare("SELECT id, location_name FROM asset_locations WHERE location_name LIKE ? LIMIT 1");
            $stmtFuzzy->execute(['%' . $ruko . '%']);
            $fuzzy = $stmtFuzzy->fetch(PDO::FETCH_ASSOC);
            if ($fuzzy) {
                $locationId = (int)$fuzzy['id'];
                $locationName = $fuzzy['location_name'];
                $isNew = false;
            } else {
                // Lokasi baru
                $locationName = $cleanMasterName;
                $isNew = true;
                if (!$dryRun) {
                    // Buat kode unik
                    $slug = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($ruko));
                    $baseCode = 'LOC-' . substr($slug ?: 'RUKO', 0, 10);
                    $locCode = $baseCode;
                    $counter = 1;
                    while ($pdo->query("SELECT id FROM asset_locations WHERE location_code = " . $pdo->quote($locCode))->fetchColumn()) {
                        $locCode = $baseCode . '-' . $counter++;
                    }
                    $ins = $pdo->prepare("INSERT INTO asset_locations (location_code, location_name, is_active) VALUES (?, ?, 1)");
                    $ins->execute([$locCode, $locationName]);
                    $locationId = (int)$pdo->lastInsertId();
                }
            }
        }
    }

    // Susun detail Ruangan & Lantai
    $detailParts = [];
    if ($lantai !== '') {
        $detailParts[] = 'Lantai ' . $lantai;
    }
    if ($ruangan !== '') {
        $detailParts[] = $ruangan;
    }
    $locationLabel = implode(' - ', $detailParts);

    $res = [
        'location_id'    => $locationId,
        'location_name'  => $locationName,
        'location_label' => $locationLabel,
        'is_new'         => $isNew,
    ];
    $locCache[$cacheKey] = $res;
    return $res;
}

/**
 * AI & Heuristic Parser yang spesifik disesuaikan dengan Kategori yang dipilih (AC, Laptop, Komputer, Kendaraan, dll.).
 */
function parse_asset_description_by_category(PDO $pdo, string $desc, string $legacyCode, string $gCode, string $tCode, bool $useAi = true): array
{
    $desc = trim($desc);
    $result = [
        'brand'         => '',
        'model'         => '',
        'asset_name'    => '',
        'serial_number' => '',
        'mac_address'   => '',
        'specs_map'     => [],
        'specs_summary' => '',
        'custodian_name'=> '',
        'ai_method'     => 'Smart Engine',
    ];

    if ($desc === '') {
        return $result;
    }

    // SMART HEURISTIC & SEMANTIC ENGINE (Super cepat & tanpa network latency)
    // ----------------------------------------------------------------------

    // Deteksi Brand Terkenal
    $knownBrands = [
        'Sharp', 'Daikin', 'Panasonic', 'Mitsubishi', 'LG', 'Gree', 'Samsung', 'AUX', 
        'Changhong', 'Toshiba', 'Polytron', 'Haier', 'Aqua', 'Midea', 'TCL', 'Hisense', 
        'Electrolux', 'Lenovo', 'Dell', 'HP', 'Asus', 'Acer', 'Apple', 'Epson', 'Canon', 
        'Brother', 'Cisco', 'MikroTik', 'Ruijie', 'Ubiquiti', 'TP-Link', 'D-Link',
        'Perkins', 'Cummins', 'Yanmar', 'Denyo', 'Toyota', 'Honda', 'Yamaha', 'Suzuki', 'Isuzu'
    ];

    foreach ($knownBrands as $b) {
        if (preg_match('/\b' . preg_quote($b, '/') . '\b/i', $desc)) {
            $result['brand'] = $b;
            break;
        }
    }

    // Deteksi Serial Number (SN / S/N / Serial No / SN:)
    if (preg_match('/\b(?:S\/N|SN|Serial\s*(?:Number|No)?|Tag)\s*[:#]?\s*([A-Za-z0-9_-]{4,32})\b/i', $desc, $mSn)) {
        $result['serial_number'] = trim($mSn[1]);
    }

    // Deteksi MAC Address
    if (preg_match('/([0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2})/', $desc, $mMac)) {
        $result['mac_address'] = strtoupper(trim($mMac[1]));
    }

    $specs = [];

    // JIKA KATEGORI AC / PENDINGIN (AC)
    if ($tCode === 'AC' || stripos($desc, 'AC') !== false) {
        // Kapasitas Pendinginan (PK / BTU)
        // Format: 1/2 PK, 3/4 PK, 1 PK, 1.5 PK, 1 1/2 PK, 2 PK, 2.5 PK, 3 PK, 5 PK, dst.
        if (preg_match('/(\d+(?:\s*\/\s*\d+)?(?:\.\d+)?\s*(?:PK|pk|BTU|btu))/i', $desc, $mPk)) {
            $specs['COOLING_CAP'] = strtoupper(trim(preg_replace('/\s+/', ' ', $mPk[1])));
            $result['model'] = $specs['COOLING_CAP'];
        }

        // Tipe AC
        if (preg_match('/\b(Split(?:\s*Wall)?|Cassette|Floor\s*Standing|Ducting|Portable|Central|Window)\b/i', $desc, $mType)) {
            $specs['AC_TYPE'] = trim($mType[1]);
        } else {
            $specs['AC_TYPE'] = 'Split Wall';
        }

        // Jenis Freon / Refrigerant
        if (preg_match('/\b(R32|R410A|R22|R290|R134a)\b/i', $desc, $mFreon)) {
            $specs['REFRIGERANT'] = strtoupper(trim($mFreon[1]));
        }

        // Fitur Inverter
        if (stripos($desc, 'inverter') !== false) {
            $specs['INVERTER'] = 'Inverter';
        } else {
            $specs['INVERTER'] = 'Standard';
        }

        // Daya Listrik / Watt
        if (preg_match('/(\d+\s*(?:Watt|W))\b/i', $desc, $mWatt)) {
            $specs['POWER_WATT'] = trim($mWatt[1]);
        }

        // Nama Aset Rapi
        $cleanNameParts = ['AC'];
        if (!empty($result['brand'])) $cleanNameParts[] = $result['brand'];
        if (!empty($specs['COOLING_CAP'])) $cleanNameParts[] = $specs['COOLING_CAP'];
        if ($specs['INVERTER'] === 'Inverter') $cleanNameParts[] = 'Inverter';
        $result['asset_name'] = implode(' ', $cleanNameParts);
    }
    // JIKA KATEGORI KOMPUTER / LAPTOP / SERVER (CMP / NBK / SRV)
    elseif (in_array($tCode, ['CMP', 'NBK', 'SRV'], true) || stripos($desc, 'core i') !== false || stripos($desc, 'ram') !== false) {
        if (preg_match('/\b(Intel\s+(?:Core\s+)?[iI][3579][\w-]*|Core\s+i[3579][\w-]*|Ryzen\s+[3579][\w-]*|Xeon[\w-]*|Celeron[\w-]*|Apple\s+M[1234][\w\s]*)\b/i', $desc, $mCpu)) {
            $specs['PROCESSOR'] = trim($mCpu[1]);
        }
        if (preg_match('/\b(?:RAM\s*:?\s*)?(\d+\s*(?:GB|MB)(?:\s*DDR\d)?)\b/i', $desc, $mRam)) {
            $specs['RAM'] = trim($mRam[1]);
        }
        if (preg_match('/\b(\d+\s*(?:GB|TB)\s*(?:SSD|HDD|NVMe))\b/i', $desc, $mSto)) {
            $specs['STORAGE'] = trim($mSto[1]);
        }
        if (preg_match('/\b(Windows\s+(?:11|10|8|7|Server)\s*(?:Pro|Home)?|Ubuntu|macOS|Linux)\b/i', $desc, $mOs)) {
            $specs['OS'] = trim($mOs[1]);
        }
        $prefix = ($tCode === 'NBK') ? 'Laptop' : 'PC';
        $result['asset_name'] = trim($prefix . ' ' . ($result['brand'] ?: '') . ' ' . ($result['model'] ?: ''));
    }
    // KATEGORI KENDARAAN / GENSET / MESIN (VEH / GEN / MAC)
    elseif (in_array($tCode, ['VEH', 'GEN', 'MAC'], true) || stripos($desc, 'kva') !== false || stripos($desc, 'genset') !== false) {
        if (preg_match('/(\d+\s*(?:KVA|kva|KW|kw))/i', $desc, $mKva)) {
            $specs['POWER_CAP'] = strtoupper(trim($mKva[1]));
        }
        if (preg_match('/(\d+\s*cc)\b/i', $desc, $mCc)) {
            $specs['ENGINE_CC'] = trim($mCc[1]);
        }
        if (preg_match('/\b(?:No\.?\s*(?:Rangka|Mesin|Polisi)|Plat)\s*[:#]?\s*([A-Za-z0-9\s]{4,15})\b/i', $desc, $mPlat)) {
            $specs['NO_REG'] = trim($mPlat[1]);
        }
        $result['asset_name'] = trim(($result['brand'] ?: '') . ' ' . ($specs['POWER_CAP'] ?? ''));
    }
    // DEFAULT UMUM
    else {
        $result['asset_name'] = mb_strimwidth($desc, 0, 45, '...');
    }

    if (empty($result['asset_name'])) {
        $result['asset_name'] = mb_strimwidth($desc, 0, 45, '...');
    }

    $result['specs_map'] = $specs;
    $summaryLines = [];
    foreach ($specs as $k => $v) {
        $summaryLines[] = "<strong>{$k}:</strong> {$v}";
    }
    $result['specs_summary'] = implode(' &bull; ', $summaryLines);

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
        . '    <p class="muted" style="margin:4px 0 0 0;">Pilih Komoditas dan Kategori tujuan terlebih dahulu, kemudian upload file Excel (.xlsx) / CSV inventaris lama Anda.</p>'
        . '  </div>'
        . '  <div>'
        . '    <a class="btn" href="' . route_url('asset_legacy_import', ['action' => 'download_template']) . '" style="background:#0284c7;color:#fff;border-color:#0284c7;font-weight:600;">📥 Download Template Excel / CSV</a>'
        . '  </div>'
        . '</div>';

    echo '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px 18px;margin-bottom:20px;font-size:13px;color:#1e40af;line-height:1.6;">'
        . '📌 <strong>Struktur Data Kolom yang Dibaca Otomatis:</strong><br>'
        . '&bull; <strong>NO:</strong> Kode Legacy lama (misal <code>1 /21-2-2023</code>). Tanggal setelah tanda <code>/</code> otomatis diekstrak menjadi <strong>Tanggal Perolehan / Beli</strong>.<br>'
        . '&bull; <strong>NAMA INVENTARIS + SPESIFIKASI:</strong> Dibedah oleh AI & Smart Engine menjadi Merek, Model, Serial Number, dan Spesifikasi sesuai Kategori pilihan Anda.<br>'
        . '&bull; <strong>RUKO, LANTAI, RUANGAN:</strong> Otomatis dialokasikan ke <strong>Master Lokasi</strong> (dibuatkan baru jika belum ada) dan detail ruangan dicatat pada label lokasi.<br>'
        . '&bull; <strong>Seleksi Interaktif:</strong> Setelah file diunggah, seluruh data akan muncul dalam bentuk list checklist sehingga Anda dapat memilih item per Komoditas dan per Kategori.'
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
        . '    <span>🤖 Aktifkan AI Parsing Otomatis (Ekstrak SN, Spesifikasi, Alokasi Lokasi & Tanggal dari Kode)</span>'
        . '  </label>'
        . '</div>'
        . '<div class="actions" style="margin-top:20px;">'
        . '  <button type="submit" class="btn primary" style="padding:10px 24px;font-size:14px;font-weight:700;">🔍 Upload & Buka List Pilihan Import</button>'
        . '  <a class="btn" href="' . route_url('asset_items') . '">Kembali ke Daftar Aset</a>'
        . '</div>'
        . '</form>'
        . '</section>';

    echo '<script>
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
 * Merender Step 2: Halaman Preview List Checklist Interaktif agar user bisa memilih baris per kategori.
 */
function render_legacy_import_preview_selection_html(PDO $pdo, array $previewList, array $opts): void
{
    $totalRows = count($previewList);
    $dupCount = count(array_filter($previewList, fn($x) => !empty($x['is_duplicate'])));
    $readyCount = $totalRows - $dupCount;

    $targetGroupName = $opts['group_name'] ?? 'Facility';
    $targetTypeName = $opts['type_name'] ?? 'AC / Pendingin';

    echo '<section class="panel">'
        . '<div class="split" style="align-items:center;margin-bottom:14px;">'
        . '  <div>'
        . '    <h1 style="margin:0;">📋 Pilih Baris yang Akan Diimpor</h1>'
        . '    <p class="muted" style="margin:4px 0 0 0;">Centang unit yang ingin diimpor ke kategori target di bawah ini. Anda dapat melakukan uncheck atau filter baris sesuai kebutuhan.</p>'
        . '  </div>'
        . '  <div style="display:flex;gap:8px;">'
        . '    <span class="badge ok" style="font-size:13px;padding:6px 14px;">Terbaca: ' . $totalRows . ' Unit</span>'
        . ($dupCount > 0 ? ('<span class="badge danger" style="font-size:13px;padding:6px 14px;">Duplikat Dilewati: ' . $dupCount . '</span>') : '')
        . '  </div>'
        . '</div>';

    // Banner Target Komoditas & Kategori
    echo '<div style="background:#eff6ff;border:2px solid #3b82f6;border-radius:8px;padding:14px 18px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">'
        . '<div>'
        . '  <span style="font-size:11px;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:0.5px;">Target Impor Saat Ini:</span><br>'
        . '  <strong style="font-size:16px;color:#1e3a8a;">📁 Komoditas: ' . e($targetGroupName) . ' &bull; 🏷️ Kategori: ' . e($targetTypeName) . '</strong>'
        . '</div>'
        . '<div>'
        . '  <a class="btn" href="' . route_url('asset_legacy_import', ['action' => 'reset']) . '" style="background:#fff;color:#dc2626;border-color:#fca5a5;font-size:12.5px;">🔄 Ganti Kategori / File</a>'
        . '</div>'
        . '</div>';

    echo '<form id="importExecForm" method="post" action="' . route_url('asset_legacy_import', ['action' => 'execute_import']) . '">'
        . '<input type="hidden" name="csrf" value="' . csrf_token() . '">'
        . '<input type="hidden" name="company_id" value="' . (int)($opts['company_id'] ?? 0) . '">'
        . '<input type="hidden" name="asset_group_id" value="' . (int)($opts['asset_group_id'] ?? 0) . '">'
        . '<input type="hidden" name="asset_type_id" value="' . (int)($opts['asset_type_id'] ?? 0) . '">';

    // Kontrol Checklist & Filter Klien
    echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:12px;flex-wrap:wrap;">'
        . '  <div style="display:flex;gap:8px;align-items:center;">'
        . '    <button type="button" class="btn" onclick="toggleSelectAll(true)" style="padding:6px 12px;font-size:12px;background:#fff;">✓ Pilih Semua</button>'
        . '    <button type="button" class="btn" onclick="toggleSelectAll(false)" style="padding:6px 12px;font-size:12px;background:#fff;">✕ Batal Pilih Semua</button>'
        . '    <span id="selectedCountDisplay" style="font-size:13px;font-weight:700;color:#0284c7;margin-left:8px;">0 Item Terpilih</span>'
        . '  </div>'
        . '  <div style="flex:1;max-width:320px;">'
        . '    <input type="text" id="filterInput" placeholder="🔍 Cari nama barang / lokasi / kode..." oninput="filterTableRows(this.value)" style="padding:7px 12px;width:100%;box-sizing:border-box;font-size:13px;">'
        . '  </div>'
        . '</div>';

    echo '<div style="overflow-x:auto;max-height:550px;border:1px solid #cbd5e1;border-radius:8px;">'
        . '<table id="previewTable" style="margin:0;font-size:12.5px;min-width:1150px;">'
        . '<thead><tr style="position:sticky;top:0;background:#f8fafc;z-index:10;box-shadow:0 1px 2px rgba(0,0,0,0.06);">'
        . '<th style="width:40px;text-align:center;"><input type="checkbox" id="masterCheckbox" onclick="toggleSelectAll(this.checked)"></th>'
        . '<th style="width:40px;text-align:center;">No</th>'
        . '<th style="width:130px;">Kode Legacy (NO)</th>'
        . '<th style="width:125px;">Tgl Perolehan</th>'
        . '<th style="width:230px;">Nama Barang & Merek</th>'
        . '<th>Spesifikasi & Identifiers</th>'
        . '<th style="width:220px;">Alokasi Master Lokasi & Ruangan</th>'
        . '<th style="width:110px;text-align:center;">Status</th>'
        . '</tr></thead><tbody>';

    $no = 0;
    foreach ($previewList as $idx => $item) {
        $no++;
        $isDup = !empty($item['is_duplicate']);
        $rowBg = $isDup ? 'background:#fff1f2;' : ($no % 2 === 0 ? 'background:#f8fafc;' : 'background:#fff;');
        $isChecked = ''; // Default tidak terselect semua

        $statusBadge = $isDup
            ? '<span class="badge danger" title="Sudah terdaftar sebagai unit ' . e($item['dup_asset_code']) . '">Duplikat</span>'
            : '<span class="badge ok">✓ Siap Impor</span>';

        // Tampilan Lokasi
        $locMasterText = e($item['loc_master_name'] ?: '-');
        $locBadge = !empty($item['loc_is_new'])
            ? ' <span style="font-size:10px;color:#166534;background:#dcfce7;padding:1px 5px;border-radius:4px;border:1px solid #bbf7d0;font-weight:700;">+ Buat Lokasi Baru</span>'
            : '';
        $locDetailText = !empty($item['loc_label']) ? '<div style="font-size:11.5px;color:#475569;margin-top:2px;">📍 ' . e($item['loc_label']) . '</div>' : '';

        // Input Edit Tgl Perolehan
        $installedVal = !empty($item['installed_at']) ? date('Y-m-d', strtotime($item['installed_at'])) : '';
        $dateInput = '<input type="date" name="items[' . $idx . '][installed_at]" value="' . e($installedVal) . '" style="padding:4px 6px;font-size:12px;width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:4px;">';

        // Input Edit Nama Barang & Merek
        $nameInput = '<input type="text" name="items[' . $idx . '][asset_name]" value="' . e($item['asset_name'] ?: '') . '" placeholder="Nama Barang" style="padding:4px 6px;font-size:12px;font-weight:700;width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:4px;">'
            . '<input type="text" name="items[' . $idx . '][brand]" value="' . e($item['brand'] ?: '') . '" placeholder="Merek (e.g. Sharp, Dell)" style="margin-top:3px;padding:3px 6px;font-size:11px;width:100%;box-sizing:border-box;border:1px solid #e2e8f0;border-radius:4px;">';

        // Tampilan Spesifikasi + Input Edit SN
        $specText = !empty($item['specs_summary']) ? ('<div style="font-size:11.5px;color:#334155;margin-bottom:3px;">' . $item['specs_summary'] . '</div>') : '';
        $specText .= '<input type="text" name="items[' . $idx . '][serial_number]" value="' . e($item['serial_number'] ?? '') . '" placeholder="Serial Number (SN)" style="padding:3px 6px;font-size:11px;width:100%;box-sizing:border-box;border:1px solid #e2e8f0;border-radius:4px;">';
        $specText .= '<div class="muted" style="font-size:10.5px;margin-top:3px;">Teks Asli: ' . e(mb_strimwidth((string)$item['raw_description'], 0, 45, '...')) . '</div>';

        // Input Edit Detail Ruangan / Lokasi
        $locInput = '<div style="font-size:12px;">🏢 <strong>' . $locMasterText . '</strong>' . $locBadge . '</div>'
            . '<input type="text" name="items[' . $idx . '][loc_label]" value="' . e($item['loc_label'] ?? '') . '" placeholder="Detail Ruangan / Lantai" style="margin-top:3px;padding:3px 6px;font-size:11px;width:100%;box-sizing:border-box;border:1px solid #e2e8f0;border-radius:4px;">';

        echo '<tr class="preview-row" style="' . $rowBg . '" data-search="' . e(strtolower($item['legacy_code'] . ' ' . $item['asset_name'] . ' ' . $item['brand'] . ' ' . $item['loc_master_name'] . ' ' . $item['loc_label'] . ' ' . $item['raw_description'])) . '">'
            . '<td style="text-align:center;"><input type="checkbox" name="selected_rows[]" value="' . $idx . '" ' . $isChecked . ' onchange="updateSelectedCount()"></td>'
            . '<td style="text-align:center;">' . $no . '</td>'
            . '<td><strong style="color:#0369a1;font-family:monospace;font-size:13px;">' . e($item['legacy_code']) . '</strong><input type="hidden" name="items[' . $idx . '][legacy_code]" value="' . e($item['legacy_code']) . '"></td>'
            . '<td>' . $dateInput . '</td>'
            . '<td>' . $nameInput . '</td>'
            . '<td>' . $specText . '</td>'
            . '<td>' . $locInput . '</td>'
            . '<td style="text-align:center;">' . $statusBadge . '</td>'
            . '</tr>';
    }

    echo '</tbody></table></div>';

    echo '<div class="actions" style="margin-top:20px;display:flex;gap:12px;align-items:center;">'
        . '  <button type="submit" class="btn primary" style="padding:12px 28px;font-size:15px;font-weight:700;">💾 Import Item Terpilih ke Kategori \'' . e($targetTypeName) . '\'</button>'
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
    </script>';
}
