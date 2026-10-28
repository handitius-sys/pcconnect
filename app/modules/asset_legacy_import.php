<?php

declare(strict_types=1);

/**
 * Modul: Import Data Aset Lama (Legacy) dengan AI-Assisted Parsing
 * 
 * Fungsi modul ini:
 * 1. Mengimpor data aset lama dari file Excel (.xlsx) atau CSV (.csv).
 * 2. Memetakan kolom:
 *    - Kode Aset Lama -> Disimpan sebagai `alias_code` pada unit aset.
 *    - Deskripsi Bebas -> Dibedah oleh AI / Smart Heuristic Engine menjadi Identifiers (SN, MAC) & Spesifikasi (CPU, RAM, Storage, dll.).
 *    - Tanggal Masuk -> Disimpan sebagai `installed_at`.
 * 3. Menyediakan mode Preview & Konfirmasi sebelum data disimpan permanen ke database `asset_items`.
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
        // Tambahkan BOM agar karakter terbaca rapi di Microsoft Excel Windows
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Kode Lama', 'Deskripsi / Spesifikasi Lengkap', 'Tanggal Masuk', 'Lokasi', 'Pengguna']);
        fputcsv($out, ['PC-IT-001', 'Laptop Lenovo ThinkPad T480 Intel Core i5-8250U RAM 16GB SSD 512GB Windows 11 Pro SN: PF1A2B3C MAC: 00:11:22:33:44:55', '2021-06-15', 'Ruang IT Lt 2', 'Budi Santoso']);
        fputcsv($out, ['PC-FIN-02', 'PC Desktop Dell OptiPlex 3080 Core i7-10700 RAM 32GB HDD 1TB VGA GTX 1650 SN: 9XYZ8765', '2020-11-20', 'Ruang Finance', 'Siti Aminah']);
        fputcsv($out, ['PRN-OPS-01', 'Printer HP LaserJet Pro M404dn SN: VNC3K01928', '2022-03-10', 'Ruang Operasional', 'Andi Pratama']);
        fclose($out);
        exit;
    }

    // 2. Eksekusi Simpan Data ke Database (Step 3)
    if ($action === 'execute_import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $previewData = $_SESSION['asset_legacy_import_preview'] ?? [];
        if (empty($previewData)) {
            flash('Tidak ada data preview yang siap diimpor. Silakan upload file kembali.', 'err');
            redirect_to('asset_legacy_import');
        }

        $importedCount = 0;
        $skippedCount = 0;
        $companyId = (int)($_POST['company_id'] ?? 0) ?: null;
        $locationId = (int)($_POST['location_id'] ?? 0) ?: null;
        $defaultGroupId = (int)($_POST['asset_group_id'] ?? 0) ?: 1;

        // Ambil peta groups dan types
        $groups = [];
        foreach ($pdo->query("SELECT id, UPPER(TRIM(group_code)) AS code, group_name FROM asset_groups")->fetchAll(PDO::FETCH_ASSOC) as $g) {
            $groups[$g['code']] = (int)$g['id'];
        }
        $types = [];
        foreach ($pdo->query("SELECT id, asset_group_id, UPPER(TRIM(type_code)) AS code, type_name FROM asset_types")->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $types[$t['asset_group_id'] . '_' . $t['code']] = (int)$t['id'];
            $types['NAME_' . strtoupper(trim($t['type_name']))] = (int)$t['id'];
        }

        // Ambil ID status aktif
        $activeStatusId = (int)($pdo->query("SELECT id FROM asset_statuses WHERE status_code = 'ACTIVE' LIMIT 1")->fetchColumn() ?: 1);

        $pdo->beginTransaction();
        try {
            foreach ($previewData as $row) {
                $legacyCode = trim((string)($row['legacy_code'] ?? ''));
                if ($legacyCode === '') {
                    $skippedCount++;
                    continue;
                }

                // Cek apakah alias_code sudah ada
                $checkStmt = $pdo->prepare("SELECT id, asset_code FROM asset_items WHERE alias_code = ? LIMIT 1");
                $checkStmt->execute([$legacyCode]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    $skippedCount++;
                    continue;
                }

                $gCode = strtoupper(trim((string)($row['group_code'] ?? 'IT')));
                $tCode = strtoupper(trim((string)($row['type_code'] ?? 'CMP')));
                $gId = $groups[$gCode] ?? ($groups['IT'] ?? $defaultGroupId);
                $tId = $types[$gId . '_' . $tCode] ?? ($types['NAME_' . strtoupper($row['category_name'] ?? '')] ?? (int)($pdo->query("SELECT id FROM asset_types WHERE asset_group_id = {$gId} LIMIT 1")->fetchColumn() ?: 1));

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
                $locLabel = trim((string)($row['location_label'] ?? ''));
                $rawNotes = 'Diimpor dari data lama. Deskripsi asli: ' . trim((string)($row['raw_description'] ?? ''));

                // Tipe nama
                $tNameStmt = $pdo->prepare("SELECT type_name FROM asset_types WHERE id = ?");
                $tNameStmt->execute([$tId]);
                $typeName = (string)($tNameStmt->fetchColumn() ?: 'Computer');

                // Insert asset_items
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
                    $gId,
                    $tId,
                    $activeStatusId,
                    $assetName,
                    $brandName,
                    $model,
                    $serialNumber ?: null,
                    $custodianName ?: null,
                    $rawNotes,
                    $companyId,
                    $locationId,
                    $locLabel ?: null,
                    $installedAt,
                    $typeName,
                    $typeName
                ]);
                $newId = (int)$pdo->lastInsertId();

                // Simpan Identifiers (Serial Number, MAC Address)
                if (!empty($serialNumber)) {
                    $idfStmt = $pdo->prepare("SELECT id FROM asset_type_identifiers WHERE asset_type_id = ? AND UPPER(identifier_code) LIKE '%SERIAL%' LIMIT 1");
                    $idfStmt->execute([$tId]);
                    $idfId = (int)($idfStmt->fetchColumn() ?: 0);
                    if ($idfId > 0) {
                        $pdo->prepare("INSERT INTO asset_identifiers (asset_item_id, asset_type_identifier_id, identifier_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE identifier_value=VALUES(identifier_value)")
                            ->execute([$newId, $idfId, $serialNumber]);
                    }
                }
                if (!empty($row['mac_address'])) {
                    $macVal = trim((string)$row['mac_address']);
                    $idfMacStmt = $pdo->prepare("SELECT id FROM asset_type_identifiers WHERE asset_type_id = ? AND UPPER(identifier_code) LIKE '%MAC%' LIMIT 1");
                    $idfMacStmt->execute([$tId]);
                    $macIdfId = (int)($idfMacStmt->fetchColumn() ?: 0);
                    if ($macIdfId > 0) {
                        $pdo->prepare("INSERT INTO asset_identifiers (asset_item_id, asset_type_identifier_id, identifier_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE identifier_value=VALUES(identifier_value)")
                            ->execute([$newId, $macIdfId, $macVal]);
                    }
                }

                // Simpan Spesifikasi (Processor, RAM, Storage, OS, VGA)
                $specsToSave = [
                    'PROCESSOR' => $row['processor'] ?? '',
                    'RAM'       => $row['ram'] ?? '',
                    'STORAGE'   => $row['storage'] ?? '',
                    'OS'        => $row['os'] ?? '',
                    'VGA'       => $row['vga'] ?? '',
                ];
                foreach ($specsToSave as $spCode => $spVal) {
                    $spVal = trim((string)$spVal);
                    if ($spVal === '') continue;
                    $spFindStmt = $pdo->prepare("SELECT id, specification_name FROM asset_type_specifications WHERE asset_type_id = ? AND UPPER(specification_code) LIKE ? LIMIT 1");
                    $spFindStmt->execute([$tId, '%' . $spCode . '%']);
                    $spRow = $spFindStmt->fetch(PDO::FETCH_ASSOC);
                    if ($spRow) {
                        $spId = (int)$spRow['id'];
                        $pdo->prepare("INSERT INTO asset_specifications (asset_item_id, asset_type_specification_id, specification_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE specification_value=VALUES(specification_value)")
                            ->execute([$newId, $spId, $spVal]);
                    }
                }

                $importedCount++;
            }
            $pdo->commit();
            unset($_SESSION['asset_legacy_import_preview']);
            flash("Berhasil mengimpor {$importedCount} unit aset lama ke sistem AsetConnect!" . ($skippedCount > 0 ? " ({$skippedCount} baris dilewati karena duplikat atau kode kosong)." : ""));
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

        // Deteksi Header Baris Pertama
        $headerRow = array_map(function($h) {
            return strtolower(trim((string)$h));
        }, $rows[0]);

        $colCode = -1;
        $colDesc = -1;
        $colDate = -1;
        $colLoc = -1;
        $colUser = -1;

        foreach ($headerRow as $idx => $headerText) {
            if ($colCode === -1 && (str_contains($headerText, 'kode') || str_contains($headerText, 'code') || str_contains($headerText, 'barcode') || str_contains($headerText, 'no aset'))) {
                $colCode = $idx;
            } elseif ($colDesc === -1 && (str_contains($headerText, 'deskripsi') || str_contains($headerText, 'description') || str_contains($headerText, 'spek') || str_contains($headerText, 'spesifikasi') || str_contains($headerText, 'nama'))) {
                $colDesc = $idx;
            } elseif ($colDate === -1 && (str_contains($headerText, 'tanggal') || str_contains($headerText, 'tgl') || str_contains($headerText, 'date') || str_contains($headerText, 'masuk') || str_contains($headerText, 'beli'))) {
                $colDate = $idx;
            } elseif ($colLoc === -1 && (str_contains($headerText, 'lokasi') || str_contains($headerText, 'ruangan') || str_contains($headerText, 'location'))) {
                $colLoc = $idx;
            } elseif ($colUser === -1 && (str_contains($headerText, 'pengguna') || str_contains($headerText, 'user') || str_contains($headerText, 'pic') || str_contains($headerText, 'custodian'))) {
                $colUser = $idx;
            }
        }

        // Fallback urutan kolom jika header tidak terdeteksi
        if ($colCode === -1) $colCode = 0;
        if ($colDesc === -1) $colDesc = count($headerRow) > 1 ? 1 : 0;
        if ($colDate === -1 && count($headerRow) > 2) $colDate = 2;

        $dataRows = array_slice($rows, 1);
        $previewList = [];

        foreach ($dataRows as $r) {
            $rawCode = trim((string)($r[$colCode] ?? ''));
            $rawDesc = trim((string)($r[$colDesc] ?? ''));
            $rawDate = $colDate >= 0 ? trim((string)($r[$colDate] ?? '')) : '';
            $rawLoc = $colLoc >= 0 ? trim((string)($r[$colLoc] ?? '')) : '';
            $rawUser = $colUser >= 0 ? trim((string)($r[$colUser] ?? '')) : '';

            if ($rawCode === '' && $rawDesc === '') {
                continue;
            }

            // Normalisasi Tanggal Masuk
            $normDate = null;
            if ($rawDate !== '') {
                $ts = strtotime(str_replace('/', '-', $rawDate));
                if ($ts && $ts > 0) {
                    $normDate = date('Y-m-d', $ts);
                }
            }

            // AI / Smart Parsing Deskripsi
            $parsed = parse_asset_description_ai($pdo, $rawDesc, $rawCode, $useAi);

            // Cek apakah kode lama sudah ada di database
            $isDuplicate = false;
            if ($rawCode !== '') {
                $cStmt = $pdo->prepare("SELECT id, asset_code FROM asset_items WHERE alias_code = ? LIMIT 1");
                $cStmt->execute([$rawCode]);
                $dup = $cStmt->fetch(PDO::FETCH_ASSOC);
                if ($dup) {
                    $isDuplicate = true;
                    $dupAssetCode = $dup['asset_code'];
                }
            }

            $previewList[] = [
                'legacy_code'      => $rawCode,
                'raw_description'  => $rawDesc,
                'installed_at'     => $normDate,
                'location_label'   => $rawLoc ?: ($parsed['location_label'] ?? ''),
                'custodian_name'   => $rawUser ?: ($parsed['custodian_name'] ?? ''),
                'group_code'       => $parsed['group_code'] ?? 'IT',
                'type_code'        => $parsed['type_code'] ?? 'CMP',
                'category_name'    => $parsed['category_name'] ?? 'Computer',
                'brand'            => $parsed['brand'] ?? '',
                'model'            => $parsed['model'] ?? '',
                'asset_name'       => $parsed['asset_name'] ?? '',
                'serial_number'    => $parsed['serial_number'] ?? '',
                'mac_address'      => $parsed['mac_address'] ?? '',
                'processor'        => $parsed['processor'] ?? '',
                'ram'              => $parsed['ram'] ?? '',
                'storage'          => $parsed['storage'] ?? '',
                'vga'              => $parsed['vga'] ?? '',
                'os'               => $parsed['os'] ?? '',
                'ai_method'        => $parsed['ai_method'] ?? 'Smart Engine',
                'is_duplicate'     => $isDuplicate,
                'dup_asset_code'   => $dupAssetCode ?? '',
            ];
        }

        $_SESSION['asset_legacy_import_preview'] = $previewList;
        $_SESSION['asset_legacy_import_opts'] = [
            'company_id'     => (int)($_POST['company_id'] ?? 0),
            'location_id'    => (int)($_POST['location_id'] ?? 0),
            'asset_group_id' => (int)($_POST['asset_group_id'] ?? 0),
        ];
    }

    // TAMPILAN HALAMAN (STEP 1 atau STEP 2 PREVIEW)
    render_header('Import Data Aset Lama (Excel)', $user);
    echo asset_nav_html();

    $previewList = $_SESSION['asset_legacy_import_preview'] ?? null;

    if ($previewList !== null && !empty($previewList)) {
        // STEP 2: PREVIEW & VERIFIKASI SEBELUM SIMPAN
        render_legacy_import_preview_html($pdo, $previewList, $_SESSION['asset_legacy_import_opts'] ?? []);
    } else {
        // STEP 1: FORM UPLOAD & OPSIONAL
        render_legacy_import_upload_form_html($pdo);
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

        // 2. Baca sheet1.xml
        $sheetXmlStr = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXmlStr === false) {
            $sheetXmlStr = $zip->getFromName('xl/worksheets/sheet.xml');
        }
        if ($sheetXmlStr !== false) {
            $sheetXml = @simplexml_load_string($sheetXmlStr);
            if ($sheetXml && isset($sheetXml->sheetData->row)) {
                foreach ($sheetXml->sheetData->row as $rowNode) {
                    $row = [];
                    foreach ($rowNode->c as $cell) {
                        $val = (string)$cell->v;
                        $type = (string)$cell['t'];
                        if ($type === 's' && isset($sharedStrings[(int)$val])) {
                            $val = $sharedStrings[(int)$val];
                        }
                        $row[] = trim($val);
                    }
                    if (!empty(array_filter($row, fn($v) => $v !== ''))) {
                        $rows[] = $row;
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
 * Mesin Parsing Cerdas AI + Heuristik Regex untuk membedah deskripsi bebas menjadi:
 * Category, Brand, Model, Serial Number, MAC, Processor, RAM, Storage, OS, VGA, Custodian, Lokasi.
 */
function parse_asset_description_ai(PDO $pdo, string $desc, string $legacyCode = '', bool $useAi = true): array
{
    $desc = trim($desc);
    $result = [
        'group_code'     => 'IT',
        'type_code'      => 'CMP',
        'category_name'  => 'Computer',
        'brand'          => '',
        'model'          => '',
        'asset_name'     => '',
        'serial_number'  => '',
        'mac_address'    => '',
        'processor'      => '',
        'ram'            => '',
        'storage'        => '',
        'vga'            => '',
        'os'             => '',
        'custodian_name' => '',
        'location_label' => '',
        'ai_method'      => 'Smart Rule Engine',
    ];

    if ($desc === '') {
        return $result;
    }

    // 1. Coba panggil Gemini AI jika API key tersedia dan opsi AI diaktifkan
    $apiKey = trim((string)(config_value('gemini_api_key') ?: getenv('GEMINI_API_KEY') ?: ''));
    if ($useAi && $apiKey !== '' && function_exists('curl_init')) {
        try {
            $prompt = "You are an expert IT hardware and asset management cataloger. Extract structured asset information from this legacy asset inventory description text:\n"
                . "Description: \"{$desc}\"\n"
                . "Legacy Asset Code: \"{$legacyCode}\"\n\n"
                . "Extract these specific fields and return ONLY a valid JSON object without markdown fences:\n"
                . "{\n"
                . "  \"type_code\": \"string (NBK for Laptop/Notebook, CMP for PC/Desktop/All-in-One, PRT for Printer, SRV for Server, or OTH for Other)\",\n"
                . "  \"category_name\": \"string (Notebook, Computer, Printer, Server, or Other)\",\n"
                . "  \"brand\": \"string (e.g. Lenovo, Dell, HP, Asus, Acer, Apple, Epson, Canon, etc.)\",\n"
                . "  \"model\": \"string (e.g. ThinkPad T480, OptiPlex 3080, LaserJet M404, etc.)\",\n"
                . "  \"asset_name\": \"string (Descriptive clean name, e.g. Laptop ThinkPad T480)\",\n"
                . "  \"serial_number\": \"string or empty if none\",\n"
                . "  \"mac_address\": \"string or empty if none\",\n"
                . "  \"processor\": \"string or empty (e.g. Intel Core i5-8250U, Ryzen 5 5600G)\",\n"
                . "  \"ram\": \"string or empty (e.g. 16GB DDR4)\",\n"
                . "  \"storage\": \"string or empty (e.g. SSD 512GB, HDD 1TB)\",\n"
                . "  \"vga\": \"string or empty (e.g. NVIDIA GTX 1650, Intel UHD)\",\n"
                . "  \"os\": \"string or empty (e.g. Windows 11 Pro)\",\n"
                . "  \"custodian_name\": \"string or empty if none\",\n"
                . "  \"location_label\": \"string or empty if none\"\n"
                . "}";

            $candidateModels = ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-flash'];
            foreach ($candidateModels as $cMod) {
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($cMod) . ':generateContent?key=' . urlencode($apiKey);
                $payload = json_encode([
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.1, 'responseMimeType' => 'application/json']
                ]);
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_TIMEOUT => 8,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($code === 200 && !empty($resp)) {
                    $json = json_decode($resp, true);
                    $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    if ($rawText !== '') {
                        $parsedJson = json_decode($rawText, true);
                        if (is_array($parsedJson)) {
                            $result['type_code'] = !empty($parsedJson['type_code']) ? strtoupper($parsedJson['type_code']) : 'CMP';
                            $result['category_name'] = !empty($parsedJson['category_name']) ? $parsedJson['category_name'] : 'Computer';
                            $result['brand'] = !empty($parsedJson['brand']) ? $parsedJson['brand'] : '';
                            $result['model'] = !empty($parsedJson['model']) ? $parsedJson['model'] : '';
                            $result['asset_name'] = !empty($parsedJson['asset_name']) ? $parsedJson['asset_name'] : '';
                            $result['serial_number'] = !empty($parsedJson['serial_number']) ? $parsedJson['serial_number'] : '';
                            $result['mac_address'] = !empty($parsedJson['mac_address']) ? $parsedJson['mac_address'] : '';
                            $result['processor'] = !empty($parsedJson['processor']) ? $parsedJson['processor'] : '';
                            $result['ram'] = !empty($parsedJson['ram']) ? $parsedJson['ram'] : '';
                            $result['storage'] = !empty($parsedJson['storage']) ? $parsedJson['storage'] : '';
                            $result['vga'] = !empty($parsedJson['vga']) ? $parsedJson['vga'] : '';
                            $result['os'] = !empty($parsedJson['os']) ? $parsedJson['os'] : '';
                            $result['custodian_name'] = !empty($parsedJson['custodian_name']) ? $parsedJson['custodian_name'] : '';
                            $result['location_label'] = !empty($parsedJson['location_label']) ? $parsedJson['location_label'] : '';
                            $result['ai_method'] = 'Gemini AI (' . $cMod . ')';
                            return $result;
                        }
                    }
                }
            }
        } catch (Throwable $ignored) {
            // Fallback ke Smart Heuristic Regex Engine jika koneksi AI gagal
        }
    }

    // 2. SMART HEURISTIC REGEX ENGINE (Fallback otomatis & Offline-Ready)
    // -----------------------------------------------------------------
    
    // Deteksi Kategori / Tipe Aset
    if (preg_match('/\b(laptop|notebook|thinkpad|macbook|zenbook|ideapad|latitude|elitebook|probook|vivobook|legion)\b/i', $desc)) {
        $result['type_code'] = 'NBK';
        $result['category_name'] = 'Notebook';
    } elseif (preg_match('/\b(printer|laserjet|deskjet|inkjet|dotmatrix|l3110|l3210|epson|canon|brother)\b/i', $desc)) {
        $result['type_code'] = 'PRT';
        $result['category_name'] = 'Printer';
    } elseif (preg_match('/\b(server|poweredge|proliant|rackmount)\b/i', $desc)) {
        $result['type_code'] = 'SRV';
        $result['category_name'] = 'Server';
    } else {
        $result['type_code'] = 'CMP';
        $result['category_name'] = 'Computer';
    }

    // Deteksi Brand / Merek
    $brands = ['Lenovo', 'Dell', 'HP', 'Hewlett-Packard', 'Asus', 'Acer', 'Apple', 'MSI', 'Toshiba', 'Samsung', 'Epson', 'Canon', 'Brother', 'Cisco', 'Mikrotik', 'Logitech', 'ViewSonic', 'BenQ', 'Philips', 'LG', 'APC', 'ICA'];
    foreach ($brands as $b) {
        if (preg_match('/\b' . preg_quote($b, '/') . '\b/i', $desc)) {
            $result['brand'] = ($b === 'Hewlett-Packard') ? 'HP' : $b;
            break;
        }
    }

    // Deteksi Serial Number
    if (preg_match('/\b(?:S\/N|SN|Serial\s*(?:Number|No)?|Service\s*Tag)\s*[:#]?\s*([A-Za-z0-9_-]{4,32})\b/i', $desc, $mSn)) {
        $result['serial_number'] = trim($mSn[1]);
    }

    // Deteksi MAC Address
    if (preg_match('/([0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2})/', $desc, $mMac)) {
        $result['mac_address'] = strtoupper(trim($mMac[1]));
    }

    // Deteksi Processor (CPU)
    if (preg_match('/\b(Intel\s+(?:Core\s+)?[iI][3579][\w-]*|Core\s+i[3579][\w-]*|Intel\s+Xeon[\w-]*|Intel\s+Celeron[\w-]*|Intel\s+Pentium[\w-]*|AMD\s+Ryzen\s+[3579][\w-]*|Ryzen\s+[3579][\w-]*|Apple\s+M[1234][\w\s]*)\b/i', $desc, $mCpu)) {
        $result['processor'] = trim($mCpu[1]);
    }

    // Deteksi RAM
    if (preg_match('/\b(?:RAM\s*:?\s*)?(\d+\s*(?:GB|MB)(?:\s*DDR\d)?)\b/i', $desc, $mRam)) {
        $result['ram'] = trim($mRam[1]);
    }

    // Deteksi Storage (SSD / HDD)
    if (preg_match('/\b(\d+\s*(?:GB|TB)\s*(?:SSD|HDD|NVMe))\b/i', $desc, $mSto)) {
        $result['storage'] = trim($mSto[1]);
    } elseif (preg_match('/\b(?:SSD|HDD|Storage)\s*:?\s*(\d+\s*(?:GB|TB))\b/i', $desc, $mSto2)) {
        $result['storage'] = trim($mSto2[1]);
    }

    // Deteksi Operating System
    if (preg_match('/\b(Windows\s+(?:11|10|8|7|Server)\s*(?:Pro|Home|Enterprise|Professional)?|Ubuntu|Debian|CentOS|macOS|Linux)\b/i', $desc, $mOs)) {
        $result['os'] = trim($mOs[1]);
    }

    // Deteksi VGA
    if (preg_match('/\b(GTX\s*\d+|RTX\s*\d+|Radeon\s*[\w\d]+|Intel\s+UHD|Intel\s+Iris)\b/i', $desc, $mVga)) {
        $result['vga'] = trim($mVga[1]);
    }

    // Deteksi Model & Nama Aset
    if (preg_match('/\b(ThinkPad\s+[\w\d]+|OptiPlex\s+[\w\d]+|Latitude\s+[\w\d]+|EliteBook\s+[\w\d]+|ProBook\s+[\w\d]+|LaserJet\s+[\w\d]+|EcoTank\s+[\w\d]+)\b/i', $desc, $mMod)) {
        $result['model'] = trim($mMod[1]);
    }

    // Nama Aset Bersih
    $prefixName = ($result['type_code'] === 'NBK') ? 'Laptop' : (($result['type_code'] === 'PRT') ? 'Printer' : 'PC');
    $result['asset_name'] = trim($prefixName . ' ' . $result['brand'] . ' ' . $result['model']);
    if (strlen($result['asset_name']) < 5) {
        $result['asset_name'] = mb_strimwidth($desc, 0, 45, '...');
    }

    // Deteksi Pengguna (PIC / User)
    if (preg_match('/\b(?:User|Pengguna|PIC)\s*:\s*([A-Za-z\s]+?)(?:,|$|\n)/i', $desc, $mUser)) {
        $result['custodian_name'] = trim($mUser[1]);
    }

    // Deteksi Lokasi
    if (preg_match('/\b(?:Lokasi|Ruang|Ruangan|Gedung)\s*:\s*([A-Za-z0-9\s]+?)(?:,|$|\n)/i', $desc, $mLoc)) {
        $result['location_label'] = trim($mLoc[1]);
    }

    return $result;
}

/**
 * Merender Step 1: Form Upload File Excel / CSV & Opsi Bantuan AI.
 */
function render_legacy_import_upload_form_html(PDO $pdo): void
{
    $companies = company_options($pdo, 0, true, '- Pilih Company -');
    $locations = asset_location_options($pdo, 0, true, '- Pilih Lokasi Unit Aset -');
    $groups = asset_group_options($pdo, 0, true, '- Default Komoditas (IT Asset) -');

    echo '<section class="panel">'
        . '<div class="split" style="align-items:center;margin-bottom:14px;">'
        . '  <div>'
        . '    <h1 style="margin:0;">📥 Import Data Aset Lama (Legacy)</h1>'
        . '    <p class="muted" style="margin:4px 0 0 0;">Upload data aset lama dari file Excel (.xlsx) atau CSV. Kode lama otomatis disimpan sebagai <strong>Alias Aset</strong> dan deskripsi bebas dibedah otomatis oleh AI.</p>'
        . '  </div>'
        . '  <div>'
        . '    <a class="btn" href="' . route_url('asset_legacy_import', ['action' => 'download_template']) . '" style="background:#0284c7;color:#fff;border-color:#0284c7;font-weight:600;">📥 Download Template Excel / CSV</a>'
        . '  </div>'
        . '</div>';

    echo '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px 18px;margin-bottom:20px;font-size:13px;color:#1e40af;line-height:1.6;">'
        . '📌 <strong>Format File yang Didukung:</strong><br>'
        . '&bull; File Microsoft Excel (<strong>.xlsx</strong>) atau File Teks Terpisah Koma (<strong>.csv</strong>).<br>'
        . '&bull; Kolom utama yang dibaca: <code>Kode Lama</code>, <code>Deskripsi / Spesifikasi</code>, dan <code>Tanggal Masuk</code>.<br>'
        . '&bull; <strong>Fitur AI Parsing:</strong> Kecerdasan Buatan (Gemini AI & Smart Heuristic Engine) akan otomatis membedah teks deskripsi dan memisahkannya menjadi <em>Serial Number</em>, <em>MAC Address</em>, <em>Processor</em>, <em>RAM</em>, <em>Storage (SSD/HDD)</em>, <em>OS</em>, serta merek dan model.'
        . '</div>';

    echo '<form method="post" enctype="multipart/form-data" action="' . route_url('asset_legacy_import', ['action' => 'upload_preview']) . '">'
        . '<input type="hidden" name="csrf" value="' . csrf_token() . '">'
        . '<div class="grid two">'
        . '  <label>File Excel (.xlsx) / CSV (.csv) *<input type="file" name="import_file" accept=".xlsx,.csv,.txt" required style="padding:8px;background:#fff;"></label>'
        . '  <label>Default Komoditas Aset<select name="asset_group_id">' . $groups . '</select></label>'
        . '</div>'
        . '<div class="grid two">'
        . '  <label>Default Company Pemilik<select name="company_id">' . $companies . '</select></label>'
        . '  <label>Default Lokasi Penempatan<select name="location_id">' . $locations . '</select></label>'
        . '</div>'
        . '<div style="background:#f8fafc;padding:12px 16px;border:1px solid #e2e8f0;border-radius:6px;margin:14px 0;">'
        . '  <label style="display:flex;align-items:center;gap:10px;margin:0;cursor:pointer;font-weight:600;color:#0f172a;">'
        . '    <input type="checkbox" name="use_ai" value="1" checked style="width:18px;height:18px;accent-color:#2563eb;">'
        . '    <span>🤖 Aktifkan AI Parsing Otomatis (Ekstrak SN, MAC, CPU, RAM, SSD/HDD dari Teks Deskripsi Bebas)</span>'
        . '  </label>'
        . '</div>'
        . '<div class="actions" style="margin-top:20px;">'
        . '  <button type="submit" class="btn primary" style="padding:10px 22px;font-size:14px;font-weight:700;">🔍 Upload & Preview Hasil Parsing AI</button>'
        . '  <a class="btn" href="' . route_url('asset_items') . '">Kembali ke Daftar Aset</a>'
        . '</div>'
        . '</form>'
        . '</section>';
}

/**
 * Merender Step 2: Halaman Preview Hasil Ekstraksi AI & Verifikasi Sebelum Simpan.
 */
function render_legacy_import_preview_html(PDO $pdo, array $previewList, array $opts): void
{
    $companyName = '-';
    if (!empty($opts['company_id'])) {
        $companyName = (string)$pdo->query("SELECT company_name FROM asset_companies WHERE id = " . (int)$opts['company_id'])->fetchColumn() ?: '-';
    }
    $locationName = '-';
    if (!empty($opts['location_id'])) {
        $locationName = (string)$pdo->query("SELECT location_name FROM asset_locations WHERE id = " . (int)$opts['location_id'])->fetchColumn() ?: '-';
    }

    $totalRows = count($previewList);
    $dupCount = count(array_filter($previewList, fn($x) => !empty($x['is_duplicate'])));
    $readyCount = $totalRows - $dupCount;

    echo '<section class="panel">'
        . '<div class="split" style="align-items:center;margin-bottom:14px;">'
        . '  <div>'
        . '    <h1 style="margin:0;">🔍 Verifikasi Hasil Parsing AI Aset Lama</h1>'
        . '    <p class="muted" style="margin:4px 0 0 0;">Periksa hasil ekstraksi AI di bawah ini sebelum menyimpan permanen ke database unit aset.</p>'
        . '  </div>'
        . '  <div style="display:flex;gap:8px;">'
        . '    <span class="badge ok" style="font-size:13px;padding:6px 14px;">Siap Impor: ' . $readyCount . ' Unit</span>'
        . ($dupCount > 0 ? ('<span class="badge danger" style="font-size:13px;padding:6px 14px;">Duplikat Dilewati: ' . $dupCount . ' Unit</span>') : '')
        . '  </div>'
        . '</div>';

    echo '<div style="background:#f8fafc;padding:12px 18px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:18px;display:flex;gap:24px;flex-wrap:wrap;font-size:13px;">'
        . '<div><strong>Company:</strong> ' . e($companyName) . '</div>'
        . '<div><strong>Lokasi:</strong> ' . e($locationName) . '</div>'
        . '<div><strong>Total Baris Terbaca:</strong> ' . $totalRows . ' Data</div>'
        . '</div>';

    echo '<form method="post" action="' . route_url('asset_legacy_import', ['action' => 'execute_import']) . '">'
        . '<input type="hidden" name="csrf" value="' . csrf_token() . '">'
        . '<input type="hidden" name="company_id" value="' . (int)($opts['company_id'] ?? 0) . '">'
        . '<input type="hidden" name="location_id" value="' . (int)($opts['location_id'] ?? 0) . '">'
        . '<input type="hidden" name="asset_group_id" value="' . (int)($opts['asset_group_id'] ?? 0) . '">';

    echo '<div style="overflow-x:auto;max-height:550px;border:1px solid #e2e8f0;border-radius:8px;">'
        . '<table style="margin:0;font-size:12.5px;min-width:1050px;">'
        . '<thead><tr>'
        . '<th style="width:40px;text-align:center;">No</th>'
        . '<th style="width:130px;">Kode Lama (Alias)</th>'
        . '<th style="width:110px;">Kategori</th>'
        . '<th style="width:180px;">Nama Barang / Model</th>'
        . '<th style="width:140px;">Identifier (SN / MAC)</th>'
        . '<th>Spesifikasi Hasil AI (CPU / RAM / SSD / OS)</th>'
        . '<th style="width:95px;">Tgl Masuk</th>'
        . '<th style="width:120px;text-align:center;">Status</th>'
        . '</tr></thead><tbody>';

    $no = 0;
    foreach ($previewList as $item) {
        $no++;
        $isDup = !empty($item['is_duplicate']);
        $rowBg = $isDup ? 'background:#fff1f2;' : ($no % 2 === 0 ? 'background:#f8fafc;' : 'background:#fff;');

        $statusBadge = $isDup
            ? '<span class="badge danger" title="Sudah terdaftar sebagai unit ' . e($item['dup_asset_code']) . '">Duplikat (Lewati)</span>'
            : '<span class="badge ok">✓ Siap Disimpan</span>';

        $specParts = [];
        if (!empty($item['processor'])) $specParts[] = '<strong>CPU:</strong> ' . e($item['processor']);
        if (!empty($item['ram'])) $specParts[] = '<strong>RAM:</strong> ' . e($item['ram']);
        if (!empty($item['storage'])) $specParts[] = '<strong>Disk:</strong> ' . e($item['storage']);
        if (!empty($item['os'])) $specParts[] = '<strong>OS:</strong> ' . e($item['os']);
        if (!empty($item['vga'])) $specParts[] = '<strong>VGA:</strong> ' . e($item['vga']);
        $specText = $specParts ? implode(' &bull; ', $specParts) : '<span class="muted">-</span>';

        $idfParts = [];
        if (!empty($item['serial_number'])) $idfParts[] = 'SN: <code>' . e($item['serial_number']) . '</code>';
        if (!empty($item['mac_address'])) $idfParts[] = 'MAC: <code>' . e($item['mac_address']) . '</code>';
        $idfText = $idfParts ? implode('<br>', $idfParts) : '<span class="muted">-</span>';

        $aiBadge = '<div style="font-size:10px;color:#0284c7;margin-top:3px;">🤖 ' . e($item['ai_method']) . '</div>';

        echo '<tr style="' . $rowBg . '">'
            . '<td style="text-align:center;">' . $no . '</td>'
            . '<td><strong style="color:#0369a1;font-family:monospace;font-size:13px;">' . e($item['legacy_code']) . '</strong>' . $aiBadge . '</td>'
            . '<td><span class="badge">' . e($item['category_name']) . '</span></td>'
            . '<td><strong>' . e($item['asset_name'] ?: '-') . '</strong>' . (!empty($item['model']) && $item['model'] !== $item['asset_name'] ? ('<br><span class="muted">' . e($item['model']) . '</span>') : '') . '</td>'
            . '<td>' . $idfText . '</td>'
            . '<td>' . $specText . '<div class="muted" style="font-size:11px;margin-top:2px;">Teks Asli: ' . e(mb_strimwidth((string)$item['raw_description'], 0, 50, '...')) . '</div></td>'
            . '<td>' . e($item['installed_at'] ?: '-') . '</td>'
            . '<td style="text-align:center;">' . $statusBadge . '</td>'
            . '</tr>';
    }

    echo '</tbody></table></div>';

    echo '<div class="actions" style="margin-top:20px;display:flex;gap:12px;align-items:center;">'
        . '  <button type="submit" class="btn primary" style="padding:10px 24px;font-size:14px;font-weight:700;">💾 Konfirmasi & Simpan Unit Aset ke Database</button>'
        . '  <a class="btn" href="' . route_url('asset_legacy_import') . '">← Batal / Upload Ulang File</a>'
        . '</div>'
        . '</form>'
        . '</section>';
}
