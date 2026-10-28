<?php
declare(strict_types=1);

/**
 * ============================================================================
 * MODUL CORRECTIVE MAINTENANCE (PERBAIKAN & TIKET KERUSAKAN)
 * ============================================================================
 * File: app/modules/corrective.php
 * 
 * Mengelola penanganan insiden, kerusakan, dan servis unit aset:
 * 1. Manajemen Tiket (handle_route_tickets & handle_route_ticket_form):
 *    - Pembuatan tiket perbaikan via scan QR atau input kode unit aset.
 *    - Unggah / foto live kamera bukti kwitansi invoice & nota perbaikan.
 *    - Bukti visual sebelum perbaikan (before) dan sesudah perbaikan (after).
 * 2. Tindakan Servis & Master Bengkel (corrective_repairs & corrective_workshops):
 *    - Pencatatan perbaikan internal dan perbaikan pihak ketiga (eksternal/vendor).
 *    - Master Bengkel rekanan dan estimasi biaya perbaikan.
 * 3. Integrasi Mobile Field Service (app/modules/field_service.php):
 *    - Alur kerja teknisi di lapangan untuk update status pengerjaan secara real-time.
 */

function generate_next_ticket_code(PDO $pdo): string
{
    $prefix = 'TKT-' . date('Ym') . '-';
    $stmt = $pdo->prepare("SELECT ticket_code FROM corrective_tickets WHERE ticket_code LIKE ? ORDER BY ticket_code DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = (string)($stmt->fetchColumn() ?: '');
    $nextNum = 1;
    if ($last !== '') {
        $suffix = substr($last, strlen($prefix));
        $nextNum = max(1, (int)$suffix + 1);
    }
    return $prefix . str_pad((string)$nextNum, 5, '0', STR_PAD_LEFT);
}

function generate_next_walkaround_code(PDO $pdo): string
{
    $prefix = 'WKD-' . date('Ym') . '-';
    $stmt = $pdo->prepare("SELECT walkaround_code FROM asset_walkarounds WHERE walkaround_code LIKE ? ORDER BY walkaround_code DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = (string)($stmt->fetchColumn() ?: '');
    $nextNum = 1;
    if ($last !== '') {
        $suffix = substr($last, strlen($prefix));
        $nextNum = max(1, (int)$suffix + 1);
    }
    return $prefix . str_pad((string)$nextNum, 5, '0', STR_PAD_LEFT);
}

function ticket_status_badge(string $status): string
{
    return match (strtolower(trim($status))) {
        'open' => '<span class="badge" style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5">Open</span>',
        'assigned' => '<span class="badge" style="background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd">Assigned</span>',
        'in_progress' => '<span class="badge" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a">In Progress</span>',
        'pending_part' => '<span class="badge" style="background:#f3e8ff;color:#6b21a8;border:1px solid #e9d5ff">Waiting Part</span>',
        'resolved' => '<span class="badge ok">Resolved</span>',
        'closed' => '<span class="badge" style="background:#f1f5f9;color:#475569;border:1px solid #cbd5e1">Closed</span>',
        default => '<span class="badge">' . e($status) . '</span>',
    };
}

function ticket_priority_badge(string $priority): string
{
    return match (strtolower(trim($priority))) {
        'critical' => '<span class="badge danger" style="font-weight:700">CRITICAL</span>',
        'high' => '<span class="badge" style="background:#ffedd5;color:#c2410c;font-weight:600">High</span>',
        'medium' => '<span class="badge" style="background:#fef9c3;color:#854d0e">Medium</span>',
        'low' => '<span class="badge" style="background:#f1f5f9;color:#475569">Low</span>',
        default => '<span class="badge">' . e($priority) . '</span>',
    };
}

function ticket_category_badge(string $category): string
{
    return match (strtoupper(trim($category))) {
        'IT', 'COMPUTER' => '<span class="badge" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe">💻 IT Asset</span>',
        'VEHICLE', 'KENDARAAN' => '<span class="badge" style="background:#ecfdf5;color:#047857;border:1px solid #a7f3d0">🚗 Vehicle</span>',
        'FACILITY', 'FASILITAS' => '<span class="badge" style="background:#fffbeb;color:#b45309;border:1px solid #fde68a">🏢 Facility</span>',
        default => '<span class="badge">' . e($category) . '</span>',
    };
}

function get_maintenance_asset_unit(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $hasPcs = db_table_exists($pdo, 'pcs');
    $hasPrinters = db_table_exists($pdo, 'printers');
    $hasMai = db_table_exists($pdo, 'maintenance_asset_items');
    $hasGroups = db_table_exists($pdo, 'asset_groups');
    $hasTypes = db_table_exists($pdo, 'asset_types');
    $hasAssetItems = db_table_exists($pdo, 'asset_items');
    $hasJobDesks = db_table_exists($pdo, 'corrective_job_desks');

    $sql = "SELECT ma.*,
                   " . ($hasGroups ? "ag.group_code, ag.group_name," : "'' AS group_code, '' AS group_name,") . "
                   " . ($hasTypes ? "at.type_code, at.type_name," : "'' AS type_code, '' AS type_name,") . "
                   " . ($hasPcs ? "p.asset_item_id AS pc_asset_item_id," : "NULL AS pc_asset_item_id,") . "
                   " . ($hasPrinters ? "prn.asset_item_id AS prn_asset_item_id," : "NULL AS prn_asset_item_id,") . "
                   " . ($hasMai ? "mai.asset_item_id AS mai_asset_item_id," : "NULL AS mai_asset_item_id,") . "
                   " . ($hasAssetItems ? "ai.id AS direct_item_id, ai.asset_code AS item_asset_code, ai.asset_name AS item_asset_name, ai.asset_group_id AS item_group_id, ai.asset_type_id AS item_type_id, ai.asset_type AS item_asset_type, ai.asset_category AS item_asset_category, ai.custodian_name AS item_custodian_name, ai.custodian_nik AS item_custodian_nik" : "NULL AS direct_item_id, '' AS item_asset_code, '' AS item_asset_name, NULL AS item_group_id, NULL AS item_type_id, '' AS item_asset_type, '' AS item_asset_category, '' AS item_custodian_name, '' AS item_custodian_nik") . "
            FROM maintenance_assets ma
            " . ($hasGroups ? "LEFT JOIN asset_groups ag ON ag.id = ma.asset_group_id" : "") . "
            " . ($hasTypes ? "LEFT JOIN asset_types at ON at.id = ma.asset_type_id" : "") . "
            " . ($hasPcs ? "LEFT JOIN pcs p ON p.pc_id = ma.pc_id" : "") . "
            " . ($hasPrinters ? "LEFT JOIN printers prn ON prn.prn_id = ma.printer_id" : "") . "
            " . ($hasMai ? "LEFT JOIN maintenance_asset_items mai ON mai.maintenance_asset_id = ma.id AND mai.detached_at IS NULL" : "") . "
            " . ($hasAssetItems ? "LEFT JOIN asset_items ai ON ai.id = COALESCE(p.asset_item_id, prn.asset_item_id, mai.asset_item_id)" : "") . "
            WHERE ma.id = ?
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $assetItemId = (int)($row['direct_item_id'] ?? $row['pc_asset_item_id'] ?? $row['prn_asset_item_id'] ?? $row['mai_asset_item_id'] ?? 0);

    // Fallback pencarian asset_items jika belum ketemu dari join
    if ($hasAssetItems && $assetItemId <= 0) {
        $aiStmt = $pdo->prepare("SELECT id, asset_code, asset_name, asset_group_id, asset_type_id, asset_type, asset_category, custodian_name, custodian_nik 
                                 FROM asset_items 
                                 WHERE asset_code = ? OR (maintenance_asset_id IS NOT NULL AND maintenance_asset_id = ?) 
                                 LIMIT 1");
        $aiStmt->execute([$row['maintenance_asset_code'], $id]);
        $aiRow = $aiStmt->fetch(PDO::FETCH_ASSOC);
        if ($aiRow) {
            $assetItemId = (int)$aiRow['id'];
            $row['item_asset_code'] = $aiRow['asset_code'];
            $row['item_asset_name'] = $aiRow['asset_name'];
            $row['item_group_id'] = $aiRow['asset_group_id'];
            $row['item_type_id'] = $aiRow['asset_type_id'];
            $row['item_asset_type'] = $aiRow['asset_type'];
            $row['item_asset_category'] = $aiRow['asset_category'];
            if (!empty($aiRow['custodian_name'])) {
                $row['item_custodian_name'] = $aiRow['custodian_name'];
            }
            if (!empty($aiRow['custodian_nik'])) {
                $row['item_custodian_nik'] = $aiRow['custodian_nik'];
            }
        }
    }

    $row['asset_item_id'] = $assetItemId > 0 ? $assetItemId : null;

    // Utamakan Group dan Type dari asset_items jika terisi
    $groupId = (int)(($row['item_group_id'] ?? 0) ?: ($row['asset_group_id'] ?? 0));
    $typeId = (int)(($row['item_type_id'] ?? 0) ?: ($row['asset_type_id'] ?? 0));

    // Jika Type ID diketahui tetapi Group ID kosong, cari Group ID dari tabel asset_types
    if ($hasTypes && $typeId > 0 && $groupId <= 0) {
        $tStmt = $pdo->prepare("SELECT asset_group_id FROM asset_types WHERE id = ? LIMIT 1");
        $tStmt->execute([$typeId]);
        $gId = (int)($tStmt->fetchColumn() ?: 0);
        if ($gId > 0) {
            $groupId = $gId;
        }
    }

    // Resolusi cerdas jika Group / Type masih belum ketemu (berdasarkan kode aset / pattern / master category)
    $assetCodeToCheck = (string)($row['item_asset_code'] ?? $row['maintenance_asset_code'] ?? '');
    if ($hasGroups && $hasTypes && ($groupId <= 0 || $typeId <= 0)) {
        if (preg_match('/^([A-Z0-9]+)-([A-Z0-9]+)-/i', $assetCodeToCheck, $m)) {
            $gCode = strtoupper($m[1]);
            $tCode = strtoupper($m[2]);
            if ($groupId <= 0) {
                $gStmt = $pdo->prepare("SELECT id FROM asset_groups WHERE UPPER(group_code) = ? LIMIT 1");
                $gStmt->execute([$gCode]);
                $groupId = (int)($gStmt->fetchColumn() ?: 0);
            }
            if ($typeId <= 0 && $groupId > 0) {
                $tStmt = $pdo->prepare("SELECT id FROM asset_types WHERE asset_group_id = ? AND UPPER(type_code) = ? LIMIT 1");
                $tStmt->execute([$groupId, $tCode]);
                $typeId = (int)($tStmt->fetchColumn() ?: 0);
            }
        }
    }

    // Resolusi fallback berdasarkan tipe perangkat (PC / Printer / Kendaraan / Fasilitas)
    $cat = 'IT';
    $grp = strtoupper((string)($row['group_code'] ?? ''));
    $grpName = strtoupper((string)($row['group_name'] ?? ''));
    $mType = strtolower((string)($row['maintenance_type'] ?? ''));
    $itemCat = (string)($row['item_asset_category'] ?? '');

    if ($grp === 'VH' || str_contains($grpName, 'VEHICLE') || str_contains($mType, 'vehicle') || strcasecmp($itemCat, 'Vehicle') === 0) {
        $cat = 'Vehicle';
        if ($groupId <= 0 && $hasGroups) {
            $groupId = (int)$pdo->query("SELECT id FROM asset_groups WHERE group_code = 'VH' OR group_name LIKE '%VEHICLE%' ORDER BY id ASC LIMIT 1")->fetchColumn();
        }
    } elseif ($grp === 'FC' || str_contains($grpName, 'FACILITY') || str_contains($mType, 'facility') || strcasecmp($itemCat, 'Facility') === 0) {
        $cat = 'Facility';
        if ($groupId <= 0 && $hasGroups) {
            $groupId = (int)$pdo->query("SELECT id FROM asset_groups WHERE group_code = 'FC' OR group_name LIKE '%FACILITY%' ORDER BY id ASC LIMIT 1")->fetchColumn();
        }
    } else {
        $cat = 'IT';
        if ($groupId <= 0 && $hasGroups) {
            $groupId = (int)$pdo->query("SELECT id FROM asset_groups WHERE group_code = 'IT' OR group_name LIKE '%IT%' ORDER BY id ASC LIMIT 1")->fetchColumn();
        }
        if ($typeId <= 0 && $hasTypes && $groupId > 0) {
            if ($mType === 'printer' || !empty($row['printer_id']) || strcasecmp($itemCat, 'Printer') === 0) {
                $typeId = (int)$pdo->query("SELECT id FROM asset_types WHERE asset_group_id = {$groupId} AND (type_code = 'PRT' OR type_name LIKE '%Printer%') ORDER BY id ASC LIMIT 1")->fetchColumn();
            } else {
                $typeId = (int)$pdo->query("SELECT id FROM asset_types WHERE asset_group_id = {$groupId} AND (type_code = 'CMP' OR type_name LIKE '%Comp%') ORDER BY id ASC LIMIT 1")->fetchColumn();
            }
        }
    }

    $row['asset_group_id'] = $groupId > 0 ? $groupId : null;
    $row['asset_type_id'] = $typeId > 0 ? $typeId : null;

    if ($groupId > 0 && $hasGroups) {
        $gRow = $pdo->query("SELECT group_code, group_name FROM asset_groups WHERE id = {$groupId}")->fetch(PDO::FETCH_ASSOC);
        if ($gRow) {
            $row['group_code'] = $gRow['group_code'];
            $row['group_name'] = $gRow['group_name'];
        }
    }
    if ($typeId > 0 && $hasTypes) {
        $tRow = $pdo->query("SELECT type_code, type_name FROM asset_types WHERE id = {$typeId}")->fetch(PDO::FETCH_ASSOC);
        if ($tRow) {
            $row['type_code'] = $tRow['type_code'];
            $row['type_name'] = $tRow['type_name'];
        }
    }

    // Temukan Job Desk Corrective Maintenance yang paling cocok
    $row['job_desk_name'] = '';
    $row['job_desk_id'] = null;
    if ($hasJobDesks && $groupId > 0) {
        $jdStmt = $pdo->prepare("SELECT id, job_desk_name FROM corrective_job_desks 
                                 WHERE asset_group_id = ? AND (asset_type_id = ? OR asset_type_id IS NULL OR asset_type_id = 0) 
                                 ORDER BY CASE WHEN asset_type_id = ? THEN 0 ELSE 1 END, id ASC LIMIT 1");
        $jdStmt->execute([$groupId, $typeId ?: 0, $typeId ?: 0]);
        $jdRow = $jdStmt->fetch(PDO::FETCH_ASSOC);
        if ($jdRow) {
            $row['job_desk_id'] = (int)$jdRow['id'];
            $row['job_desk_name'] = $jdRow['job_desk_name'];
        }
    }

    // Sinkronkan ke maintenance_assets jika perlu
    if ($groupId > 0 || $typeId > 0) {
        try {
            $upd = $pdo->prepare("UPDATE maintenance_assets SET asset_group_id = ?, asset_type_id = ? WHERE id = ? AND (asset_group_id IS NULL OR asset_type_id IS NULL OR asset_group_id <> ? OR asset_type_id <> ?)");
            $upd->execute([$groupId ?: null, $typeId ?: null, $id, $groupId ?: 0, $typeId ?: 0]);
        } catch (Throwable $ignored) {}
    }

    $row['asset_category'] = $cat;
    $row['custodian_name'] = $row['item_custodian_name'] ?: ($row['owner_name'] ?? '');
    $row['custodian_nik'] = $row['item_custodian_nik'] ?: ($row['employee_nik'] ?? '');

    return $row;
}

function resolve_maintenance_asset_id_from_raw_input(PDO $pdo, string $rawInput): ?int
{
    $raw = trim($rawInput);
    if ($raw === '') {
        return null;
    }

    // 0. Direct Integer ID check
    if (ctype_digit($raw) && (int)$raw > 0) {
        $stmt = $pdo->prepare("SELECT id FROM maintenance_assets WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$raw]);
        $id = (int)($stmt->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }
    }

    $candidateCodes = [];
    $rawClean = urldecode($raw);
    $candidateCodes[] = $raw;
    $candidateCodes[] = $rawClean;

    // 1. Ekstrak query parameters jika raw input adalah URL atau memiliki query string
    $extractedTokens = [];
    if (preg_match('/[?&]eqr=([^&#\s]+)/i', $rawClean, $m)) {
        $extractedTokens[] = urldecode($m[1]);
    }
    if (preg_match('/[?&]code=([^&#\s]+)/i', $rawClean, $m)) {
        $candidateCodes[] = urldecode($m[1]);
    }
    if (str_contains($rawClean, '?') || str_starts_with($rawClean, 'http://') || str_starts_with($rawClean, 'https://')) {
        $parsed = parse_url($rawClean);
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $queryParams);
            if (!empty($queryParams['eqr'])) {
                $extractedTokens[] = (string)$queryParams['eqr'];
            }
            if (!empty($queryParams['code'])) {
                $candidateCodes[] = (string)$queryParams['code'];
            }
        }
    }

    // 2. Dekripsi token terenkripsi jika ditemukan (atau jika string input itu sendiri adalah token)
    if (function_exists('qr_decrypt_payload')) {
        foreach ($extractedTokens as $token) {
            $dec = qr_decrypt_payload($token);
            if (!empty($dec)) {
                $candidateCodes[] = trim($dec);
            }
        }
        // Coba juga dekripsi langsung raw string jika token terenkripsi di-scan mentah
        $dec = qr_decrypt_payload($raw);
        if (!empty($dec)) {
            $candidateCodes[] = trim($dec);
        }
        $dec2 = qr_decrypt_payload($rawClean);
        if (!empty($dec2)) {
            $candidateCodes[] = trim($dec2);
        }
    }

    // 3. Ekstrak variasi kode (strip security code, parse prefix, regex)
    $extraCodes = [];
    foreach ($candidateCodes as $c) {
        $c = trim($c);
        if ($c === '') {
            continue;
        }

        // Coba via helper bawaan find_asset_by_code
        if (function_exists('find_asset_by_code')) {
            [$assetType, $assetId, $assetInfo] = find_asset_by_code($pdo, $c);
            if (!empty($assetInfo['maintenance_asset_id'])) {
                return (int)$assetInfo['maintenance_asset_id'];
            }
            if ($assetType === 'maintenance_asset' && !empty($assetInfo['id'])) {
                return (int)$assetInfo['id'];
            }
            if ($assetId !== '') {
                $extraCodes[] = $assetId;
            }
        }

        // Parse format CODE-SECURITY_CODE
        if (function_exists('parse_asset_code')) {
            [$base, $sec] = parse_asset_code($c);
            if ($base !== '') {
                $extraCodes[] = $base;
            }
        }

        // Strip akhiran -XXXX jika ada
        if (preg_match('/^(.+?)-[A-Z0-9]{2,12}$/i', $c, $m)) {
            $extraCodes[] = $m[1];
        }

        // Regex pola MNT-XXXX
        if (preg_match_all('/MNT-[A-Z0-9_\-]+/i', $c, $matches)) {
            foreach ($matches[0] as $m) {
                $extraCodes[] = $m;
                if (preg_match('/^(.+?)-[A-Z0-9]{2,12}$/i', $m, $subm)) {
                    $extraCodes[] = $subm[1];
                }
            }
        }

        // Regex pola PCXXXX atau PRNXXXX
        if (preg_match_all('/(PC\d{4,10}|PRN\d{4,10})/i', $c, $matches)) {
            foreach ($matches[0] as $m) {
                $extraCodes[] = $m;
            }
        }
    }

    $allCandidates = array_values(array_unique(array_filter(array_merge($candidateCodes, $extraCodes))));

    // 4. Cari di database maintenance_assets untuk setiap candidate code
    $hasPcs = db_table_exists($pdo, 'pcs');
    $hasPrinters = db_table_exists($pdo, 'printers');
    $hasAssetItems = db_table_exists($pdo, 'asset_items');
    $hasMaintItems = db_table_exists($pdo, 'maintenance_asset_items');

    foreach ($allCandidates as $code) {
        $code = trim($code);
        if ($code === '' || str_starts_with($code, 'http://') || str_starts_with($code, 'https://')) {
            continue;
        }

        // a. Exact match maintenance_asset_code
        $stmt = $pdo->prepare("SELECT id FROM maintenance_assets WHERE maintenance_asset_code = ? LIMIT 1");
        $stmt->execute([$code]);
        $id = (int)($stmt->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }

        // b. Exact match pc_id
        $stmt = $pdo->prepare("SELECT id FROM maintenance_assets WHERE pc_id = ? LIMIT 1");
        $stmt->execute([$code]);
        $id = (int)($stmt->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }

        // c. Exact match printer_id
        $stmt = $pdo->prepare("SELECT id FROM maintenance_assets WHERE printer_id = ? LIMIT 1");
        $stmt->execute([$code]);
        $id = (int)($stmt->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }

        // d. Match via pcs table (maintenance_asset_id)
        if ($hasPcs) {
            $stmt = $pdo->prepare("SELECT maintenance_asset_id FROM pcs WHERE pc_id = ? AND maintenance_asset_id IS NOT NULL LIMIT 1");
            $stmt->execute([$code]);
            $id = (int)($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }
        }

        // e. Match via printers table (maintenance_asset_id)
        if ($hasPrinters) {
            $stmt = $pdo->prepare("SELECT maintenance_asset_id FROM printers WHERE prn_id = ? AND maintenance_asset_id IS NOT NULL LIMIT 1");
            $stmt->execute([$code]);
            $id = (int)($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }
        }

        // f. Match via maintenance_asset_items & asset_items
        if ($hasMaintItems && $hasAssetItems) {
            $stmt = $pdo->prepare("SELECT mai.maintenance_asset_id FROM maintenance_asset_items mai
                                   JOIN asset_items ai ON ai.id = mai.asset_item_id
                                   WHERE ai.asset_code = ? AND mai.detached_at IS NULL LIMIT 1");
            $stmt->execute([$code]);
            $id = (int)($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }
        }

        // g. Match via asset_items linked to pcs
        if ($hasAssetItems && $hasPcs) {
            $stmt = $pdo->prepare("SELECT ma.id FROM maintenance_assets ma
                                   JOIN pcs p ON p.pc_id = ma.pc_id
                                   JOIN asset_items ai ON ai.id = p.asset_item_id
                                   WHERE ai.asset_code = ? LIMIT 1");
            $stmt->execute([$code]);
            $id = (int)($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }
        }

        // h. Match via asset_items directly (auto-link / auto-create maintenance_asset if needed)
        if ($hasAssetItems) {
            $stmt = $pdo->prepare("SELECT id, asset_code, asset_name, asset_group_id, asset_type_id, asset_category, location_label, custodian_name, custodian_nik FROM asset_items WHERE asset_code = ? OR alias_code = ? LIMIT 1");
            $stmt->execute([$code, $code]);
            $aiRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($aiRow) {
                // Periksa apakah sudah ada maintenance_asset yang terhubung via asset_item_id atau kode
                $stmtMa = $pdo->prepare("SELECT id FROM maintenance_assets WHERE asset_item_id = ? OR maintenance_asset_code = ? LIMIT 1");
                $stmtMa->execute([$aiRow['id'], $aiRow['asset_code']]);
                $maId = (int)($stmtMa->fetchColumn() ?: 0);
                if ($maId > 0) {
                    $pdo->prepare("UPDATE maintenance_assets SET asset_item_id = ? WHERE id = ?")->execute([$aiRow['id'], $maId]);
                    return $maId;
                }
                // Buat maintenance_asset baru secara otomatis untuk asset_item ini
                $secCode = strtoupper(substr(md5(uniqid('', true)), 0, 6));
                $mType = 'other';
                if (stripos((string)$aiRow['asset_category'], 'comp') !== false || stripos((string)$aiRow['asset_code'], '-CMP-') !== false || stripos((string)$aiRow['asset_code'], '-NBK-') !== false) {
                    $mType = 'pc_set';
                } elseif (stripos((string)$aiRow['asset_category'], 'print') !== false || stripos((string)$aiRow['asset_code'], '-PRT-') !== false) {
                    $mType = 'printer';
                } elseif (stripos((string)$aiRow['asset_category'], 'vehic') !== false) {
                    $mType = 'vehicle';
                } elseif (stripos((string)$aiRow['asset_category'], 'facil') !== false) {
                    $mType = 'facility';
                }
                $insMa = $pdo->prepare("INSERT INTO maintenance_assets (
                    maintenance_asset_code, security_code, maintenance_type, name, asset_group_id, asset_type_id, asset_item_id, location_label, owner_name, employee_nik, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                $insMa->execute([
                    $aiRow['asset_code'],
                    $secCode,
                    $mType,
                    $aiRow['asset_name'] ?: $aiRow['asset_code'],
                    $aiRow['asset_group_id'] ?: null,
                    $aiRow['asset_type_id'] ?: null,
                    $aiRow['id'],
                    $aiRow['location_label'] ?: null,
                    $aiRow['custodian_name'] ?: null,
                    $aiRow['custodian_nik'] ?: null,
                ]);
                $newMaId = (int)$pdo->lastInsertId();
                if ($newMaId > 0) {
                    if (db_column_exists($pdo, 'asset_items', 'maintenance_asset_id')) {
                        $pdo->prepare("UPDATE asset_items SET maintenance_asset_id = ? WHERE id = ?")->execute([$newMaId, (int)$aiRow['id']]);
                    }
                    if ($hasMaintItems) {
                        $pdo->prepare("INSERT INTO maintenance_asset_items (maintenance_asset_id, asset_item_id, role_name, attached_at) VALUES (?, ?, 'Primary Asset', CURDATE())")
                            ->execute([$newMaId, (int)$aiRow['id']]);
                    }
                    return $newMaId;
                }
            }
        }

        // i. Match prefix maintenance_asset_code LIKE 'code%'
        $stmt = $pdo->prepare("SELECT id FROM maintenance_assets WHERE maintenance_asset_code LIKE ? OR pc_id LIKE ? LIMIT 1");
        $stmt->execute([$code . '%', $code . '%']);
        $id = (int)($stmt->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }

        // i. Fallback LIKE name
        if (strlen($code) >= 4) {
            $stmt = $pdo->prepare("SELECT id FROM maintenance_assets WHERE name LIKE ? LIMIT 1");
            $stmt->execute(['%' . $code . '%']);
            $id = (int)($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }
        }
    }

    return null;
}

// -----------------------------------------------------------------------------
// WEB INTERFACE: TICKET MANAGEMENT (DESKTOP)
// -----------------------------------------------------------------------------

/**
 * Modul: Daftar Tiket
 * Proses: Menampilkan seluruh tiket permintaan perbaikan (Helpdesk/Troubleshooting) beserta statusnya.
 */
function handle_route_tickets(PDO $pdo): void
{
    $user = require_regulation('tickets', 'view');
    ensure_corrective_maintenance_schema($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_ticket') {
        require_regulation('tickets', 'delete');
        $delTicketId = (int)($_POST['ticket_id'] ?? 0);
        if ($delTicketId > 0) {
            try {
                $pdo->beginTransaction();
                // Hapus sparepart & riwayat perbaikan
                $repStmt = $pdo->prepare("SELECT id FROM corrective_repairs WHERE ticket_id = ?");
                $repStmt->execute([$delTicketId]);
                $repIds = $repStmt->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($repIds)) {
                    $inR = implode(',', array_map('intval', $repIds));
                    $pdo->exec("DELETE FROM corrective_repair_parts WHERE repair_id IN ($inR)");
                    $pdo->prepare("DELETE FROM corrective_repairs WHERE ticket_id = ?")->execute([$delTicketId]);
                }
                $pdo->prepare("DELETE FROM corrective_tickets WHERE id = ?")->execute([$delTicketId]);
                $pdo->commit();
                flash('Tiket corrective maintenance berhasil dihapus.');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                flash('Gagal menghapus tiket: ' . $e->getMessage(), 'err');
            }
        }
        redirect_to('tickets');
    }

    $catFilter = trim((string)($_GET['category'] ?? ''));
    $statusFilter = trim((string)($_GET['status'] ?? ''));
    $q = trim((string)($_GET['q'] ?? ''));

    $commodityGroups = [];
    if (db_table_exists($pdo, 'asset_groups')) {
        $commodityGroups = $pdo->query("SELECT id, group_code, group_name FROM asset_groups WHERE is_active = 1 ORDER BY group_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    $where = [];
    $params = [];

    if ($catFilter !== '') {
        if (ctype_digit($catFilter)) {
            $where[] = 'COALESCE(ai.asset_group_id, ma.asset_group_id) = ?';
            $params[] = (int)$catFilter;
        } else {
            $where[] = '(ag.group_code = ? OR t.asset_category = ?)';
            $params[] = $catFilter;
            $params[] = $catFilter;
        }
    }
    if ($statusFilter !== '') {
        $where[] = 't.status = ?';
        $params[] = $statusFilter;
    }
    if ($q !== '') {
        $where[] = '(t.ticket_code LIKE ? OR t.subject LIKE ? OR t.reporter_name LIKE ? OR t.location_label LIKE ? OR ai.asset_code LIKE ? OR (ai.alias_code IS NOT NULL AND ai.alias_code LIKE ?) OR ai.asset_name LIKE ? OR ma.maintenance_asset_code LIKE ? OR ma.name LIKE ? OR t.pc_id LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like);
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT t.*, 
                   COALESCE(ai.asset_code, ma.maintenance_asset_code, t.pc_id) AS maintenance_asset_code,
                   ai.alias_code AS item_alias_code,
                   COALESCE(ai.asset_name, ma.name, t.pc_id) AS maintenance_asset_name,
                   ag.group_code AS commodity_code,
                   ag.group_name AS commodity_name,
                   c.company_name,
                   u.name AS handled_user_name,
                   w.workshop_name AS handled_workshop_name,
                   (SELECT COUNT(*) FROM corrective_repairs cr WHERE cr.ticket_id = t.id) AS repair_count,
                   (SELECT COALESCE(SUM(cr.repair_cost), 0) FROM corrective_repairs cr WHERE cr.ticket_id = t.id) AS total_repair_cost,
                   (SELECT COALESCE(SUM(crp.part_cost), 0) FROM corrective_repairs cr JOIN corrective_repair_parts crp ON crp.repair_id = cr.id WHERE cr.ticket_id = t.id) AS total_part_cost
            FROM corrective_tickets t
            LEFT JOIN asset_items ai ON ai.id = t.asset_item_id
            LEFT JOIN maintenance_assets ma ON ma.id = t.maintenance_asset_id
            LEFT JOIN asset_groups ag ON ag.id = COALESCE(ai.asset_group_id, ma.asset_group_id)
            LEFT JOIN asset_companies c ON c.id = t.company_id
            LEFT JOIN users u ON u.id = t.handled_by_user_id
            LEFT JOIN corrective_workshops w ON w.id = t.handled_by_workshop_id
            $whereSql
            ORDER BY FIELD(t.status, 'open', 'assigned', 'in_progress', 'pending_part', 'resolved', 'closed'), t.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stats
    $stats = $pdo->query("SELECT 
        COUNT(CASE WHEN status IN ('open', 'assigned') THEN 1 END) AS count_open,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) AS count_progress,
        COUNT(CASE WHEN status = 'pending_part' THEN 1 END) AS count_pending_part,
        COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) AS count_resolved,
        COUNT(*) AS count_total
    FROM corrective_tickets")->fetch(PDO::FETCH_ASSOC);

    $renderCmCard = function($number, $label, $iconPath, $themeIdx) {
        $themes = [
            ['#eff6ff', '#1e3a8a', '#3b82f6'], // 0: blue
            ['#fef2f2', '#7f1d1d', '#ef4444'], // 1: red
            ['#fffbeb', '#78350f', '#f59e0b'], // 2: yellow/amber
            ['#f3e8ff', '#581c87', '#a855f7'], // 3: purple
            ['#f0fdf4', '#14532d', '#22c55e'], // 4: green
        ];
        $t = $themes[$themeIdx % count($themes)];
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="' . $t[2] . '"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="' . $iconPath . '"/></svg>';
        $bgImage = "data:image/svg+xml;base64," . base64_encode($svg);

        return '<div class="stat" style="background-color: ' . $t[0] . '; color: ' . $t[1] . '; border-left: 5px solid ' . $t[2] . '; position: relative; overflow: hidden; padding: 16px 18px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 0; min-width: 0; flex: 1 1 0;">'
             . '<div style="position: absolute; right: -15px; top: -15px; width: 110px; height: 110px; opacity: 0.15; background-image: url(\'' . $bgImage . '\'); background-size: contain; background-repeat: no-repeat; transform: rotate(-10deg);"></div>'
             . '<div style="position: relative; z-index: 1;">'
             . '<strong style="font-size: 30px; display: block; margin-bottom: 2px; line-height: 1; color: ' . $t[1] . ';">' . e($number) . '</strong>'
             . '<span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; opacity: 0.85; color: ' . $t[1] . ';">' . e($label) . '</span>'
             . '</div></div>';
    };

    render_header('Corrective Maintenance & Ticketing', $user);
    ?>
    <section class="panel">
        <div class="split" style="align-items:flex-start; gap:16px;">
            <div>
                <h1 style="margin:0; font-size:22px;">Tiket & Troubleshooting (Corrective Maintenance)</h1>
                <p class="muted" style="margin:4px 0 0;">Kelola pelaporan masalah, penanganan perbaikan (Internal / Workshop), dan riwayat maintenance unit aset.</p>
            </div>
            <div class="actions" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <?php if (has_regulation('tickets', 'create')): ?>
                    <a class="btn primary" href="<?= route_url('ticket_form') ?>">+ Buat Tiket Baru</a>
                <?php endif; ?>
                <a class="btn good" href="<?= route_url('mobile_service') ?>" target="_blank">📱 Mobile Service</a>
                <a class="btn" href="<?= route_url('corrective_repairs') ?>">Riwayat Reparasi & Part</a>
            </div>
        </div>
    </section>

    <div style="margin-bottom: 18px;">
        <section style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px;">
            <?= $renderCmCard((int)($stats['count_total'] ?? 0), 'Total Tiket', 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z', 0) ?>
            <?= $renderCmCard((int)($stats['count_open'] ?? 0), 'Tiket Baru / Terbuka', 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 1) ?>
            <?= $renderCmCard((int)($stats['count_progress'] ?? 0), 'Sedang Dikerjakan', 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z', 2) ?>
            <?= $renderCmCard((int)($stats['count_pending_part'] ?? 0), 'Menunggu Sparepart', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 3) ?>
            <?= $renderCmCard((int)($stats['count_resolved'] ?? 0), 'Selesai / Ditutup', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 4) ?>
        </section>
    </div>

    <section class="panel">
        <form method="get" class="grid four" style="margin-bottom:16px;">
            <input type="hidden" name="route" value="tickets">
            <label>Cari Tiket / Aset / Pelapor
                <input name="q" value="<?= e($q) ?>" placeholder="Kode tiket, kode aset, nama pelapor...">
            </label>
            <label>Komoditas
                <select name="category">
                    <option value="">Semua Komoditas</option>
                    <?php if (!empty($commodityGroups)): ?>
                        <?php foreach ($commodityGroups as $cg): ?>
                            <option value="<?= (int)$cg['id'] ?>" <?= $catFilter === (string)$cg['id'] || strcasecmp($catFilter, (string)$cg['group_code']) === 0 ? 'selected' : '' ?>><?= e($cg['group_code'] . ' - ' . $cg['group_name']) ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="IT" <?= $catFilter === 'IT' ? 'selected' : '' ?>>IT Asset</option>
                        <option value="Vehicle" <?= $catFilter === 'Vehicle' ? 'selected' : '' ?>>Vehicle (Kendaraan)</option>
                        <option value="Facility" <?= $catFilter === 'Facility' ? 'selected' : '' ?>>Facility (Gedung/Fasilitas)</option>
                    <?php endif; ?>
                </select>
            </label>
            <label>Status Tiket
                <select name="status">
                    <option value="">Semua Status</option>
                    <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="pending_part" <?= $statusFilter === 'pending_part' ? 'selected' : '' ?>>Waiting Part</option>
                    <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                    <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </label>
            <div style="display:flex;align-items:flex-end;gap:8px;">
                <button class="btn primary" style="height:42px;">Filter</button>
                <a class="btn" href="<?= route_url('tickets') ?>" style="height:42px;line-height:22px;">Reset</a>
            </div>
        </form>

        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tiket & Tanggal</th>
                    <th>Komoditas & Prioritas</th>
                    <th>Unit Aset</th>
                    <th>Kode Lama (Legacy)</th>
                    <th>Kendala / Subjek</th>
                    <th>Pelapor & Lokasi</th>
                    <th>Status & Penanganan</th>
                    <th>Total Biaya</th>
                    <th style="width:90px; text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$tickets): ?>
                    <tr><td colspan="8" class="muted" style="text-align:center;padding:24px;">Belum ada tiket corrective maintenance.</td></tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <?php
                        $assetLabel = '-';
                        if (!empty($t['maintenance_asset_code'])) {
                            $aliasBadge = !empty($t['item_alias_code']) ? '<br><span style="display:inline-block;padding:1px 6px;border-radius:4px;background:#e0f2fe;color:#0284c7;font-size:11px;font-weight:700;margin-top:2px;">Lama: ' . e($t['item_alias_code']) . '</span>' : '';
                            $assetLabel = '<strong>' . e($t['maintenance_asset_code']) . '</strong>' . $aliasBadge . '<br><small class="muted">' . e(mb_strimwidth((string)$t['maintenance_asset_name'], 0, 35, '...')) . '</small>';
                        } elseif (!empty($t['pc_id'])) {
                            $assetLabel = 'PC: <strong>' . e($t['pc_id']) . '</strong>';
                        }
                        $totalCost = (float)$t['total_repair_cost'] + (float)$t['total_part_cost'];

                        $handledDisplay = 'Belum Ditentukan';
                        if ($t['handled_by_type'] === 'workshop' && !empty($t['handled_workshop_name'])) {
                            $handledDisplay = '🏢 Bengkel: <strong>' . e($t['handled_workshop_name']) . '</strong>';
                        } elseif (!empty($t['handled_user_name'])) {
                            $handledDisplay = '👨‍🔧 Internal: <strong>' . e($t['handled_user_name']) . '</strong>';
                        } elseif (!empty($t['assigned_technician_name'])) {
                            $handledDisplay = '👨‍🔧 Internal: <strong>' . e($t['assigned_technician_name']) . '</strong>';
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?= e($t['ticket_code']) ?></strong><br>
                                <span class="muted" style="font-size:12px;"><?= e(date('d M Y H:i', strtotime($t['created_at']))) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($t['commodity_name'])): ?>
                                    <span class="badge" style="background:#e0f2fe;color:#0369a1;"><?= e(($t['commodity_code'] ? $t['commodity_code'] . ' - ' : '') . $t['commodity_name']) ?></span><br>
                                <?php else: ?>
                                    <?= ticket_category_badge($t['asset_category']) ?><br>
                                <?php endif; ?>
                                <div style="margin-top:4px;"><?= ticket_priority_badge($t['priority']) ?></div>
                            </td>
                            <td><?= $assetLabel ?></td>
                            <td><?= !empty($t['item_alias_code']) ? '<span style="display:inline-block;padding:2px 7px;border-radius:4px;background:#fef3c7;color:#92400e;font-size:12px;font-weight:700;border:1px solid #fde68a;font-family:monospace;">' . e($t['item_alias_code']) . '</span>' : '<span class="muted">-</span>' ?></td>
                            <td>
                                <strong><?= e($t['subject']) ?></strong><br>
                                <span class="muted" style="font-size:12px;"><?= e(mb_strimwidth((string)$t['description'], 0, 60, '...')) ?></span>
                            </td>
                            <td>
                                <strong><?= e($t['reporter_name']) ?></strong> <?= $t['reporter_nik'] ? '<span class="muted">(' . e($t['reporter_nik']) . ')</span>' : '' ?><br>
                                <span class="muted" style="font-size:12px;"><?= e($t['location_label'] ?: '-') ?></span>
                            </td>
                            <td>
                                <?= ticket_status_badge($t['status']) ?><br>
                                <small class="muted" style="display:block; margin-top:3px;"><?= $handledDisplay ?></small>
                            </td>
                            <td>
                                <?php if ($totalCost > 0): ?>
                                    <span style="font-weight:600;color:#0f766e;">Rp <?= number_format($totalCost, 0, ',', '.') ?></span><br>
                                    <small class="muted"><?= (int)$t['repair_count'] ?> tindakan</small>
                                <?php else: ?>
                                    <span class="muted">Rp 0</span>
                                <?php endif; ?>
                                <?php if (!empty($t['invoice_photo'])): ?>
                                    <br><a href="<?= e(url($t['invoice_photo'])) ?>" target="_blank" style="font-size:11px; text-decoration:none; color:#0284c7; font-weight:600; display:inline-flex; align-items:center; gap:3px; margin-top:3px;">🧾 Kwitansi</a>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <a href="<?= route_url('ticket_detail', ['id' => $t['id']]) ?>" title="Detail Tiket & Penanganan" style="display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; border-radius:6px; text-decoration:none; vertical-align:middle; margin-right:4px;"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a>
                                <?php if (has_regulation('tickets', 'delete')): ?>
                                    <form method="post" action="<?= route_url('tickets') ?>" style="display:inline;" onsubmit="return confirm('Batalkan tiket <?= e($t['ticket_code']) ?> beserta seluruh data perbaikan terkait?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_ticket">
                                        <input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
                                        <button type="submit" title="Hapus Tiket" style="display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; background:#fef2f2; color:#ef4444; border:1px solid #fecaca; border-radius:6px; cursor:pointer; vertical-align:middle;"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>
    </section>
    <?php
    render_footer();
}

/**
 * Helper: Simpan foto/scan kwitansi atau invoice (dari file upload maupun live camera base64)
 */
function save_corrective_invoice_photo(?array $file, ?string $base64): ?string
{
    $uploadDir = __DIR__ . '/../../public/uploads/invoices';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    // 1. Direct file upload (Image / PDF)
    if (!empty($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
        $origName = (string)($file['name'] ?? '');
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        if (in_array($ext, $allowedExts, true)) {
            $filename = 'invoice_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $targetPath = $uploadDir . '/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                if ($ext !== 'pdf' && function_exists('compress_image_to_limit')) {
                    @compress_image_to_limit($targetPath, 1500000);
                }
                return 'uploads/invoices/' . $filename;
            }
        }
    }

    // 2. Base64 snapshot dari live kamera
    if (!empty($base64) && is_string($base64)) {
        $base64 = trim($base64);
        if (preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/i', $base64, $matches)) {
            $ext = strtolower($matches[1]);
            if ($ext === 'jpeg') { $ext = 'jpg'; }
            $data = base64_decode($matches[2]);
            if ($data !== false && strlen($data) > 0) {
                $filename = 'invoice_cam_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetPath = $uploadDir . '/' . $filename;
                if (file_put_contents($targetPath, $data) !== false) {
                    if (function_exists('compress_image_to_limit')) {
                        @compress_image_to_limit($targetPath, 1500000);
                    }
                    return 'uploads/invoices/' . $filename;
                }
            }
        }
    }

    return null;
}

/**
 * Render modal dan JavaScript untuk fitur live kamera & preview dokumen kwitansi/invoice
 */
function render_invoice_camera_modal_and_script(): void
{
    static $rendered = false;
    if ($rendered) return;
    $rendered = true;
    ?>
    <!-- Modal Live Kamera untuk Kwitansi / Invoice -->
    <div id="invoiceCameraModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.85); z-index:99999; align-items:center; justify-content:center; padding:16px;">
        <div style="background:#fff; border-radius:12px; width:100%; max-width:520px; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); display:flex; flex-direction:column; max-height:92vh;">
            <div style="background:#0f172a; color:#fff; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                <div style="font-weight:600; font-size:15px; display:flex; align-items:center; gap:8px;">
                    <span>📷</span> Potret Kwitansi / Invoice Fisik
                </div>
                <button type="button" onclick="closeInvoiceCameraModal()" style="background:none; border:none; color:#cbd5e1; font-size:22px; cursor:pointer; line-height:1; padding:0 4px;" title="Tutup">&times;</button>
            </div>
            
            <div style="padding:14px; background:#000; display:flex; flex-direction:column; align-items:center; justify-content:center; position:relative; min-height:300px; flex:1;">
                <video id="invoiceCamVideo" autoplay playsinline muted style="width:100%; max-height:55vh; border-radius:6px; object-fit:contain;"></video>
                <img id="invoiceCamSnapshot" alt="Hasil Foto" style="display:none; width:100%; max-height:55vh; border-radius:6px; object-fit:contain;">
                <canvas id="invoiceCamCanvas" style="display:none;"></canvas>
                <div id="invoiceCamMsg" style="position:absolute; bottom:12px; left:16px; right:16px; text-align:center; background:rgba(0,0,0,0.65); color:#f8fafc; font-size:12px; padding:6px 10px; border-radius:20px;">
                    Posisikan kwitansi / invoice agar teks dan nominal terbaca jelas
                </div>
            </div>
            
            <div style="padding:12px 16px; background:#f8fafc; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <div id="invoiceCamLiveControls" style="display:flex; gap:8px; width:100%; justify-content:space-between; align-items:center;">
                    <button type="button" class="btn secondary" onclick="switchInvoiceCamera()" id="btnSwitchCam" style="font-size:13px; padding:7px 12px; display:inline-flex; align-items:center; gap:5px;">
                        🔄 Ganti Kamera
                    </button>
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="btn" onclick="closeInvoiceCameraModal()" style="font-size:13px; padding:7px 14px;">Batal</button>
                        <button type="button" class="btn primary" onclick="takeInvoiceSnapshot()" style="font-size:13px; padding:7px 18px; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
                            📸 Potret Foto
                        </button>
                    </div>
                </div>
                
                <div id="invoiceCamSnapControls" style="display:none; gap:8px; width:100%; justify-content:space-between; align-items:center;">
                    <button type="button" class="btn secondary" onclick="retakeInvoiceSnapshot()" style="font-size:13px; padding:7px 14px;">
                        🔄 Ulangi Foto
                    </button>
                    <button type="button" class="btn primary" onclick="confirmInvoiceSnapshot()" style="font-size:13px; padding:7px 18px; font-weight:600; background:#16a34a; border-color:#16a34a;">
                        ✔️ Gunakan Foto Ini
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Lightbox Viewer untuk Foto Kwitansi / Invoice -->
    <div id="invoiceImageViewerModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.85); z-index:999999; align-items:center; justify-content:center; padding:16px;" onclick="closeInvoiceImageViewer()">
        <div style="position:relative; max-width:92vw; max-height:92vh; text-align:center;" onclick="event.stopPropagation()">
            <button type="button" onclick="closeInvoiceImageViewer()" style="position:absolute; top:-38px; right:0; background:none; border:none; color:#fff; font-size:30px; cursor:pointer; line-height:1;" title="Tutup">&times;</button>
            <img id="invoiceViewerImg" src="" alt="Bukti Kwitansi" style="max-width:92vw; max-height:85vh; border-radius:6px; box-shadow:0 10px 30px rgba(0,0,0,0.6); object-fit:contain; background:#fff;">
            <div style="margin-top:10px;">
                <a id="invoiceViewerDownload" href="" target="_blank" download class="btn" style="background:#fff; color:#0f172a; font-size:12px; padding:6px 14px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                    ⬇️ Buka / Unduh Ukuran Asli
                </a>
            </div>
        </div>
    </div>

    <script>
    var invoiceStream = null;
    var invoiceCurrentFacing = 'environment';
    var invoiceCapturedDataUrl = null;
    var currentInvoiceTargetPrefix = '';

    function openInvoiceCameraModal(prefix) {
        currentInvoiceTargetPrefix = prefix || '';
        var modal = document.getElementById('invoiceCameraModal');
        if (!modal) return;
        modal.style.display = 'flex';
        resetInvoiceCamState();
        startInvoiceCameraStream(invoiceCurrentFacing);
    }

    function closeInvoiceCameraModal() {
        stopInvoiceCameraStream();
        var modal = document.getElementById('invoiceCameraModal');
        if (modal) modal.style.display = 'none';
        resetInvoiceCamState();
    }

    function resetInvoiceCamState() {
        invoiceCapturedDataUrl = null;
        var video = document.getElementById('invoiceCamVideo');
        var snap = document.getElementById('invoiceCamSnapshot');
        var liveCtrls = document.getElementById('invoiceCamLiveControls');
        var snapCtrls = document.getElementById('invoiceCamSnapControls');
        var msg = document.getElementById('invoiceCamMsg');
        
        if (video) video.style.display = 'block';
        if (snap) { snap.style.display = 'none'; snap.src = ''; }
        if (liveCtrls) liveCtrls.style.display = 'flex';
        if (snapCtrls) snapCtrls.style.display = 'none';
        if (msg) msg.textContent = 'Posisikan kwitansi / invoice agar teks dan nominal terbaca jelas';
    }

    async function startInvoiceCameraStream(facing) {
        stopInvoiceCameraStream();
        var video = document.getElementById('invoiceCamVideo');
        var msg = document.getElementById('invoiceCamMsg');
        
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('Fitur akses kamera langsung tidak didukung atau dibatasi pada browser ini. Silakan gunakan tombol "Pilih File / Scan".');
            closeInvoiceCameraModal();
            var inputId = currentInvoiceTargetPrefix ? currentInvoiceTargetPrefix + 'FileInput' : 'invoiceFileInput';
            var fileInput = document.getElementById(inputId);
            if (fileInput) fileInput.click();
            return;
        }

        try {
            var constraints = {
                video: {
                    facingMode: { ideal: facing },
                    width: { ideal: 1920, min: 640 },
                    height: { ideal: 1080, min: 480 }
                },
                audio: false
            };
            var stream = await navigator.mediaDevices.getUserMedia(constraints);
            invoiceStream = stream;
            if (video) {
                video.srcObject = stream;
                video.play();
            }
        } catch (err) {
            console.warn('Camera stream error, trying fallback:', err);
            try {
                var fallbackStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                invoiceStream = fallbackStream;
                if (video) {
                    video.srcObject = fallbackStream;
                    video.play();
                }
            } catch (e) {
                alert('Tidak dapat mengakses kamera (' + (e.message || 'Izin ditolak') + '). Pastikan izin akses kamera telah diizinkan.');
                closeInvoiceCameraModal();
                var inputId = currentInvoiceTargetPrefix ? currentInvoiceTargetPrefix + 'FileInput' : 'invoiceFileInput';
                var fileInput = document.getElementById(inputId);
                if (fileInput) fileInput.click();
            }
        }
    }

    function stopInvoiceCameraStream() {
        if (invoiceStream) {
            try {
                invoiceStream.getTracks().forEach(function(track) { track.stop(); });
            } catch (e) {}
            invoiceStream = null;
        }
    }

    function switchInvoiceCamera() {
        invoiceCurrentFacing = (invoiceCurrentFacing === 'environment') ? 'user' : 'environment';
        startInvoiceCameraStream(invoiceCurrentFacing);
    }

    function takeInvoiceSnapshot() {
        var video = document.getElementById('invoiceCamVideo');
        var canvas = document.getElementById('invoiceCamCanvas');
        var snap = document.getElementById('invoiceCamSnapshot');
        var liveCtrls = document.getElementById('invoiceCamLiveControls');
        var snapCtrls = document.getElementById('invoiceCamSnapControls');
        var msg = document.getElementById('invoiceCamMsg');
        
        if (!video || !canvas) return;
        
        var w = video.videoWidth || 1280;
        var h = video.videoHeight || 720;
        canvas.width = w;
        canvas.height = h;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, w, h);
        
        invoiceCapturedDataUrl = canvas.toDataURL('image/jpeg', 0.88);
        
        if (snap) {
            snap.src = invoiceCapturedDataUrl;
            snap.style.display = 'block';
        }
        video.style.display = 'none';
        if (liveCtrls) liveCtrls.style.display = 'none';
        if (snapCtrls) snapCtrls.style.display = 'flex';
        if (msg) msg.textContent = 'Periksa hasil foto. Klik "Gunakan Foto Ini" jika sudah jelas dan terbaca.';
    }

    function retakeInvoiceSnapshot() {
        resetInvoiceCamState();
        var video = document.getElementById('invoiceCamVideo');
        if (video && invoiceStream) {
            video.play();
        } else {
            startInvoiceCameraStream(invoiceCurrentFacing);
        }
    }

    function confirmInvoiceSnapshot() {
        if (!invoiceCapturedDataUrl) return;
        
        var p = currentInvoiceTargetPrefix;
        var base64Input = document.getElementById(p ? p + 'Base64' : 'invoicePhotoBase64');
        var fileInput = document.getElementById(p ? p + 'FileInput' : 'invoiceFileInput');
        var previewContainer = document.getElementById(p ? p + 'PreviewContainer' : 'invoicePreviewContainer');
        var previewImg = document.getElementById(p ? p + 'PreviewImg' : 'invoicePreviewImg');
        var pdfBadge = document.getElementById(p ? p + 'PdfBadge' : 'invoicePdfBadge');
        var fileInfo = document.getElementById(p ? p + 'FileInfo' : 'invoiceFileInfo');
        var fileSub = document.getElementById(p ? p + 'FileSub' : 'invoiceFileSub');
        
        if (base64Input) base64Input.value = invoiceCapturedDataUrl;
        if (fileInput) fileInput.value = '';
        
        if (previewImg) {
            previewImg.src = invoiceCapturedDataUrl;
            previewImg.style.display = 'block';
        }
        if (pdfBadge) pdfBadge.style.display = 'none';
        if (fileInfo) fileInfo.textContent = 'Foto Kwitansi (Live Kamera)';
        if (fileSub) fileSub.textContent = 'Format: JPEG (Siap disimpan)';
        if (previewContainer) previewContainer.style.display = 'flex';
        
        closeInvoiceCameraModal();
    }

    function handleInvoiceFileSelected(input, prefix) {
        if (!input || !input.files || !input.files[0]) return;
        var file = input.files[0];
        var p = prefix || '';
        
        var base64Input = document.getElementById(p ? p + 'Base64' : 'invoicePhotoBase64');
        var previewContainer = document.getElementById(p ? p + 'PreviewContainer' : 'invoicePreviewContainer');
        var previewImg = document.getElementById(p ? p + 'PreviewImg' : 'invoicePreviewImg');
        var pdfBadge = document.getElementById(p ? p + 'PdfBadge' : 'invoicePdfBadge');
        var pdfName = document.getElementById(p ? p + 'PdfName' : 'invoicePdfName');
        var fileInfo = document.getElementById(p ? p + 'FileInfo' : 'invoiceFileInfo');
        var fileSub = document.getElementById(p ? p + 'FileSub' : 'invoiceFileSub');
        
        if (base64Input) base64Input.value = '';
        
        var isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
        var sizeKb = (file.size / 1024).toFixed(1);
        
        if (isPdf) {
            if (previewImg) previewImg.style.display = 'none';
            if (pdfBadge) pdfBadge.style.display = 'flex';
            if (pdfName) pdfName.textContent = file.name;
        } else {
            if (pdfBadge) pdfBadge.style.display = 'none';
            if (previewImg) {
                previewImg.src = URL.createObjectURL(file);
                previewImg.style.display = 'block';
            }
        }
        
        if (fileInfo) fileInfo.textContent = file.name;
        if (fileSub) fileSub.textContent = (isPdf ? 'Dokumen PDF' : 'Gambar/Scan') + ' (' + sizeKb + ' KB)';
        if (previewContainer) previewContainer.style.display = 'flex';
    }

    function clearInvoicePhoto(prefix) {
        var p = prefix || '';
        var base64Input = document.getElementById(p ? p + 'Base64' : 'invoicePhotoBase64');
        var fileInput = document.getElementById(p ? p + 'FileInput' : 'invoiceFileInput');
        var previewContainer = document.getElementById(p ? p + 'PreviewContainer' : 'invoicePreviewContainer');
        var previewImg = document.getElementById(p ? p + 'PreviewImg' : 'invoicePreviewImg');
        var pdfBadge = document.getElementById(p ? p + 'PdfBadge' : 'invoicePdfBadge');
        
        if (base64Input) base64Input.value = '';
        if (fileInput) fileInput.value = '';
        if (previewImg) { previewImg.src = ''; previewImg.style.display = 'none'; }
        if (pdfBadge) pdfBadge.style.display = 'none';
        if (previewContainer) previewContainer.style.display = 'none';
    }

    function openInvoiceImageViewer(src) {
        var modal = document.getElementById('invoiceImageViewerModal');
        var img = document.getElementById('invoiceViewerImg');
        var dl = document.getElementById('invoiceViewerDownload');
        if (!modal || !img) {
            window.open(src, '_blank');
            return;
        }
        img.src = src;
        if (dl) dl.href = src;
        modal.style.display = 'flex';
    }

    function closeInvoiceImageViewer() {
        var modal = document.getElementById('invoiceImageViewerModal');
        if (modal) modal.style.display = 'none';
    }
    </script>
    <?php
}

/**
 * Modul: Form Input Tiket
 * Proses: Menangani pembuatan tiket baru oleh user atau admin terkait masalah/kerusakan aset.
 */
function handle_route_ticket_form(PDO $pdo): void
{
    $user = require_regulation('tickets', 'create');
    ensure_corrective_maintenance_schema($pdo);

    $preMntId = (int)($_GET['maintenance_asset_id'] ?? 0);
    $prePcId = trim((string)($_GET['pc_id'] ?? ''));

    if ($prePcId !== '' && $preMntId <= 0) {
        $stmt = $pdo->prepare("SELECT maintenance_asset_id FROM pcs WHERE pc_id = ?");
        $stmt->execute([$prePcId]);
        $preMntId = (int)($stmt->fetchColumn() ?: 0);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $code = generate_next_ticket_code($pdo);
        $assetItemId = (int)($_POST['asset_item_id'] ?? 0) ?: null;
        $maintAssetId = (int)($_POST['maintenance_asset_id'] ?? 0) ?: null;
        $reporterName = trim((string)($_POST['reporter_name'] ?? ''));
        $reporterNik = trim((string)($_POST['reporter_nik'] ?? ''));
        $reporterContact = trim((string)($_POST['reporter_contact'] ?? ''));
        $issueCat = trim((string)($_POST['issue_category'] ?? 'hardware'));
        $priority = trim((string)($_POST['priority'] ?? 'medium'));
        $subject = trim((string)($_POST['subject'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $odometer = (int)($_POST['vehicle_odometer_km'] ?? 0) ?: null;

        if ($subject === '' || $reporterName === '') {
            flash('Nama pelapor dan subjek kendala wajib diisi.', 'err');
            redirect_to('ticket_form');
        }

        // Ambil data detail aset dari asset_items
        $assetCat = 'IT';
        $pcId = null;
        $companyId = null;
        $location = trim((string)($_POST['location_label'] ?? ''));

        if ($assetItemId) {
            $aiStmt = $pdo->prepare('SELECT ai.*, ag.group_code, c.company_name FROM asset_items ai LEFT JOIN asset_groups ag ON ag.id = ai.asset_group_id LEFT JOIN asset_companies c ON c.id = ai.company_id WHERE ai.id = ? LIMIT 1');
            $aiStmt->execute([$assetItemId]);
            $aiRow = $aiStmt->fetch();
            if ($aiRow) {
                $grp = strtoupper((string)($aiRow['group_code'] ?? ''));
                if ($grp === 'VH') {
                    $assetCat = 'Vehicle';
                } elseif (in_array($grp, ['FC', 'BLD', 'FCL'], true)) {
                    $assetCat = 'Facility';
                } else {
                    $assetCat = 'IT';
                }
                $companyId = (int)($aiRow['company_id'] ?? 0) ?: null;
                if ($location === '' && !empty($aiRow['location_label'])) {
                    $location = (string)$aiRow['location_label'];
                }
                if (db_table_exists($pdo, 'pcs')) {
                    $stP = $pdo->prepare('SELECT pc_id FROM pcs WHERE asset_item_id = ? LIMIT 1');
                    $stP->execute([$assetItemId]);
                    $pcId = $stP->fetchColumn() ?: null;
                }
            }
        } elseif ($maintAssetId) {
            $mRow = get_maintenance_asset_unit($pdo, $maintAssetId);
            if ($mRow) {
                $assetItemId = $mRow['asset_item_id'];
                $pcId = $mRow['pc_id'] ?: null;
                $companyId = (int)($mRow['company_id'] ?? 0) ?: null;
                $assetCat = $mRow['asset_category'];
                if ($location === '' && !empty($mRow['location_label'])) {
                    $location = (string)$mRow['location_label'];
                }
            }
        }

        // Upload photo before jika ada
        $photoBefore = null;
        if (!empty($_FILES['photo_before']['tmp_name']) && is_uploaded_file($_FILES['photo_before']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../../public/uploads/tickets';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $ext = strtolower(pathinfo((string)$_FILES['photo_before']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $filename = 'before_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['photo_before']['tmp_name'], $uploadDir . '/' . $filename)) {
                    $photoBefore = 'uploads/tickets/' . $filename;
                }
            }
        }

        // Upload invoice / kwitansi photo (via file or live camera)
        $invoicePhoto = save_corrective_invoice_photo($_FILES['invoice_photo'] ?? null, $_POST['invoice_photo_base64'] ?? null);

        $handledByType = trim((string)($_POST['handled_by_type'] ?? 'internal'));
        $handledByUserId = (int)($_POST['handled_by_user_id'] ?? 0) ?: null;
        $handledByWorkshopId = (int)($_POST['handled_by_workshop_id'] ?? 0) ?: null;
        if ($handledByType !== 'workshop') { $handledByWorkshopId = null; }
        if ($handledByType !== 'internal') { $handledByUserId = null; }

        $stmt = $pdo->prepare("INSERT INTO corrective_tickets (
            ticket_code, asset_category, asset_item_id, maintenance_asset_id, pc_id, company_id, 
            location_label, vehicle_odometer_km, reporter_name, reporter_nik, reporter_contact, 
            issue_category, priority, subject, description, photo_before, invoice_photo, status, assigned_technician_name,
            handled_by_type, handled_by_user_id, handled_by_workshop_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, ?, ?, ?)");

        $stmt->execute([
            $code, $assetCat, $assetItemId, $maintAssetId, $pcId, $companyId,
            $location, $odometer, $reporterName, $reporterNik, $reporterContact,
            $issueCat, $priority, $subject, $desc, $photoBefore, $invoicePhoto,
            ($user['role'] === 'technician' ? $user['name'] : null),
            $handledByType, $handledByUserId, $handledByWorkshopId
        ]);
        $ticketId = (int)$pdo->lastInsertId();

        // Save requested actions
        if (!empty($_POST['requested_actions']) && is_array($_POST['requested_actions'])) {
            $actStmt = $pdo->prepare("INSERT INTO corrective_ticket_actions (ticket_id, job_action_id, action_title) VALUES (?, ?, ?)");
            foreach ($_POST['requested_actions'] as $actId) {
                $aRow = $pdo->query("SELECT action_name AS action_title FROM corrective_action_types WHERE id = " . (int)$actId)->fetch();
                if ($aRow) {
                    $actStmt->execute([$ticketId, (int)$actId, $aRow['action_title']]);
                }
            }
        }

        // Save repair cost, solution details, invoice photo, & spareparts if provided
        $repairCost = (float)($_POST['repair_cost'] ?? 0);
        $solutionDetails = trim((string)($_POST['solution_details'] ?? ''));
        $partNewName = trim((string)($_POST['part_new_name'] ?? ''));
        $partNewSerial = trim((string)($_POST['part_new_serial'] ?? ''));
        $partCost = (float)($_POST['part_cost'] ?? 0);

        if ($repairCost > 0 || $solutionDetails !== '' || $partNewName !== '' || $invoicePhoto !== null) {
            $repStmt = $pdo->prepare("INSERT INTO corrective_repairs (
                ticket_id, technician_name, action_type, solution_details, repair_cost, invoice_photo
            ) VALUES (?, ?, 'corrective', ?, ?, ?)");
            $repStmt->execute([
                $ticketId, $user['name'], $solutionDetails ?: 'Pencatatan nota/kwitansi perbaikan saat tiket dibuat.', $repairCost, $invoicePhoto
            ]);
            $repairId = (int)$pdo->lastInsertId();

            if ($partNewName !== '') {
                $pdo->prepare("INSERT INTO corrective_repair_parts (repair_id, new_part_name, new_part_serial, part_cost) VALUES (?, ?, ?, ?)")
                    ->execute([$repairId, $partNewName, $partNewSerial ?: null, $partCost]);
            }
        }

        flash("Tiket baru {$code} berhasil dibuat.");
        redirect_to('ticket_detail', ['id' => $ticketId]);
    }

    // Ambil seluruh daftar Unit Aset untuk dropdown cepat
    $maintList = $pdo->query("SELECT ai.id, ai.asset_code, ai.asset_name, ai.asset_mode, ai.location_label,
                                     ai.custodian_name, ai.custodian_nik, ag.group_code, ag.group_name, at.type_code, at.type_name
                              FROM asset_items ai
                              LEFT JOIN asset_groups ag ON ag.id = ai.asset_group_id
                              LEFT JOIN asset_types at ON at.id = ai.asset_type_id
                              WHERE ai.status NOT IN ('inactive', 'deleted')
                              ORDER BY (ai.asset_mode = 'group') DESC, ai.asset_code ASC")->fetchAll(PDO::FETCH_ASSOC);

    render_header('Buat Tiket Corrective Baru', $user);
    ?>
    <section class="panel">
        <h1>Buat Tiket Kendala / Reparasi Baru</h1>
        <p class="muted">Pilih Unit Aset yang bermasalah. Untuk unit bundling, cukup pilih unit Induk Bundle atau anggota terkait.</p>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

            <?php
            // Ambil master Komoditas (Asset Groups)
            $groupList = $pdo->query("SELECT id, group_code, group_name FROM asset_groups WHERE is_active=1 ORDER BY group_name")->fetchAll();
            ?>
            <h2>1. Identitas Unit Aset & Tindakan</h2>
            <div class="grid three">
                <label>Komoditas *
                    <select id="dynGroupId" required onchange="onDynGroupChange()">
                        <option value="">-- Pilih Komoditas --</option>
                        <?php foreach ($groupList as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= e($g['group_code'] . ' - ' . $g['group_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Kategori *
                    <select id="dynTypeId" required onchange="onDynTypeChange()">
                        <option value="">-- Pilih Komoditas Dulu --</option>
                    </select>
                </label>
                <div style="position:relative;">
                    <label style="margin:0 0 4px 0;display:flex;justify-content:space-between;align-items:center;font-weight:600;font-size:13px;">
                        <span>Unit Aset *</span>
                        
                    </label>
                    <div style="position:relative;display:flex;align-items:stretch;">
                        <input type="text" id="maintAssetCombobox" placeholder="🔍 Cari / Pilih unit aset (Kode Baru, Kode Lama, Nama, PIC)..." autocomplete="off" style="width:100%;box-sizing:border-box;padding:8px 36px 8px 12px;font-size:13px;border:1.5px solid #0284c7;border-radius:6px;height:38px;background:#fff;" onfocus="showMaintAssetDropdown(this.value)" oninput="showMaintAssetDropdown(this.value)">
                        <button type="button" onclick="toggleMaintAssetDropdown(event)" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:#0284c7;font-size:13px;cursor:pointer;padding:4px;line-height:1;" title="Buka / Tutup Pilihan Aset">▼</button>
                    </div>
                    <select name="asset_item_id" id="maintAssetSelect" required onchange="onMaintAssetChange()" style="display:none;">
                        <option value="">-- Pilih Kategori Dulu --</option>
                    </select>
                    <div id="maintAssetDropdownList" style="display:none;position:absolute;left:0;right:0;top:calc(100% + 4px);max-height:260px;overflow-y:auto;background:#fff;border:1px solid #cbd5e1;border-radius:8px;box-shadow:0 12px 30px rgba(0,0,0,0.18);z-index:99999;"></div>
                </div>
            </div>
            <div id="vehicleKmWrapper" style="display:none; margin-top:12px;">
                <label>Kilometer Kendaraan (KM)
                    <input type="number" name="vehicle_odometer_km" placeholder="Contoh: 45200">
                </label>
            </div>

            <?php
            // List mekanik internal & workshop
            $mechanics = $pdo->query("SELECT id, name FROM users WHERE role='corrective_maintenance' AND is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            $workshops = $pdo->query("SELECT id, workshop_name FROM corrective_workshops ORDER BY workshop_name")->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <h2>2. Penanganan Oleh</h2>
            <div style="background:#fff; border:1px solid #e2e8f0; padding:15px; border-radius:6px; margin-bottom:20px;">
                <div style="display:flex; gap:20px; margin-bottom:15px;">
                    <label style="font-weight:normal; display:flex; align-items:center; gap:5px; cursor:pointer;">
                        <input type="radio" name="handled_by_type" value="internal" checked onclick="toggleHandledBy()">
                        <strong>Internal (Mekanik)</strong>
                    </label>
                    <label style="font-weight:normal; display:flex; align-items:center; gap:5px; cursor:pointer;">
                        <input type="radio" name="handled_by_type" value="workshop" onclick="toggleHandledBy()">
                        <strong>Bengkel / Workshop</strong>
                    </label>
                </div>
                
                <div id="handledInternalWrap">
                    <label>Pilih Mekanik Internal
                        <select name="handled_by_user_id">
                            <option value="">-- Belum Ditentukan --</option>
                            <?php foreach ($mechanics as $mec): ?>
                                <option value="<?= $mec['id'] ?>"><?= e($mec['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div id="handledWorkshopWrap" style="display:none;">
                    <label>Pilih Bengkel / Workshop
                        <select name="handled_by_workshop_id" style="width:100%;">
                            <option value="">-- Pilih Bengkel --</option>
                            <?php foreach ($workshops as $wks): ?>
                                <option value="<?= $wks['id'] ?>"><?= e($wks['workshop_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </div>

            <?php
            $allActions = $pdo->query("SELECT id, action_name AS action_title FROM corrective_action_types ORDER BY action_name ASC")->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <h2>3. Daftar Tindakan & Catatan Perbaikan</h2>
            <div id="actionListWrapper" style="margin-bottom:20px; background:#f8fafc; padding:15px; border-radius:6px; border:1px solid #e2e8f0;">
                <h3 style="margin-top:0;font-size:15px;color:#0369a1;">Daftar Tindakan / Pekerjaan Perbaikan: <span id="jdNameSpan" style="color:#0ea5e9;">Semua Opsi (Pilih Kategori untuk Menyaring)</span></h3>
                <div id="dynamicActionRows">
                    <div class="action-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center;">
                        <select name="requested_actions[]" style="flex:1;">
                            <option value="">-- Pilih Tindakan (Ketik untuk mencari) --</option>
                            <?php foreach ($allActions as $a): ?>
                                <option value="<?= (int)$a['id'] ?>"><?= e($a['action_title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn danger" onclick="if(document.querySelectorAll('#dynamicActionRows .action-row').length > 1) { this.parentElement.remove(); } else { this.previousElementSibling.value = ''; }" style="padding:8px 12px;" title="Hapus">X</button>
                    </div>
                </div>
                <button type="button" class="btn secondary" onclick="addDynamicAction()" style="font-size:13px; padding:6px 12px; margin-top:5px;">+ Tambah Tindakan Lain</button>
            </div>

            <div style="background:#fff; border:1px solid #e2e8f0; padding:15px; border-radius:6px; margin-bottom:20px;">
                <h3 style="margin-top:0;font-size:14px;color:#334155;">💰 Biaya Service, Catatan Sparepart & Catatan Kerja (Opsional)</h3>
                <div class="grid two" style="margin-bottom:12px;">
                    <label>Biaya Service / Jasa (Rp)
                        <input type="number" name="repair_cost" value="0" placeholder="0">
                    </label>
                    <label>Biaya Sparepart (Rp)
                        <input type="number" name="part_cost" value="0" placeholder="0">
                    </label>
                </div>

                <div class="grid two" style="margin-bottom:12px;">
                    <label>Nama Sparepart Baru
                        <input name="part_new_name" placeholder="Misal: RAM 8GB DDR4 / Ban Bridgestone / Aki GS">
                    </label>
                    <label>Serial Number / Spesifikasi Part
                        <input name="part_new_serial" placeholder="Misal: SN12345 / 195/65 R15">
                    </label>
                </div>

                <label>Catatan Kerja Lainnya
                    <textarea name="solution_details" style="min-height:70px;" placeholder="Catatan perbaikan tambahan, tindakan penanganan teknisi, atau instruksi khusus..."></textarea>
                </label>

                <div style="margin-top:14px; padding:12px 14px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:6px;">
                    <label style="font-weight:600; display:block; margin-bottom:4px; color:#1e293b; font-size:13px;">
                        🧾 Foto / Scan Kwitansi / Invoice
                    </label>
                    <p class="muted" style="font-size:12px; margin-top:0; margin-bottom:10px;">
                        Tambahkan file image/scan bukti kwitansi / invoice (JPG, PNG, PDF) atau gunakan live kamera untuk mengambil foto secara langsung.
                    </p>

                    <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                        <label class="btn" style="cursor:pointer; display:inline-flex; align-items:center; gap:6px; padding:7px 13px; font-size:13px; background:#fff; border:1px solid #cbd5e1; border-radius:6px; font-weight:500;">
                            📁 Pilih File Image / Scan PDF
                            <input type="file" id="invoiceFileInput" name="invoice_photo" accept="image/*,.pdf,application/pdf" style="display:none;" onchange="handleInvoiceFileSelected(this, '')">
                        </label>

                        <button type="button" class="btn primary" onclick="openInvoiceCameraModal('')" style="display:inline-flex; align-items:center; gap:6px; padding:7px 14px; font-size:13px; border-radius:6px;">
                            📷 Ambil Foto (Live Kamera)
                        </button>
                    </div>

                    <input type="hidden" id="invoicePhotoBase64" name="invoice_photo_base64" value="">

                    <div id="invoicePreviewContainer" style="display:none; margin-top:10px; align-items:center; gap:12px; background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:10px;">
                        <img id="invoicePreviewImg" src="" alt="Preview Kwitansi" style="max-height:80px; max-width:110px; border-radius:4px; object-fit:cover; border:1px solid #cbd5e1; display:none;">
                        <div id="invoicePdfBadge" style="display:none; align-items:center; gap:6px; background:#fee2e2; color:#b91c1c; padding:6px 10px; border-radius:4px; font-weight:600; font-size:12px;">
                            📄 <span id="invoicePdfName">Dokumen PDF</span>
                        </div>
                        <div style="flex:1;">
                            <strong id="invoiceFileInfo" style="font-size:13px; color:#334155; display:block;">File terpilih</strong>
                            <span id="invoiceFileSub" class="muted" style="font-size:11px;">Siap disimpan</span>
                        </div>
                        <button type="button" class="btn danger" onclick="clearInvoicePhoto('')" style="padding:4px 8px; font-size:11px;">✕ Hapus</button>
                    </div>
                </div>
            </div>

            <h2>4. Informasi Pelapor</h2>
            <div class="grid three">
                <label>Nama Pelapor *
                    <input name="reporter_name" id="reporterName" value="<?= e($user['name'] ?? '') ?>" required placeholder="Nama pemakai / PIC">
                </label>
                <label>NIK Pelapor
                    <input name="reporter_nik" id="reporterNik" placeholder="NIK karyawan">
                </label>
                <label>No. HP / WhatsApp Pelapor
                    <input name="reporter_contact" placeholder="0812xxxxxx">
                </label>
            </div>
            <?= function_exists('employee_portal_name_picker_html') ? employee_portal_name_picker_html('ticketReporter', 'reporterName', 'reporterNik') : '' ?>

            <h2>5. Rincian Masalah & Foto Bukti</h2>
            <?php
            // Master Lokasi dari database
            $locationsList = $pdo->query("SELECT id, location_code, location_name FROM asset_locations WHERE is_active=1 ORDER BY location_name")->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div id="locationFieldWrapper" style="margin-bottom:16px;">
                <label>Lokasi / Gedung / Ruangan (Khusus Penanganan Internal)
                    <select name="location_label" id="locInput">
                        <option value="">-- Pilih Lokasi dari Database --</option>
                        <?php foreach ($locationsList as $loc): ?>
                            <option value="<?= e($loc['location_name']) ?>"><?= e($loc['location_code'] . ' - ' . $loc['location_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="grid three">
                <label>Kategori Masalah
                    <select name="issue_category" id="ticketIssueCat">
                        <option value="hardware">Kerusakan Hardware / Komponen</option>
                        <option value="software">Masalah Software / OS / Aplikasi</option>
                        <option value="network">Jaringan / Koneksi Internet</option>
                        <option value="printer">Printer / Scanner Error</option>
                        <option value="engine">Mesin / Ban / Rem (Kendaraan)</option>
                        <option value="electrical">Kelistrikan / Aki / Lampu</option>
                        <option value="hvac">AC / Pendingin Ruangan</option>
                        <option value="plumbing">Pipa / Sanitasi Air</option>
                        <option value="general">Lain-lain / Kerusakan Fisik</option>
                    </select>
                </label>
                <label>Tingkat Prioritas *
                    <select name="priority" required>
                        <option value="low">Low (Bisa ditunda)</option>
                        <option value="medium" selected>Medium (Standard)</option>
                        <option value="high">High (Mendesak / Mengganggu Operasional)</option>
                        <option value="critical">CRITICAL (Operasional Terhenti Total)</option>
                    </select>
                </label>
                <label>Foto Kerusakan / Bukti Error
                    <input type="file" name="photo_before" accept="image/*" capture="environment">
                </label>
            </div>

            <label>Judul / Subjek Kendala *
                <input name="subject" required placeholder="Contoh: Layar bluescreen tidak bisa masuk Windows / Ban mobil bocor / AC tidak dingin">
            </label>

            <label>Deskripsi Lengkap Gejala Kerusakan
                <textarea name="description" placeholder="Jelaskan kronologi, pesan error, atau kondisi fisik saat terjadi masalah..."></textarea>
            </label>

            <div class="actions" style="margin-top:20px;">
                <a class="btn" href="<?= route_url('tickets') ?>">Batal</a>
                <button class="btn primary">Simpan & Terbitkan Tiket</button>
            </div>
        </form>
    </section>

    <script>
    function toggleHandledBy() {
        var rad = document.querySelector('input[name="handled_by_type"]:checked');
        var type = rad ? rad.value : 'internal';
        var locWrap = document.getElementById('locationFieldWrapper');
        if (type === 'internal') {
            document.getElementById('handledInternalWrap').style.display = 'block';
            document.getElementById('handledWorkshopWrap').style.display = 'none';
            if (locWrap) locWrap.style.display = 'block';
        } else {
            document.getElementById('handledInternalWrap').style.display = 'none';
            document.getElementById('handledWorkshopWrap').style.display = 'block';
            if (locWrap) locWrap.style.display = 'none';
        }
    }

    // Call on initial load
    toggleHandledBy();

    async function onDynGroupChange() {
        var gid = document.getElementById('dynGroupId').value;
        var tSel = document.getElementById('dynTypeId');
        var aSel = document.getElementById('maintAssetSelect');
        var jdSpan = document.getElementById('jdNameSpan');
        
        tSel.innerHTML = '<option value="">-- Loading Kategori... --</option>';
        aSel.innerHTML = '<option value="">-- Pilih Kategori Dulu --</option>';
        if (jdSpan) jdSpan.textContent = 'Semua Opsi (Pilih Kategori untuk Menyaring)';

        if (!gid) {
            tSel.innerHTML = '<option value="">-- Pilih Komoditas Dulu --</option>';
            return;
        }

        try {
            let res = await fetch('index.php?route=api_corrective_data&action=get_types&group_id=' + gid);
            let types = await res.json();
            
            tSel.innerHTML = '<option value="">-- Pilih Kategori --</option>';
            if (Array.isArray(types) && types.length > 0) {
                types.forEach(function(t) {
                    let opt = document.createElement('option');
                    opt.value = t.id;
                    opt.textContent = (t.type_code ? t.type_code + ' - ' : '') + t.type_name;
                    tSel.appendChild(opt);
                });
            } else {
                tSel.innerHTML = '<option value="">-- Tidak Ada Kategori --</option>';
            }
        } catch (e) {
            console.error(e);
            tSel.innerHTML = '<option value="">-- Gagal Memuat Kategori --</option>';
        }
    }

    async function onDynTypeChange() {
        var gid = document.getElementById('dynGroupId').value;
        var tid = document.getElementById('dynTypeId').value;
        var aSel = document.getElementById('maintAssetSelect');
        var jdSpan = document.getElementById('jdNameSpan');
        var actCheckboxes = document.getElementById('actionCheckboxes');

        aSel.innerHTML = '<option value="">-- Loading Unit Aset... --</option>';

        if (!gid || !tid) {
            aSel.innerHTML = '<option value="">-- Pilih Kategori Dulu --</option>';
            return;
        }

        try {
            // Load Assets
            let res = await fetch('index.php?route=api_corrective_data&action=get_assets&group_id=' + gid + '&type_id=' + tid);
            let assets = await res.json();
            window.currentCategoryAssets = Array.isArray(assets) ? assets : [];

            aSel.innerHTML = '<option value="">-- Pilih Unit Aset --</option>';
            var cbInput = document.getElementById('maintAssetCombobox');
            if (cbInput) {
                cbInput.value = '';
                cbInput.placeholder = window.currentCategoryAssets.length > 0 
                    ? ('🔍 Ketik / pilih dari ' + window.currentCategoryAssets.length + ' unit aset...') 
                    : '-- Tidak ada aset pada kategori ini --';
            }

            if (window.currentCategoryAssets.length > 0) {
                window.currentCategoryAssets.forEach(function(m) {
                    let badge = (m.asset_mode === 'group') ? '[📦 INDUK BUNDLE] ' : ((m.asset_mode === 'child') ? '[🔗 ANGGOTA] ' : '');
                    let aliasPart = m.alias_code ? (' [Lama: ' + m.alias_code + ']') : '';
                    let desc = badge + m.asset_code + aliasPart + ' - ' + m.asset_name + (m.custodian_name ? ' (' + m.custodian_name + ')' : '');
                    let opt = document.createElement('option');
                    opt.value = m.id;
                    opt.setAttribute('data-loc', m.location_label || '');
                    opt.setAttribute('data-user', m.custodian_name || '');
                    opt.textContent = desc;
                    aSel.appendChild(opt);
                });
            } else {
                aSel.innerHTML = '<option value="">-- Tidak ada aset pada kategori ini --</option>';
            }

            // Load Actions
            let resAct = await fetch('index.php?route=api_corrective_data&action=get_actions&group_id=' + gid + '&type_id=' + tid);
            let actionsData = await resAct.json();
            
            if (actionsData && actionsData.actions && actionsData.actions.length > 0) {
                if (jdSpan) jdSpan.textContent = actionsData.desk_name || 'Tindakan Kategori Ini';
                
                window.currentActionOptions = '<option value="">-- Pilih Tindakan (Ketik untuk mencari) --</option>';
                actionsData.actions.forEach(function(a) {
                    window.currentActionOptions += '<option value="'+a.id+'">'+a.action_title+'</option>';
                });
                
                var selects = document.querySelectorAll('#dynamicActionRows select[name="requested_actions[]"]');
                selects.forEach(function(sel) {
                    var currentVal = sel.value;
                    sel.innerHTML = window.currentActionOptions;
                    var opt = sel.querySelector('option[value="'+currentVal+'"]');
                    if(opt) {
                        sel.value = currentVal;
                    } else {
                        sel.value = '';
                    }
                });
            }
        } catch (e) {
            console.error(e);
        }
    }

    
    function toggleMaintAssetDropdown(e) {
        if (e) e.stopPropagation();
        var dd = document.getElementById('maintAssetDropdownList');
        if (!dd) return;
        if (dd.style.display === 'block') {
            dd.style.display = 'none';
        } else {
            var inp = document.getElementById('maintAssetCombobox');
            showMaintAssetDropdown(inp ? inp.value : '');
            if (inp) inp.focus();
        }
    }

    function showMaintAssetDropdown(q) {
        var dd = document.getElementById('maintAssetDropdownList');
        if (!dd) return;
        var term = (q || '').trim().toLowerCase();
        var assets = window.currentCategoryAssets || [];

        if (assets.length === 0) {
            dd.innerHTML = '<div style="padding:12px;font-size:12px;color:#64748b;text-align:center;">Pilih Komoditas & Kategori terlebih dahulu.</div>';
            dd.style.display = 'block';
            return;
        }

        var matches = assets.filter(function(m) {
            if (!term) return true;
            var code = (m.asset_code || '').toLowerCase();
            var alias = (m.alias_code || '').toLowerCase();
            var name = (m.asset_name || '').toLowerCase();
            var cust = (m.custodian_name || '').toLowerCase();
            var loc = (m.location_label || '').toLowerCase();
            return code.includes(term) || alias.includes(term) || name.includes(term) || cust.includes(term) || loc.includes(term);
        });

        if (matches.length === 0) {
            dd.innerHTML = '<div style="padding:12px;font-size:12px;color:#64748b;text-align:center;">Tidak ada unit aset cocok dengan kata kunci &ldquo;' + (q || '') + '&rdquo;</div>';
            dd.style.display = 'block';
            return;
        }

        dd.innerHTML = '';
        matches.slice(0, 30).forEach(function(m) {
            var div = document.createElement('div');
            div.style.cssText = 'padding:9px 12px;border-bottom:1px solid #f1f5f9;cursor:pointer;background:#fff;transition:background 0.15s ease;';
            
            var badge = (m.asset_mode === 'group') ? '[📦 INDUK BUNDLE] ' : ((m.asset_mode === 'child') ? '[🔗 ANGGOTA] ' : '');
            var aliasBadge = m.alias_code ? (' <span style="background:#e0f2fe;color:#0284c7;font-size:11px;font-weight:700;padding:1px 6px;border-radius:4px;border:1px solid #bae6fd;">[Lama: ' + m.alias_code + ']</span>') : '';
            var desc = badge + m.asset_code + aliasBadge + ' - ' + m.asset_name;
            var subParts = [];
            if (m.location_label) subParts.push('📍 ' + m.location_label);
            if (m.custodian_name) subParts.push('👤 ' + m.custodian_name);

            div.innerHTML = '<div style="font-size:13px;font-weight:700;color:#0f172a;"><strong style="color:#0284c7;">' + badge + m.asset_code + '</strong>' + aliasBadge + ' - ' + m.asset_name + '</div>'
                + (subParts.length > 0 ? ('<div style="font-size:11px;color:#64748b;margin-top:2px;">' + subParts.join(' &bull; ') + '</div>') : '');

            div.onmouseenter = function() { div.style.background = '#f0f9ff'; };
            div.onmouseleave = function() { div.style.background = '#fff'; };
            div.onmousedown = function() {
                var aSel = document.getElementById('maintAssetSelect');
                if (aSel) aSel.value = m.id;
                var cbInput = document.getElementById('maintAssetCombobox');
                if (cbInput) cbInput.value = m.asset_code + (m.alias_code ? ' [Lama: ' + m.alias_code + ']' : '') + ' - ' + m.asset_name;
                dd.style.display = 'none';
                onMaintAssetChange();
            };
            dd.appendChild(div);
        });
        dd.style.display = 'block';
    }

    document.addEventListener('click', function(e) {
        var dd = document.getElementById('maintAssetDropdownList');
        var inp = document.getElementById('maintAssetCombobox');
        if (dd && inp && !dd.contains(e.target) && e.target !== inp) {
            dd.style.display = 'none';
        }
    });

    function onMaintAssetChange() {
        var sel = document.getElementById('maintAssetSelect');
        var opt = sel.options[sel.selectedIndex];
        var loc = opt ? opt.getAttribute('data-loc') : '';
        var kmWrap = document.getElementById('vehicleKmWrapper');
        var locInput = document.getElementById('locInput');

        if (loc && locInput && !locInput.value) {
            locInput.value = loc;
        }
        var dynGroupSel = document.getElementById('dynGroupId');
        var groupText = (dynGroupSel && dynGroupSel.options[dynGroupSel.selectedIndex]) ? dynGroupSel.options[dynGroupSel.selectedIndex].text.toUpperCase() : '';
        if (kmWrap) {
            if (groupText.includes('VH -') || groupText.includes('VEHICLE')) {
                kmWrap.style.display = 'block';
            } else {
                kmWrap.style.display = 'none';
            }
        }
    }

    // Initialize global options from the first load
    window.currentActionOptions = document.querySelector('#dynamicActionRows select') ? document.querySelector('#dynamicActionRows select').innerHTML : '';

    function addDynamicAction() {
        var container = document.getElementById('dynamicActionRows');
        if (!container) return;
        
        var div = document.createElement('div');
        div.className = 'action-row';
        div.style.display = 'flex';
        div.style.gap = '10px';
        div.style.marginBottom = '10px';
        div.style.alignItems = 'center';
        
        var sel = document.createElement('select');
        sel.name = 'requested_actions[]';
        sel.style.flex = '1';
        sel.innerHTML = window.currentActionOptions;
        
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn danger';
        btn.style.padding = '8px 12px';
        btn.title = 'Hapus';
        btn.textContent = 'X';
        btn.onclick = function() {
            if(document.querySelectorAll('#dynamicActionRows .action-row').length > 1) {
                div.remove();
            } else {
                sel.value = '';
            }
        };
        
        div.appendChild(sel);
        div.appendChild(btn);
        container.appendChild(div);
    }
    </script>
    <?php
    render_invoice_camera_modal_and_script();
    render_footer();
}

/**
 * Modul: Detail Tiket
 * Proses: Menampilkan rincian lengkap suatu tiket, riwayat penanganan, dan komentar antar teknisi/user.
 */
function handle_route_ticket_detail(PDO $pdo): void
{
    $user = require_regulation('tickets', 'view');
    ensure_corrective_maintenance_schema($pdo);

    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT t.*, ma.maintenance_asset_code, ma.name AS maintenance_asset_name, 
                                  ma.asset_group_id AS ma_group_id, ma.asset_type_id AS ma_type_id,
                                  ai.asset_group_id AS ai_group_id, ai.asset_type_id AS ai_type_id,
                                  c.company_name
                           FROM corrective_tickets t
                           LEFT JOIN maintenance_assets ma ON ma.id = t.maintenance_asset_id
                           LEFT JOIN asset_items ai ON ai.id = t.asset_item_id
                           LEFT JOIN asset_companies c ON c.id = t.company_id
                           WHERE t.id = ?");
    $stmt->execute([$id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        flash('Tiket tidak ditemukan.', 'err');
        redirect_to('tickets');
    }

    $unit = null;
    if (!empty($ticket['maintenance_asset_id'])) {
        $unit = get_maintenance_asset_unit($pdo, (int)$ticket['maintenance_asset_id']);
    }
    $ticketGroupId = (int)($unit['asset_group_id'] ?? ($ticket['ma_group_id'] ?: ($ticket['ai_group_id'] ?? 0)));
    $ticketTypeId = (int)($unit['asset_type_id'] ?? ($ticket['ma_type_id'] ?: ($ticket['ai_type_id'] ?? 0)));

    // Ambil riwayat tindakan reparasi
    $repairsStmt = $pdo->prepare("SELECT cr.* FROM corrective_repairs cr WHERE cr.ticket_id = ? ORDER BY cr.repaired_at DESC, cr.id DESC");
    $repairsStmt->execute([$id]);
    $repairs = $repairsStmt->fetchAll(PDO::FETCH_ASSOC);

    $repairIds = array_column($repairs, 'id');
    $partsByRepair = [];
    if ($repairIds) {
        $inRepairs = implode(',', array_map('intval', $repairIds));
        $partsStmt = $pdo->query("SELECT * FROM corrective_repair_parts WHERE repair_id IN ($inRepairs) ORDER BY id ASC");
        foreach ($partsStmt->fetchAll(PDO::FETCH_ASSOC) as $part) {
            $partsByRepair[(int)$part['repair_id']][] = $part;
        }
    }

    render_header('Detail Tiket #' . $ticket['ticket_code'], $user);
    ?>
    <section class="panel">
        <div class="split">
            <div>
                <h1>Tiket #<?= e($ticket['ticket_code']) ?></h1>
                <p class="muted">Dibuat pada <?= e(date('d M Y H:i:s', strtotime($ticket['created_at']))) ?> oleh <?= e($ticket['reporter_name']) ?></p>
            </div>
            <div class="actions">
                <a class="btn" href="<?= route_url('tickets') ?>">← Kembali ke Daftar</a>
                <?php if (has_regulation('tickets', 'delete')): ?>
                    <form method="post" action="<?= route_url('tickets') ?>" style="display:inline;" onsubmit="return confirm('Hapus tiket <?= e($ticket['ticket_code']) ?> beserta seluruh data perbaikan terkait?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_ticket">
                        <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
                        <button type="submit" class="btn danger">Hapus Tiket</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid two" style="margin-top:16px;">
            <div class="mini-card" style="background:#f8fafc;padding:16px;border-radius:8px;border:1px solid #e2e8f0">
                <h3 style="margin-top:0;">Informasi Tiket & Maintenance Asset</h3>
                <table>
                    <tr><th style="width:160px;">No. Maintenance Asset</th><td><strong style="color:#0284c7;"><?= e($ticket['maintenance_asset_code'] ?: ($ticket['pc_id'] ? 'PC ' . $ticket['pc_id'] : '-')) ?></td></tr>
                    <tr><th>Nama Unit</th><td><?= e($ticket['maintenance_asset_name'] ?: '-') ?></td></tr>
                    <tr><th>Kategori Aset</th><td><?= ticket_category_badge($ticket['asset_category']) ?></td></tr>
                    <tr><th>Prioritas</th><td><?= ticket_priority_badge($ticket['priority']) ?></td></tr>
                    <tr><th>Status</th><td><?= ticket_status_badge($ticket['status']) ?></td></tr>
                    <?php if ($ticket['vehicle_odometer_km']): ?>
                        <tr><th>KM Kendaraan</th><td><?= number_format((int)$ticket['vehicle_odometer_km'], 0, ',', '.') ?> KM</td></tr>
                    <?php endif; ?>
                    <tr><th>Company / Lokasi</th><td><?= e($ticket['company_name'] ?: '-') ?> / <?= e($ticket['location_label'] ?: '-') ?></td></tr>
                    <tr><th>Pelapor</th><td><?= e($ticket['reporter_name']) ?> <?= $ticket['reporter_nik'] ? '(' . e($ticket['reporter_nik']) . ')' : '' ?> <?= $ticket['reporter_contact'] ? ' - Telp: ' . e($ticket['reporter_contact']) : '' ?></td></tr>
                    <tr><th>Teknisi PIC</th><td><?= e($ticket['assigned_technician_name'] ?: 'Belum ditentukan') ?></td></tr>
                </table>
            </div>

            <div class="mini-card" style="background:#f8fafc;padding:16px;border-radius:8px;border:1px solid #e2e8f0">
                <h3 style="margin-top:0;">Detail Keluhan & Foto</h3>
                <p>Subjek: <?= e($ticket['subject']) ?></p>
                <p>Deskripsi Masalah:<br><?= nl2br(e($ticket['description'] ?: '-')) ?></p>
                <?php if ($ticket['photo_before']): ?>
                    <p style="margin-bottom:4px;">Foto Kondisi Awal:</p>
                    <a href="<?= e($ticket['photo_before']) ?>" target="_blank" onclick="openInvoiceImageViewer(this.href); return false;">
                        <img src="<?= e($ticket['photo_before']) ?>" style="max-width:240px;max-height:160px;border-radius:6px;border:1px solid #cbd5e1;display:block;">
                    </a>
                <?php endif; ?>
                <?php if (!empty($ticket['invoice_photo'])): ?>
                    <p style="margin-top:12px; margin-bottom:4px;"><strong>🧾 Foto / Scan Kwitansi / Invoice:</strong></p>
                    <?php if (str_ends_with(strtolower($ticket['invoice_photo']), '.pdf')): ?>
                        <a href="<?= e(url($ticket['invoice_photo'])) ?>" target="_blank" class="btn secondary" style="display:inline-flex; align-items:center; gap:6px; font-size:12px; padding:6px 12px; text-decoration:none;">
                            📄 Buka Dokumen PDF Kwitansi
                        </a>
                    <?php else: ?>
                        <a href="<?= e(url($ticket['invoice_photo'])) ?>" target="_blank" onclick="openInvoiceImageViewer(this.href); return false;">
                            <img src="<?= e(url($ticket['invoice_photo'])) ?>" alt="Kwitansi / Invoice" style="max-width:240px;max-height:160px;border-radius:6px;border:1px solid #cbd5e1;display:block;">
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Form Eksekusi Teknisi -->
    <?php if ($ticket['status'] !== 'closed' && in_array($user['role'], ['admin', 'maintenance_admin', 'technician', 'corrective_maintenance'], true)): ?>
        <section class="panel">
            <h2>Input Tindakan Perbaikan & Penggantian Sparepart</h2>
            <form method="post" action="<?= route_url('ticket_action') ?>" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">

                <div class="grid three">
                    <label>Ubah Status Tiket
                        <select name="update_status" required>
                            <option value="in_progress" <?= $ticket['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress (Sedang Dikerjakan)</option>
                            <option value="pending_part" <?= $ticket['status'] === 'pending_part' ? 'selected' : '' ?>>Waiting Part (Menunggu Sparepart)</option>
                            <option value="resolved" <?= $ticket['status'] === 'resolved' ? 'selected' : '' ?>>Resolved (Perbaikan Selesai)</option>
                            <option value="closed" <?= $ticket['status'] === 'closed' ? 'selected' : '' ?>>Closed (Tutup Tiket)</option>
                        </select>
                    </label>
                    <label>Teknisi Pelaksana *
                        <input name="technician_name" value="<?= e($user['name']) ?>" required>
                    </label>
                    <label>Jenis Tindakan (Job Desk Corrective) *
                        <select name="action_type" required>
                            <?= corrective_action_type_options($pdo, null, $ticketGroupId, $ticketTypeId) ?>
                        </select>
                    </label>
                </div>

                <div class="grid two">
                    <label>Analisa Akar Penyebab Kerusakan (*Root Cause*)
                        <textarea name="root_cause_analysis" style="min-height:80px;" placeholder="Penyebab kerusakan: misal kapasitor short, bad sector pada SSD, ban robek sisi samping..."></textarea>
                    </label>
                    <label>Tindakan / Solusi yang Dilakukan *
                        <textarea name="solution_details" style="min-height:80px;" required placeholder="Langkah perbaikan yang dilakukan secara detail..."></textarea>
                    </label>
                </div>

                <h3>Vendor / Bengkel Luar & Biaya Jasa (Opsional)</h3>
                <div class="grid three">
                    <label>Nama Vendor / Bengkel
                        <input name="vendor_name" placeholder="Nama bengkel rekanan / vendor">
                    </label>
                    <label>No. Invoice / Kwitansi
                        <input name="vendor_invoice_no" placeholder="INV-xxxxxx">
                    </label>
                    <label>Biaya Jasa Servis (Rp)
                        <input type="number" name="repair_cost" value="0" placeholder="0">
                    </label>
                </div>

                <div style="margin-top:10px; margin-bottom:14px; padding:12px 14px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:6px;">
                    <label style="font-weight:600; display:block; margin-bottom:4px; color:#1e293b; font-size:13px;">
                        🧾 Foto / Scan Kwitansi / Invoice Vendor
                    </label>
                    <p class="muted" style="font-size:12px; margin-top:0; margin-bottom:10px;">
                        Unggah scan file kwitansi / invoice rekanan (JPG, PNG, PDF) atau gunakan live kamera untuk memotret fisik nota.
                    </p>

                    <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                        <label class="btn" style="cursor:pointer; display:inline-flex; align-items:center; gap:6px; padding:6px 12px; font-size:13px; background:#fff; border:1px solid #cbd5e1; border-radius:6px;">
                            📁 Pilih File / Scan PDF
                            <input type="file" id="act_invoiceFileInput" name="invoice_photo" accept="image/*,.pdf,application/pdf" style="display:none;" onchange="handleInvoiceFileSelected(this, 'act_invoice')">
                        </label>
                        <button type="button" class="btn primary" onclick="openInvoiceCameraModal('act_invoice')" style="display:inline-flex; align-items:center; gap:6px; padding:6px 12px; font-size:13px; border-radius:6px;">
                            📷 Buka Live Kamera
                        </button>
                    </div>

                    <input type="hidden" id="act_invoiceBase64" name="invoice_photo_base64" value="">

                    <div id="act_invoicePreviewContainer" style="display:none; margin-top:10px; align-items:center; gap:12px; background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:10px;">
                        <img id="act_invoicePreviewImg" src="" alt="Preview Kwitansi" style="max-height:80px; max-width:110px; border-radius:4px; object-fit:cover; border:1px solid #cbd5e1; display:none;">
                        <div id="act_invoicePdfBadge" style="display:none; align-items:center; gap:6px; background:#fee2e2; color:#b91c1c; padding:6px 10px; border-radius:4px; font-weight:600; font-size:12px;">
                            📄 <span id="act_invoicePdfName">Dokumen PDF</span>
                        </div>
                        <div style="flex:1;">
                            <strong id="act_invoiceFileInfo" style="font-size:13px; color:#334155; display:block;">File terpilih</strong>
                            <span id="act_invoiceFileSub" class="muted" style="font-size:11px;">Siap disimpan</span>
                        </div>
                        <button type="button" class="btn danger" onclick="clearInvoicePhoto('act_invoice')" style="padding:4px 8px; font-size:11px;">✕ Hapus</button>
                    </div>
                </div>

                <h3>Penggantian Sparepart (Jika Ada Part Baru Dipasang)</h3>
                <div id="sparepartContainer">
                    <div class="grid four" style="background:#f1f5f9;padding:12px;border-radius:6px;margin-bottom:8px;">
                        <label>Nama Part Lama yang Dilepas
                            <input name="parts[0][old_name]" placeholder="Misal: RAM 4GB DDR4 / Aki GS 45A">
                        </label>
                        <label>Nama Part Baru yang Dipasang
                            <input name="parts[0][new_name]" placeholder="Misal: RAM 8GB Kingston / Aki Yuasa 45A">
                        </label>
                        <label>Serial Number Part Baru
                            <input name="parts[0][new_serial]" placeholder="SN part baru">
                        </label>
                        <label>Harga Part Baru (Rp)
                            <input type="number" name="parts[0][cost]" value="0">
                        </label>
                    </div>
                </div>

                <label>Foto Bukti Selesai Perbaikan
                    <input type="file" name="photo_after" accept="image/*" capture="environment">
                </label>

                <div class="actions" style="margin-top:16px;">
                    <button class="btn primary">Simpan Tindakan Perbaikan</button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <!-- Riwayat Tindakan Reparasi yang Pernah Dicatat -->
    <section class="panel">
        <h2>Riwayat Tindakan & Penggantian Part pada Tiket Ini</h2>
        <?php if (!$repairs): ?>
            <p class="muted">Belum ada tindakan perbaikan yang dicatat.</p>
        <?php else: ?>
            <?php foreach ($repairs as $r): ?>
                <div style="border-left:4px solid #0f766e;padding:12px 16px;background:#f8fafc;margin-bottom:16px;border-radius:0 8px 8px 0;border-top:1px solid #e2e8f0;border-right:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;">
                    <div class="split">
                        <div>
                            <?= e(date('d M Y H:i', strtotime($r['repaired_at']))) ?> oleh <?= e($r['technician_name']) ?>
                            <span class="badge ok" style="margin-left:8px;"><?= e($r['action_type']) ?></span>
                        </div>
                        <?php if ((float)$r['repair_cost'] > 0): ?>
                            <span style="font-weight:700;color:#0f766e;">Jasa: Rp <?= number_format((float)$r['repair_cost'], 0, ',', '.') ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($r['root_cause_analysis']): ?>
                        <p style="margin:8px 0 4px;">Penyebab: <?= nl2br(e($r['root_cause_analysis'])) ?></p>
                    <?php endif; ?>
                    <p style="margin:4px 0;">Solusi: <?= nl2br(e($r['solution_details'])) ?></p>

                    <?php if (!empty($partsByRepair[(int)$r['id']])): ?>
                        <div style="margin-top:10px;background:#fff;padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;">
                            Sparepart yang Diganti:
                            <ul style="margin:4px 0 0 18px;padding:0;">
                                <?php foreach ($partsByRepair[(int)$r['id']] as $pRow): ?>
                                    <li>
                                        <?= e($pRow['new_part_name']) ?> (SN: <?= e($pRow['new_part_serial'] ?: '-') ?>)
                                        <?php if ($pRow['old_part_name']): ?>
                                            - Menggantikan: <em><?= e($pRow['old_part_name']) ?></em>
                                        <?php endif; ?>
                                        <?php if ((float)$pRow['part_cost'] > 0): ?>
                                            - Biaya: Rp <?= number_format((float)$pRow['part_cost'], 0, ',', '.') ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($r['photo_after']): ?>
                        <div style="margin-top:8px;">
                            <span class="muted" style="font-size:12px; display:block;">Foto Selesai Pengerjaan:</span>
                            <a href="<?= e(url($r['photo_after'])) ?>" target="_blank" onclick="openInvoiceImageViewer(this.href); return false;">
                                <img src="<?= e(url($r['photo_after'])) ?>" style="max-height:100px;border-radius:4px;border:1px solid #cbd5e1;">
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($r['invoice_photo'])): ?>
                        <div style="margin-top:10px;">
                            <span class="muted" style="font-size:12px; display:block; font-weight:600; color:#334155;">🧾 Bukti Foto / Scan Kwitansi / Invoice:</span>
                            <?php if (str_ends_with(strtolower($r['invoice_photo']), '.pdf')): ?>
                                <a href="<?= e(url($r['invoice_photo'])) ?>" target="_blank" class="btn secondary" style="font-size:11px; padding:3px 8px; margin-top:4px; display:inline-flex; align-items:center; gap:4px; text-decoration:none;">
                                    📄 Buka Dokumen PDF
                                </a>
                            <?php else: ?>
                                <a href="<?= e(url($r['invoice_photo'])) ?>" target="_blank" onclick="openInvoiceImageViewer(this.href); return false;">
                                    <img src="<?= e(url($r['invoice_photo'])) ?>" alt="Kwitansi" style="max-height:100px; border-radius:4px; border:1px solid #cbd5e1; margin-top:4px; display:block; cursor:pointer;">
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
    <?php
    render_invoice_camera_modal_and_script();
    render_footer();
}

/**
 * Modul: Aksi / Update Tiket
 * Proses: Memperbarui status tiket (In Progress, Resolved, Closed) dan menambahkan catatan penanganan (Action Log).
 */
function handle_route_ticket_action(PDO $pdo): void
{
    $user = require_role(['admin', 'corrective_maintenance', 'technician']);
    verify_csrf();
    ensure_corrective_maintenance_schema($pdo);

    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $status = trim((string)($_POST['update_status'] ?? 'in_progress'));
    $techName = trim((string)($_POST['technician_name'] ?? $user['name']));
    $actionType = trim((string)($_POST['action_type'] ?? 'hardware_repair'));
    $rootCause = trim((string)($_POST['root_cause_analysis'] ?? ''));
    $solution = trim((string)($_POST['solution_details'] ?? ''));
    $vendorName = trim((string)($_POST['vendor_name'] ?? ''));
    $vendorInv = trim((string)($_POST['vendor_invoice_no'] ?? ''));
    $repairCost = (float)($_POST['repair_cost'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM corrective_tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        flash('Tiket tidak ditemukan.', 'err');
        redirect_to('tickets');
    }

    // Upload photo after jika ada
    $photoAfter = null;
    if (!empty($_FILES['photo_after']['tmp_name']) && is_uploaded_file($_FILES['photo_after']['tmp_name'])) {
        $uploadDir = __DIR__ . '/../../public/uploads/tickets';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        $ext = strtolower(pathinfo((string)$_FILES['photo_after']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $filename = 'after_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['photo_after']['tmp_name'], $uploadDir . '/' . $filename)) {
                $photoAfter = 'uploads/tickets/' . $filename;
            }
        }
    }

    // Upload invoice / kwitansi photo (via file or live camera)
    $invoicePhoto = save_corrective_invoice_photo($_FILES['invoice_photo'] ?? null, $_POST['invoice_photo_base64'] ?? null);

    if ($solution !== '' || $invoicePhoto !== null || $repairCost > 0) {
        $repStmt = $pdo->prepare("INSERT INTO corrective_repairs (
            ticket_id, technician_name, action_type, root_cause_analysis, solution_details, 
            vendor_name, vendor_invoice_no, repair_cost, photo_after, invoice_photo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $repStmt->execute([
            $ticketId, $techName, $actionType, $rootCause, $solution ?: 'Tindakan perbaikan dan lampiran invoice/kwitansi.',
            $vendorName ?: null, $vendorInv ?: null, $repairCost, $photoAfter, $invoicePhoto
        ]);
        $repairId = (int)$pdo->lastInsertId();

        if ($invoicePhoto) {
            $pdo->prepare("UPDATE corrective_tickets SET invoice_photo = COALESCE(invoice_photo, ?) WHERE id = ?")->execute([$invoicePhoto, $ticketId]);
        }

        // Simpan part replacements
        $parts = $_POST['parts'] ?? [];
        if (is_array($parts)) {
            $partInsert = $pdo->prepare("INSERT INTO corrective_repair_parts (
                repair_id, old_part_name, old_part_serial, new_part_name, new_part_serial, part_cost
            ) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($parts as $pRow) {
                $newName = trim((string)($pRow['new_name'] ?? ''));
                if ($newName !== '') {
                    $oldName = trim((string)($pRow['old_name'] ?? ''));
                    $newSerial = trim((string)($pRow['new_serial'] ?? ''));
                    $cost = (float)($pRow['cost'] ?? 0);
                    $partInsert->execute([$repairId, $oldName ?: null, null, $newName, $newSerial ?: null, $cost]);
                }
            }
        }
    }

    // Update status tiket
    $resolvedAt = ($status === 'resolved' || $status === 'closed') ? date('Y-m-d H:i:s') : $ticket['resolved_at'];
    $closedAt = ($status === 'closed') ? date('Y-m-d H:i:s') : $ticket['closed_at'];

    $pdo->prepare("UPDATE corrective_tickets SET status = ?, assigned_technician_name = ?, resolved_at = ?, closed_at = ? WHERE id = ?")
        ->execute([$status, $techName, $resolvedAt, $closedAt, $ticketId]);

    flash('Tindakan perbaikan berhasil disimpan.');
    redirect_to('ticket_detail', ['id' => $ticketId]);
}

/**
 * Modul: Laporan Perbaikan Korektif
 * Proses: Menampilkan riwayat atau rekap tindakan perbaikan (Corrective Repairs) yang telah dilakukan dari tiket.
 */
function handle_route_corrective_repairs(PDO $pdo): void
{
    $user = require_role(['admin', 'maintenance_admin', 'technician', 'corrective_maintenance']);
    ensure_corrective_maintenance_schema($pdo);

    $sql = "SELECT cr.*, t.ticket_code, t.subject, t.asset_category, t.location_label, t.invoice_photo AS ticket_invoice_photo,
                   ma.maintenance_asset_code, ma.name AS maintenance_asset_name, ai.alias_code AS legacy_code
            FROM corrective_repairs cr
            JOIN corrective_tickets t ON t.id = cr.ticket_id
            LEFT JOIN asset_items ai ON ai.id = t.asset_item_id
            LEFT JOIN maintenance_assets ma ON ma.id = t.maintenance_asset_id
            ORDER BY cr.repaired_at DESC, cr.id DESC";
    $repairs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    render_header('Riwayat Reparasi & Sparepart', $user);
    ?>
    <section class="panel">
        <div class="split">
            <div>
                <h1>Riwayat Reparasi & Penggantian Sparepart</h1>
                <p class="muted">Daftar seluruh tindakan corrective maintenance dan sparepart yang pernah diganti pada unit.</p>
            </div>
            <div class="actions">
                <a class="btn primary" href="<?= route_url('tickets') ?>">Daftar Tiket Aktif</a>
                <a class="btn" href="<?= route_url('export_excel', ['type' => 'corrective_repairs']) ?>">📥 Export Excel</a>
            </div>
        </div>

        <div style="overflow-x:auto;">
        <table class="sortable-table">
            <thead>
                <tr>
                    <th>Tanggal & Tiket</th>
                    <th>Kode Aset</th>
                    <th>Kode Lama (Legacy)</th>
                    <th>Teknisi / Vendor</th>
                    <th>Tindakan & Solusi</th>
                    <th>Sparepart Diganti</th>
                    <th>Biaya Jasa Servis</th>
                    <th style="text-align:center;">Foto Kwitansi / Invoice</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$repairs): ?>
                    <tr><td colspan="7" class="muted" style="text-align:center;padding:24px;">Belum ada riwayat perbaikan.</td></tr>
                <?php else: ?>
                    <?php foreach ($repairs as $r): ?>
                        <?php
                        $pStmt = $pdo->prepare("SELECT * FROM corrective_repair_parts WHERE repair_id = ?");
                        $pStmt->execute([(int)$r['id']]);
                        $parts = $pStmt->fetchAll(PDO::FETCH_ASSOC);
                        $invPhoto = $r['invoice_photo'] ?: ($r['ticket_invoice_photo'] ?? null);
                        $isPdf = $invPhoto && str_ends_with(strtolower($invPhoto), '.pdf');
                        ?>
                        <tr>
                            <td>
                                <?= e(date('d M Y H:i', strtotime($r['repaired_at']))) ?><br>
                                <a href="<?= route_url('ticket_detail', ['id' => $r['ticket_id']]) ?>" style="color:#0284c7;font-weight:600;"><?= e($r['ticket_code']) ?></a>
                            </td>
                            <td>
                                <?= ticket_category_badge($r['asset_category']) ?><br>
                                <?= e($r['maintenance_asset_code'] ?: '-') ?><br>
                                <small class="muted"><?= e(mb_strimwidth((string)$r['maintenance_asset_name'], 0, 35, '...')) ?></small>
                            </td>
                            <td>
                                <?= !empty($r['legacy_code']) ? '<span style="display:inline-block;padding:2px 7px;border-radius:4px;background:#fef3c7;color:#92400e;font-size:12px;font-weight:700;border:1px solid #fde68a;font-family:monospace;">' . e($r['legacy_code']) . '</span>' : '<span class="muted">-</span>' ?>
                            </td>
                            <td>
                                <?= e($r['technician_name']) ?><br>
                                <?= $r['vendor_name'] ? '<span class="muted">Vendor: ' . e($r['vendor_name']) . '</span>' : '' ?>
                            </td>
                            <td>
                                <span class="badge ok"><?= e($r['action_type']) ?></span><br>
                                <?= e(mb_strimwidth((string)$r['solution_details'], 0, 90, '...')) ?>
                            </td>
                            <td>
                                <?php if (!$parts): ?>
                                    <span class="muted">-</span>
                                <?php else: ?>
                                    <?php foreach ($parts as $p): ?>
                                        • <?= e($p['new_part_name']) ?> (Rp <?= number_format((float)$p['part_cost'], 0, ',', '.') ?>)<br>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                Rp <?= number_format((float)$r['repair_cost'], 0, ',', '.') ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if (!$invPhoto): ?>
                                    <span class="muted">-</span>
                                <?php elseif ($isPdf): ?>
                                    <a href="<?= e(url($invPhoto)) ?>" target="_blank" class="btn secondary" style="font-size:11px; padding:4px 8px; display:inline-flex; align-items:center; gap:4px; text-decoration:none;" title="Buka Dokumen PDF">
                                        📄 Buka PDF
                                    </a>
                                <?php else: ?>
                                    <a href="<?= e(url($invPhoto)) ?>" target="_blank" onclick="openInvoiceImageViewer(this.href); return false;" title="Klik untuk memperbesar foto kwitansi" style="display:inline-block; vertical-align:middle;">
                                        <img src="<?= e(url($invPhoto)) ?>" alt="Kwitansi" style="width:48px; height:48px; object-fit:cover; border-radius:6px; border:1px solid #cbd5e1; box-shadow:0 1px 3px rgba(0,0,0,0.1); cursor:pointer;">
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>
    <?php
    render_invoice_camera_modal_and_script();
    render_footer();
}

/**
 * Modul: Daftar Walkaround
 * Proses: Menampilkan log pemantauan keliling (Walkaround) yang dilakukan teknisi tanpa adanya tiket resmi.
 */
function handle_route_walkarounds(PDO $pdo): void
{
    $user = require_regulation('walkarounds', 'view');
    ensure_corrective_maintenance_schema($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'delete_walkaround') {
            require_regulation('walkarounds', 'delete');
            $delId = (int)($_POST['walkaround_id'] ?? 0);
            if ($delId > 0) {
                $pdo->prepare("DELETE FROM asset_walkarounds WHERE id = ?")->execute([$delId]);
                flash("Log walkaround berhasil dihapus.");
            }
            redirect_to('walkarounds');
        }
    }

    $sql = "SELECT w.*, ma.maintenance_asset_code, ma.name AS maintenance_asset_name, t.ticket_code
            FROM asset_walkarounds w
            LEFT JOIN maintenance_assets ma ON ma.id = w.maintenance_asset_id
            LEFT JOIN corrective_tickets t ON t.id = w.ticket_id
            ORDER BY w.inspection_date DESC, w.id DESC";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    render_header('Patroli & Walkaround Aset', $user);
    ?>
    <section class="panel">
        <div class="split">
            <div>
                <h1>Patroli & Walkaround Aset (Quick Inspection)</h1>
                <p class="muted">Pengecekan fisik cepat unit di lapangan. Jika ditemukan kerusakan, sistem otomatis menerbitkan tiket perbaikan.</p>
            </div>
            <div class="actions">
                <?php if (has_regulation('walkarounds', 'create')): ?>
                    <a class="btn primary" href="<?= route_url('walkaround_form') ?>">+ Mulai Walkaround Baru</a>
                <?php endif; ?>
                <a class="btn" href="<?= route_url('tickets') ?>">Daftar Tiket</a>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Kode & Tanggal</th>
                    <th>Inspektur</th>
                    <th>Maintenance Asset</th>
                    <th>Kondisi Fisik</th>
                    <th>Catatan Temuan</th>
                    <th>Tiket Masalah</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="muted" style="text-align:center;padding:24px;">Belum ada log walkaround.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $w): ?>
                        <tr>
                            <td>
                                <?= e($w['walkaround_code']) ?><br>
                                <span class="muted"><?= e($w['inspection_date']) ?></span>
                            </td>
                            <td><?= e($w['inspector_name']) ?></td>
                            <td>
                                <?= e($w['maintenance_asset_code'] ?: '-') ?><br>
                                <small class="muted"><?= e(mb_strimwidth((string)$w['maintenance_asset_name'], 0, 35, '...')) ?></small>
                            </td>
                            <td>
                                <?php if ($w['overall_condition'] === 'good'): ?>
                                    <span class="badge ok">✓ Normal / Bagus</span>
                                <?php elseif ($w['overall_condition'] === 'need_attention'): ?>
                                    <span class="badge" style="background:#fef3c7;color:#92400e">⚠️ Perlu Perhatian</span>
                                <?php else: ?>
                                    <span class="badge danger">✕ Rusak / Kendala</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($w['notes'] ?: '-') ?></td>
                            <td>
                                <?php if ($w['ticket_code']): ?>
                                    <a href="<?= route_url('ticket_detail', ['id' => $w['ticket_id']]) ?>" class="btn danger" style="padding:4px 8px;font-size:12px;">Buka Tiket <?= e($w['ticket_code']) ?></a>
                                <?php else: ?>
                                    <span class="muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= route_url('walkaround_form', ['id' => $w['id']]) ?>" style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; border-radius:6px; text-decoration:none; vertical-align:middle; margin-right:4px;"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a>
                                <?php if (has_regulation('walkarounds', 'delete')): ?>
                                    <form method="post" style="display:inline" onsubmit="return confirm('Batalkan riwayat walkaround ini?')">
                                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete_walkaround">
                                        <input type="hidden" name="walkaround_id" value="<?= (int)$w['id'] ?>">
                                        <button type="submit" style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; background:#fef2f2; color:#ef4444; border:1px solid #fecaca; border-radius:6px; cursor:pointer; vertical-align:middle;"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
    <?php
    render_footer();
}

/**
 * Modul: Form Walkaround
 * Proses: Menangani pencatatan hasil temuan masalah saat teknisi melakukan inspeksi keliling.
 */
function handle_route_walkaround_form(PDO $pdo): void
{
    $user = require_regulation('walkarounds', 'create');
    ensure_corrective_maintenance_schema($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $code = generate_next_walkaround_code($pdo);
        $maintAssetId = (int)($_POST['maintenance_asset_id'] ?? 0) ?: null;
        $inspectorName = trim((string)($_POST['inspector_name'] ?? $user['name']));
        $inspectorNik = trim((string)($_POST['inspector_nik'] ?? ''));
        $date = normalize_date_input((string)($_POST['inspection_date'] ?? date('Y-m-d')));
        $loc = trim((string)($_POST['location_label'] ?? ''));
        $odometer = (int)($_POST['odometer_km'] ?? 0) ?: null;
        $condition = trim((string)($_POST['overall_condition'] ?? 'good'));
        $notes = trim((string)($_POST['notes'] ?? ''));

        $ticketId = null;
        if ($condition !== 'good' && $notes !== '') {
            $tktCode = generate_next_ticket_code($pdo);
            $cat = 'IT';
            $assetItemId = null;
            $pcId = null;

            if ($maintAssetId) {
                $mRow = get_maintenance_asset_unit($pdo, $maintAssetId);
                if ($mRow) {
                    $assetItemId = $mRow['asset_item_id'];
                    $pcId = $mRow['pc_id'] ?: null;
                    $cat = $mRow['asset_category'];
                }
            }

            $stmt = $pdo->prepare("INSERT INTO corrective_tickets (
                ticket_code, asset_category, asset_item_id, maintenance_asset_id, pc_id, location_label, vehicle_odometer_km, 
                reporter_name, reporter_nik, subject, description, priority, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'high', 'open')");
            $stmt->execute([
                $tktCode, $cat, $assetItemId, $maintAssetId, $pcId, $loc, $odometer,
                $inspectorName, $inspectorNik, "Temuan Walkaround: " . mb_strimwidth($notes, 0, 50, '...'),
                $notes
            ]);
            $ticketId = (int)$pdo->lastInsertId();
        }

        $stmt = $pdo->prepare("INSERT INTO asset_walkarounds (
            walkaround_code, maintenance_asset_id, inspector_name, inspector_nik, 
            inspection_date, location_label, odometer_km, overall_condition, notes, ticket_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $code, $maintAssetId, $inspectorName, $inspectorNik,
            $date, $loc, $odometer, $condition, $notes, $ticketId
        ]);

        flash('Log patroli / walkaround berhasil disimpan.' . ($ticketId ? " Tiket kerusakan otomatis diterbitkan (#$tktCode)." : ''));
        redirect_to('walkarounds');
    }

    $maintList = $pdo->query("SELECT ma.id, ma.maintenance_asset_code, ma.name, ma.location_label FROM maintenance_assets ma ORDER BY ma.maintenance_asset_code ASC")->fetchAll(PDO::FETCH_ASSOC);

    render_header('Input Walkaround / Patroli Aset', $user);
    ?>
    <section class="panel">
        <h1>Input Patroli & Walkaround Aset</h1>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

            <div class="grid three">
                <label>No. Maintenance Asset
                    <select name="maintenance_asset_id">
                        <option value="">-- Pilih Maintenance Asset --</option>
                        <?php foreach ($maintList as $m): ?>
                            <option value="<?= (int)$m['id'] ?>">
                                <?= e($m['maintenance_asset_code'] . ' - ' . $m['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Tanggal Pemeriksaan
                    <input type="date" name="inspection_date" value="<?= date('Y-m-d') ?>" required>
                </label>
                <label>Nama Petugas / Inspektur *
                    <input name="inspector_name" value="<?= e($user['name']) ?>" required>
                </label>
            </div>

            <div class="grid two">
                <label>Lokasi / Ruangan
                    <input name="location_label" placeholder="Gedung / Lantai / Ruang">
                </label>
                <label>KM Kendaraan (Jika memeriksa Kendaraan)
                    <input type="number" name="odometer_km" placeholder="Contoh: 12500">
                </label>
            </div>

            <h2>Kondisi Fisik & Temuan</h2>
            <div class="grid two">
                <label>Status Kondisi Fisik *
                    <select name="overall_condition" required>
                        <option value="good">✓ Normal / Berfungsi Baik</option>
                        <option value="need_attention">⚠️ Perlu Perhatian / Pembersihan / Pengecekan Ulang</option>
                        <option value="damaged">✕ Rusak / Tidak Berfungsi (Otomatis Buat Tiket)</option>
                    </select>
                </label>
                <label>Catatan Temuan
                    <input name="notes" placeholder="Catatan kondisi fisik atau kerusakan jika ada...">
                </label>
            </div>

            <div class="actions" style="margin-top:16px;">
                <a class="btn" href="<?= route_url('walkarounds') ?>">Batal</a>
                <button class="btn primary">Simpan Hasil Patroli</button>
            </div>
        </form>
    </section>
    <?php
    render_footer();
}

// -----------------------------------------------------------------------------
// MASTER DATA: JOB DESK CORRECTIVE MAINTENANCE & JENIS TINDAKAN
// -----------------------------------------------------------------------------

/**
 * Modul: Master Tipe Tindakan
 * Proses: Mengelola master data jenis-jenis tindakan (Action Types) perbaikan yang bisa dipilih teknisi.
 */
function handle_route_corrective_action_types(PDO $pdo): void
{
    handle_route_corrective_job_desks($pdo);
}

/**
 * Modul: Master Jobdesk Corrective (Data Master)
 * Proses: Mengelola master rincian pekerjaan dan tindakan perbaikan (Action Types / Job Tasks) Corrective Maintenance.
 *         Daftar pekerjaan (Tasks) tampil di bagian depan (utama) dengan filter Komoditas & Kategori.
 *         Penambahan pekerjaan dilakukan melalui jendela modul (Modal Popup) "+ Tambah Pekerjaan / Job Task".
 */
/**
 * Modul: Master Jobdesk Corrective (Data Master)
 * Proses: Mengelola master rincian pekerjaan dan tindakan perbaikan (Action Types / Job Tasks) Corrective Maintenance.
 *         Daftar pekerjaan (Tasks) tampil di bagian depan (utama) dengan filter Komoditas & Kategori.
 *         Penambahan pekerjaan dilakukan melalui jendela modul (Modal Popup) "+ Tambah Pekerjaan / Job Task".
 */
function handle_route_corrective_job_desks(PDO $pdo): void
{
    $user = require_regulation('corrective_job_desks', 'view');
    ensure_corrective_maintenance_schema($pdo);

    $canCreate = has_regulation('corrective_job_desks', 'create');
    $canEdit = has_regulation('corrective_job_desks', 'edit');
    $canDelete = has_regulation('corrective_job_desks', 'delete');

    // 1. Data Komoditas & Kategori untuk dropdown
    $assetGroups = $pdo->query('SELECT id, group_code, group_name FROM asset_groups ORDER BY group_name ASC')->fetchAll(PDO::FETCH_ASSOC);
    $allAssetTypes = $pdo->query('SELECT id, asset_group_id, type_code, type_name FROM asset_types ORDER BY type_name ASC')->fetchAll(PDO::FETCH_ASSOC);

    // 2. Filter Tabel Daftar Pekerjaan
    $filterGroupId = (int)($_GET['filter_group_id'] ?? 0);
    $filterTypeId = (int)($_GET['filter_type_id'] ?? 0);

    // 3. Edit task ID dari URL (jika ada request edit via link langsung)
    $editTaskId = (int)($_GET['edit_task_id'] ?? 0);
    $editingTask = null;
    if ($editTaskId > 0) {
        $stEdit = $pdo->prepare('SELECT * FROM corrective_action_types WHERE id = ?');
        $stEdit->execute([$editTaskId]);
        $editingTask = $stEdit->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // 4. PROSES POST (TAMBAH, EDIT, TOGGLE, HAPUS)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string)($_POST['action'] ?? '');
        $postFilterGid = (int)($_POST['filter_group_id'] ?? $filterGroupId);
        $postFilterTid = (int)($_POST['filter_type_id'] ?? $filterTypeId);

        // --- TAMBAH PEKERJAAN / JOB TASK CORRECTIVE ---
        if ($action === 'add_task') {
            require_regulation('corrective_job_desks', 'create');
            $groupId = (int)($_POST['asset_group_id'] ?? 0);
            $typeId = (int)($_POST['asset_type_id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));

            if ($groupId <= 0 || $typeId <= 0) {
                flash('Pilih Komoditas (Asset Group) dan Kategori (Asset Type) terlebih dahulu.', 'err');
            } elseif ($title === '') {
                flash('Nama Pekerjaan / Job Task wajib diisi.', 'err');
            } else {
                // Generate action_code unik
                $baseCode = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9_]/', '_', $title)));
                if ($baseCode === '') $baseCode = 'act';
                $code = $baseCode . '_' . substr(md5(uniqid('', true)), 0, 4);

                $gName = '';
                foreach ($assetGroups as $ag) {
                    if ((int)$ag['id'] === $groupId) { $gName = $ag['group_name']; break; }
                }
                $tName = '';
                foreach ($allAssetTypes as $at) {
                    if ((int)$at['id'] === $typeId) { $tName = $at['type_name']; break; }
                }
                $deskName = 'Job Desk Corrective ' . trim($gName . ' ' . $tName);

                $stmtIns = $pdo->prepare('INSERT INTO corrective_action_types (action_code, action_name, job_desk_name, asset_group_id, asset_type_id, estimated_minutes, description, is_active, is_deleted) VALUES (?, ?, ?, ?, ?, 0, ?, 1, 0)');
                $stmtIns->execute([
                    $code,
                    $title,
                    $deskName,
                    $groupId,
                    $typeId,
                    $desc !== '' ? $desc : null,
                ]);

                // Pastikan tabel corrective_job_desks memiliki entri grup & tipe ini untuk kompatibilitas
                try {
                    $chkDesk = $pdo->prepare('SELECT id FROM corrective_job_desks WHERE job_desk_name = ? LIMIT 1');
                    $chkDesk->execute([$deskName]);
                    if (!$chkDesk->fetchColumn()) {
                        $insDesk = $pdo->prepare('INSERT INTO corrective_job_desks (job_desk_name, asset_group_id, asset_type_id, description) VALUES (?, ?, ?, ?)');
                        $insDesk->execute([$deskName, $groupId, $typeId, 'Job Desk Corrective ' . trim($gName . ' ' . $tName)]);
                    }
                } catch (Throwable $ignored) {}

                flash("Pekerjaan '{$title}' berhasil ditambahkan ke Daftar Pekerjaan Corrective.");
            }
            redirect_to('corrective_job_desks', array_filter(['filter_group_id' => $postFilterGid, 'filter_type_id' => $postFilterTid]));
        }

        // --- EDIT PEKERJAAN / JOB TASK CORRECTIVE ---
        if ($action === 'edit_task') {
            require_regulation('corrective_job_desks', 'edit');
            $taskId = (int)($_POST['task_id'] ?? 0);
            $groupId = (int)($_POST['asset_group_id'] ?? 0);
            $typeId = (int)($_POST['asset_type_id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));

            if ($groupId <= 0 || $typeId <= 0) {
                flash('Pilih Komoditas (Asset Group) dan Kategori (Asset Type) terlebih dahulu.', 'err');
            } elseif ($title === '') {
                flash('Nama Pekerjaan / Job Task wajib diisi.', 'err');
            } elseif ($taskId > 0) {
                $stmtUpd = $pdo->prepare('UPDATE corrective_action_types SET action_name=?, description=?, asset_group_id=?, asset_type_id=? WHERE id=?');
                $stmtUpd->execute([
                    $title,
                    $desc !== '' ? $desc : null,
                    $groupId,
                    $typeId,
                    $taskId,
                ]);
                flash("Pekerjaan '{$title}' berhasil diperbarui.");
            }
            redirect_to('corrective_job_desks', array_filter(['filter_group_id' => $postFilterGid, 'filter_type_id' => $postFilterTid]));
        }

        // --- TOGGLE STATUS AKTIF / NON-AKTIF ---
        if ($action === 'toggle_task') {
            require_regulation('corrective_job_desks', 'edit');
            $taskId = (int)($_POST['id'] ?? 0);
            if ($taskId > 0) {
                $pdo->prepare('UPDATE corrective_action_types SET is_active = 1 - is_active WHERE id = ?')->execute([$taskId]);
                flash('Status pekerjaan berhasil diperbarui.');
            }
            redirect_to('corrective_job_desks', array_filter(['filter_group_id' => $postFilterGid, 'filter_type_id' => $postFilterTid]));
        }

        // --- HAPUS PEKERJAAN / JOB TASK (SOFT DELETE) ---
        if ($action === 'delete_task') {
            require_regulation('corrective_job_desks', 'delete');
            $taskId = (int)($_POST['id'] ?? 0);
            if ($taskId > 0) {
                $pdo->prepare('UPDATE corrective_action_types SET is_deleted = 1, deleted_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$taskId]);
                flash('Pekerjaan berhasil dihapus dari Master Jobdesk Corrective.');
            }
            redirect_to('corrective_job_desks', array_filter(['filter_group_id' => $postFilterGid, 'filter_type_id' => $postFilterTid]));
        }
    }

    render_header('Master Jobdesk Corrective', $user);

    // 5. QUERY DAFTAR PEKERJAAN (TIDAK MENAMPILKAN YANG DI-SOFT-DELETE)
    $where = ['(cat.is_deleted = 0 OR cat.is_deleted IS NULL)'];
    $params = [];
    if ($filterGroupId > 0) {
        $where[] = 'cat.asset_group_id = ?';
        $params[] = $filterGroupId;
    }
    if ($filterTypeId > 0) {
        $where[] = 'cat.asset_type_id = ?';
        $params[] = $filterTypeId;
    }
    $whereSql = implode(' AND ', $where);

    $tasksQuery = "SELECT cat.*, g.group_code, g.group_name, t.type_code, t.type_name
                   FROM corrective_action_types cat
                   LEFT JOIN asset_groups g ON g.id = cat.asset_group_id
                   LEFT JOIN asset_types t ON t.id = cat.asset_type_id
                   WHERE {$whereSql}
                   ORDER BY g.group_name ASC, t.type_name ASC, cat.action_name ASC";
    $tasksStmt = $pdo->prepare($tasksQuery);
    $tasksStmt->execute($params);
    $tasksList = $tasksStmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. TAMPILAN UTAMA: DAFTAR PEKERJAAN (TASKS) DI DEPAN
    echo '<section class="panel">'
        . '<div class="split" style="align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">'
        . '  <div>'
        . '    <h1 style="margin:0; font-size:20px; color:#0f172a;">📋 Daftar Pekerjaan (Tasks)</h1>'
        . '    <p class="muted" style="margin:3px 0 0 0; font-size:13px;">Master Jobdesk Corrective: Kelola daftar rincian tugas dan tindakan perbaikan Corrective Maintenance per Komoditas dan Kategori aset.</p>'
        . '  </div>'
        . '  <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">'
        . ($canCreate ? ('<button type="button" class="btn primary" onclick="openCorrectiveJobModal()" style="display:inline-flex; align-items:center; font-weight:700; padding:8px 16px; font-size:13px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">+ Tambah Pekerjaan / Job Task</button>') : '')
        . '    <span class="badge ok" style="font-size:12px; padding:6px 12px;">Total: ' . count($tasksList) . ' Tasks</span>'
        . '    <a class="btn" href="' . route_url('export_excel', array_filter(['type' => 'corrective_job_desks', 'filter_group_id' => $filterGroupId, 'filter_type_id' => $filterTypeId])) . '" style="background:#fff;">📥 Export Excel</a>'
        . '  </div>'
        . '</div>';

    // Form Filter Daftar Pekerjaan
    echo '<div style="margin-bottom:16px; padding:12px 14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">'
        . '<form method="get" id="filterTasksForm" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; margin:0;">'
        . '  <input type="hidden" name="route" value="corrective_job_desks">'
        . '  <label style="flex:1; min-width:200px; margin:0; font-weight:600; font-size:12px; color:#475569;">Filter Komoditas'
        . '    <select id="filterAssetGroup" name="filter_group_id" style="width:100%; margin-top:4px; padding:7px 10px; font-size:13px; background:#fff;">'
        . '      <option value="">-- Semua Komoditas --</option>';
    foreach ($assetGroups as $ag) {
        $sel = ($filterGroupId === (int)$ag['id']) ? ' selected' : '';
        echo '<option value="' . (int)$ag['id'] . '"' . $sel . '>' . e($ag['group_code'] . ' - ' . $ag['group_name']) . '</option>';
    }
    echo '    </select>'
        . '  </label>'
        . '  <label style="flex:1; min-width:200px; margin:0; font-weight:600; font-size:12px; color:#475569;">Filter Kategori'
        . '    <select id="filterAssetType" name="filter_type_id" style="width:100%; margin-top:4px; padding:7px 10px; font-size:13px; background:#fff;">'
        . '      <option value="">-- Semua Kategori --</option>';
    foreach ($allAssetTypes as $at) {
        $sel = ($filterTypeId === (int)$at['id']) ? ' selected' : '';
        echo '<option value="' . (int)$at['id'] . '" data-group-id="' . (int)$at['asset_group_id'] . '"' . $sel . '>' . e($at['type_code'] . ' - ' . $at['type_name']) . '</option>';
    }
    echo '    </select>'
        . '  </label>'
        . '  <button type="submit" class="btn primary" style="padding:8px 16px; font-size:13px;">Filter</button>'
        . (($filterGroupId > 0 || $filterTypeId > 0) ? ('<a class="btn" href="' . route_url('corrective_job_desks') . '" style="padding:8px 14px; font-size:13px;">✕ Reset Filter</a>') : '')
        . '</form>'
        . '</div>';

    // Tabel Daftar Pekerjaan
    if (empty($tasksList)) {
        echo '<div style="padding:40px 20px; text-align:center; background:#fff; border:1px dashed #cbd5e1; border-radius:8px;">'
            . '<p class="muted" style="margin:0 0 8px 0; font-size:14px; font-weight:600;">Tidak ada item pekerjaan / tasks yang sesuai dengan filter.</p>'
            . '<p style="font-size:13px; color:#64748b; margin:0 0 16px 0;">Klik tombol di bawah untuk menambahkan pekerjaan baru ke daftar.</p>'
            . ($canCreate ? ('<button type="button" class="btn primary" onclick="openCorrectiveJobModal()">+ Tambah Pekerjaan / Job Task</button>') : '')
            . '</div>';
    } else {
        echo '<div style="overflow-x:auto;"><table style="margin:0;">'
            . '<thead><tr>'
            . '<th style="width:40px; text-align:center;">No</th>'
            . '<th style="width:160px;">Komoditas</th>'
            . '<th style="width:160px;">Kategori</th>'
            . '<th>Nama Pekerjaan / Job Task</th>'
            . '<th>Keterangan / SOP</th>'
            . ($canEdit || $canDelete ? '<th style="width:130px; text-align:center; white-space:nowrap;">Aksi</th>' : '')
            . '</tr></thead><tbody>';

        $no = 1;
        foreach ($tasksList as $task) {
            $tId = (int)$task['id'];
            $tTitle = $task['action_name'];
            $tDesc = trim((string)($task['description'] ?? ''));
            $tActive = !empty($task['is_active']);
            $gId = (int)($task['asset_group_id'] ?? 0);
            $tTypeId = (int)($task['asset_type_id'] ?? 0);
            $gLabel = !empty($task['group_name']) ? ($task['group_code'] . ' - ' . $task['group_name']) : '<span class="muted">(Semua)</span>';
            $tLabel = !empty($task['type_name']) ? ($task['type_code'] . ' - ' . $task['type_name']) : '<span class="muted">(Semua)</span>';

            $toggleBtn = $canEdit
                ? ('<form method="post" style="display:inline-flex; margin:0; padding:0; vertical-align:middle;"><input type="hidden" name="csrf" value="' . csrf_token() . '"><input type="hidden" name="action" value="toggle_task"><input type="hidden" name="id" value="' . $tId . '"><input type="hidden" name="filter_group_id" value="' . $filterGroupId . '"><input type="hidden" name="filter_type_id" value="' . $filterTypeId . '"><button type="submit" class="btn-icon" style="padding:6px; border:1px solid ' . ($tActive ? '#86efac' : '#cbd5e1') . '; border-radius:6px; background:' . ($tActive ? '#f0fdf4' : '#f8fafc') . '; color:' . ($tActive ? '#16a34a' : '#94a3b8') . '; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; box-shadow:0 1px 2px rgba(0,0,0,0.05); flex-shrink:0;" title="' . ($tActive ? 'Nonaktifkan Pekerjaan' : 'Aktifkan Pekerjaan') . '">' . ($tActive ? '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>' : '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>') . '</button></form>')
                : '';

            $editBtn = $canEdit
                ? ('<button type="button" class="btn-icon" style="padding:6px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; color:#0284c7; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; box-shadow:0 1px 2px rgba(0,0,0,0.05); flex-shrink:0; vertical-align:middle;" onclick="editCorrectiveJobModal(this)" data-id="' . $tId . '" data-group-id="' . $gId . '" data-type-id="' . $tTypeId . '" data-title="' . e($tTitle) . '" data-desc="' . e($tDesc) . '" title="Edit Pekerjaan"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></button>')
                : '';

            $delBtn = $canDelete
                ? ('<form method="post" style="display:inline-flex; margin:0; padding:0; vertical-align:middle;" onsubmit="return confirm(&quot;Yakin hapus pekerjaan ini? Data akan diarsipkan (soft delete).&quot;);"><input type="hidden" name="csrf" value="' . csrf_token() . '"><input type="hidden" name="action" value="delete_task"><input type="hidden" name="id" value="' . $tId . '"><input type="hidden" name="filter_group_id" value="' . $filterGroupId . '"><input type="hidden" name="filter_type_id" value="' . $filterTypeId . '"><button type="submit" class="btn-icon danger" style="padding:6px; border:1px solid #fecaca; border-radius:6px; background:#fef2f2; color:#dc2626; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; box-shadow:0 1px 2px rgba(0,0,0,0.05); flex-shrink:0;" title="Hapus Pekerjaan (Soft Delete)"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg></button></form>')
                : '';

            echo '<tr' . (!$tActive ? ' style="opacity:0.55; background:#f8fafc;"' : '') . '>'
                . '<td style="text-align:center; font-weight:600; color:#64748b;">' . $no++ . '</td>'
                . '<td><strong>' . $gLabel . '</strong></td>'
                . '<td>' . $tLabel . '</td>'
                . '<td><strong>' . e($tTitle) . '</strong>' . (!$tActive ? ' <span class="badge" style="font-size:10px; background:#f1f5f9; color:#64748b; margin-left:6px;">Tidak Aktif</span>' : '') . '</td>'
                . '<td>' . ($tDesc !== '' ? ('<span style="font-size:12px; color:#475569;">' . e($tDesc) . '</span>') : '<span class="muted">-</span>') . '</td>';

            if ($canEdit || $canDelete) {
                echo '<td style="text-align:center; white-space:nowrap;"><div style="display:inline-flex; flex-direction:row; flex-wrap:nowrap; gap:6px; justify-content:center; align-items:center; white-space:nowrap;">' . $editBtn . $toggleBtn . $delBtn . '</div></td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';

    // 7. WINDOWS MODUL: TAMBAH / EDIT PEKERJAAN CORRECTIVE (MODAL POPUP)
    echo '<div id="modalCorrectiveJob" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.65); backdrop-filter:blur(3px); z-index:99999; align-items:center; justify-content:center; padding:16px; box-sizing:border-box;">'
        . '<div style="background:#fff; border-radius:12px; padding:24px; width:100%; max-width:620px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35); max-height:92vh; overflow-y:auto; position:relative;">'
        . '<button type="button" onclick="closeCorrectiveJobModal()" style="position:absolute; top:18px; right:18px; background:none; border:none; font-size:22px; cursor:pointer; color:#64748b; line-height:1;" title="Tutup Window">&times;</button>'
        . '<h2 id="modalCorrectiveJobTitle" style="margin:0 0 6px 0; font-size:18px; color:#1e40af;">+ Tambah Pekerjaan / Job Task</h2>'
        . '<p class="muted" style="margin:0 0 16px 0; font-size:13px;">Pilih Komoditas dan Kategori aset, tentukan rincian pekerjaan, lalu simpan pekerjaan.</p>'
        . '<form method="post" id="formModalCorrectiveJob">'
        . '<input type="hidden" name="csrf" value="' . csrf_token() . '">'
        . '<input type="hidden" name="action" id="modalCorrectiveJobAction" value="add_task">'
        . '<input type="hidden" name="task_id" id="modalCorrectiveJobTaskId" value="">'
        . '<input type="hidden" name="filter_group_id" value="' . $filterGroupId . '">'
        . '<input type="hidden" name="filter_type_id" value="' . $filterTypeId . '">'
        . '<div class="grid two" style="margin-bottom:12px;">'
        . '  <label style="font-weight:600; font-size:13px; color:#334155;">Komoditas *'
        . '    <select id="formCorrectiveGroup" name="asset_group_id" required style="width:100%; margin-top:4px; padding:9px 12px; font-weight:600; background:#fff;">'
        . '      <option value="">-- Pilih Komoditas --</option>';
    foreach ($assetGroups as $ag) {
        echo '<option value="' . (int)$ag['id'] . '">' . e($ag['group_code'] . ' - ' . $ag['group_name']) . '</option>';
    }
    echo '    </select>'
        . '  </label>'
        . '  <label style="font-weight:600; font-size:13px; color:#334155;">Kategori *'
        . '    <select id="formCorrectiveType" name="asset_type_id" required style="width:100%; margin-top:4px; padding:9px 12px; font-weight:600; background:#fff;">'
        . '      <option value="">-- Pilih Kategori --</option>';
    foreach ($allAssetTypes as $at) {
        echo '<option value="' . (int)$at['id'] . '" data-group-id="' . (int)$at['asset_group_id'] . '">' . e($at['type_code'] . ' - ' . $at['type_name']) . '</option>';
    }
    echo '    </select>'
        . '  </label>'
        . '</div>'
        . '<div style="margin-bottom:12px;">'
        . '  <label style="font-weight:600; font-size:13px; color:#334155; display:block;">Nama Pekerjaan / Job Task *'
        . '    <input type="text" id="modalCorrectiveTitleInput" name="title" required placeholder="Contoh: Penggantian Power Supply / Komponen" style="width:100%; margin-top:4px; padding:9px 12px; font-size:14px; font-weight:600; box-sizing:border-box;">'
        . '  </label>'
        . '</div>'
        . '<label style="font-weight:600; font-size:13px; color:#334155; margin-bottom:18px; display:block;">Keterangan / SOP (Opsional)'
        . '  <textarea id="modalCorrectiveDescInput" name="description" rows="3" placeholder="Instruksi pengerjaan teknis atau SOP penanganan..." style="width:100%; margin-top:4px; padding:9px 12px; font-size:13px; box-sizing:border-box;"></textarea>'
        . '</label>'
        . '<div class="actions" style="display:flex; justify-content:flex-end; gap:10px;">'
        . '  <button type="button" class="btn" onclick="closeCorrectiveJobModal()" style="padding:10px 18px;">Batal</button>'
        . '  <button type="submit" id="modalCorrectiveSubmitBtn" class="btn primary" style="padding:10px 22px; font-size:14px; font-weight:700;">Simpan Pekerjaan</button>'
        . '</div>'
        . '</form>'
        . '</div></div>';

    // 8. JAVASCRIPT: LOGIKA MODAL POPUP & DINAMISASI KATEGORI
    echo '<script>
(function(){
    var modal = document.getElementById("modalCorrectiveJob");
    var modalTitle = document.getElementById("modalCorrectiveJobTitle");
    var modalAction = document.getElementById("modalCorrectiveJobAction");
    var modalTaskId = document.getElementById("modalCorrectiveJobTaskId");
    var modalSubmitBtn = document.getElementById("modalCorrectiveSubmitBtn");
    var formGroup = document.getElementById("formCorrectiveGroup");
    var formType = document.getElementById("formCorrectiveType");
    var taskTitleInput = document.getElementById("modalCorrectiveTitleInput");
    var taskDescInput = document.getElementById("modalCorrectiveDescInput");

    function syncTypeOptions(gSel, tSel, autoReset) {
        var selectedGid = gSel ? gSel.value : "";
        var currentTid = tSel ? tSel.value : "";
        var hasMatch = false;

        if (!tSel) return;
        Array.from(tSel.options).forEach(function(opt, idx) {
            if (idx === 0) {
                opt.style.display = "";
                return;
            }
            var optGid = opt.getAttribute("data-group-id");
            var show = (!selectedGid || optGid === selectedGid);
            opt.style.display = show ? "" : "none";
            if (show && opt.value === currentTid) {
                hasMatch = true;
            }
        });

        if (autoReset && !hasMatch && selectedGid !== "") {
            tSel.value = "";
        }
    }

    if (formGroup && formType) {
        formGroup.addEventListener("change", function() {
            syncTypeOptions(formGroup, formType, true);
        });
    }

    var filterGroup = document.getElementById("filterAssetGroup");
    var filterType = document.getElementById("filterAssetType");
    if (filterGroup && filterType) {
        filterGroup.addEventListener("change", function() {
            syncTypeOptions(filterGroup, filterType, false);
        });
        syncTypeOptions(filterGroup, filterType, false);
    }

    window.openCorrectiveJobModal = function() {
        if (!modal) return;
        modalTitle.textContent = "+ Tambah Pekerjaan / Job Task";
        modalAction.value = "add_task";
        modalTaskId.value = "";
        modalSubmitBtn.textContent = "Simpan Pekerjaan";

        var currentFilterGid = "' . $filterGroupId . '";
        var currentFilterTid = "' . $filterTypeId . '";
        if (formGroup && currentFilterGid && currentFilterGid !== "0") {
            formGroup.value = currentFilterGid;
            syncTypeOptions(formGroup, formType, false);
            if (formType && currentFilterTid && currentFilterTid !== "0") {
                formType.value = currentFilterTid;
            }
        } else if (formGroup) {
            formGroup.selectedIndex = 0;
            syncTypeOptions(formGroup, formType, true);
        }

        if (taskTitleInput) taskTitleInput.value = "";
        if (taskDescInput) taskDescInput.value = "";

        modal.style.display = "flex";
        if (formGroup) formGroup.focus();
    };

    window.editCorrectiveJobModal = function(btn) {
        if (!modal || !btn) return;
        var id = btn.getAttribute("data-id") || "";
        var gid = btn.getAttribute("data-group-id") || "";
        var tid = btn.getAttribute("data-type-id") || "";
        var title = btn.getAttribute("data-title") || "";
        var desc = btn.getAttribute("data-desc") || "";

        modalTitle.textContent = "Edit Pekerjaan / Job Task #" + id;
        modalAction.value = "edit_task";
        modalTaskId.value = id;
        modalSubmitBtn.textContent = "Perbarui Pekerjaan";

        if (formGroup) {
            formGroup.value = gid;
            syncTypeOptions(formGroup, formType, false);
        }
        if (formType) {
            formType.value = tid;
        }
        if (taskTitleInput) taskTitleInput.value = title;
        if (taskDescInput) taskDescInput.value = desc;

        modal.style.display = "flex";
        if (taskTitleInput) taskTitleInput.focus();
    };

    window.closeCorrectiveJobModal = function() {
        if (modal) modal.style.display = "none";
    };

    if (modal) {
        modal.addEventListener("click", function(e) {
            if (e.target === modal) {
                closeCorrectiveJobModal();
            }
        });
    }

    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape" && modal && modal.style.display === "flex") {
            closeCorrectiveJobModal();
        }
    });

    ' . ($editingTask ? '
    var editBtnDummy = {
        getAttribute: function(name) {
            if (name === "data-id") return "' . (int)$editingTask['id'] . '";
            if (name === "data-group-id") return "' . (int)$editingTask['asset_group_id'] . '";
            if (name === "data-type-id") return "' . (int)$editingTask['asset_type_id'] . '";
            if (name === "data-title") return ' . json_encode((string)$editingTask['action_name']) . ';
            if (name === "data-desc") return ' . json_encode((string)($editingTask['description'] ?? '')) . ';
            return "";
        }
    };
    window.editCorrectiveJobModal(editBtnDummy);
    ' : '') . '
})();
</script>';

    render_footer();
}

/**
 * Modul: Bengkel / Workshop Master
 * Proses: Menampilkan daftar bengkel/workshop.
 */
function handle_route_corrective_workshops(PDO $pdo): void
{
    $user = require_regulation('corrective_admin', 'view');
    ensure_corrective_maintenance_schema($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
        verify_csrf();
        require_regulation('corrective_admin', 'delete');
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId > 0) {
            $inUse = 0;
            if (db_table_exists($pdo, 'tickets') && db_column_exists($pdo, 'tickets', 'handled_by_workshop_id')) {
                $inUse = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE handled_by_workshop_id = {$delId}")->fetchColumn();
            }
            if ($inUse > 0) {
                flash("Bengkel tidak dapat dihapus karena digunakan pada {$inUse} tiket perbaikan.", 'err');
            } else {
                $pdo->prepare("DELETE FROM corrective_workshops WHERE id = ?")->execute([$delId]);
                flash("Bengkel berhasil dihapus.");
            }
        }
        redirect_to('corrective_workshops');
    }

    $stmt = $pdo->query("SELECT * FROM corrective_workshops ORDER BY id DESC");
    $rows = $stmt->fetchAll();

    render_header('Master Bengkel / Workshop', $user);
    echo '<section class="panel">';
    echo '<div class="split"><h1>Master Bengkel / Workshop</h1>';
    if (has_regulation('corrective_admin', 'create', $user)) {
        echo btn_icon_add(route_url('corrective_workshop_form'), 'Tambah Data Bengkel', 'Tambah Data Bengkel');
    }
    echo '</div>';
    echo '<div style="overflow-x:auto;"><table class="data-table"><thead><tr><th>ID</th><th>Kode</th><th>Nama Bengkel</th><th>Alamat</th><th>Aksi</th></tr></thead><tbody>';
    if (empty($rows)) {
        echo '<tr><td colspan="5" class="muted" style="text-align:center;">Belum ada data bengkel / workshop.</td></tr>';
    } else {
        foreach ($rows as $r) {
            $actions = '';
            if (has_regulation('corrective_admin', 'update', $user)) {
                $actions .= btn_icon_edit(route_url('corrective_workshop_form', ['id' => $r['id']]), 'Edit Data Bengkel');
            }
            if (has_regulation('corrective_admin', 'delete', $user)) {
                $actions .= ($actions ? ' ' : '') . btn_icon_delete(route_url('corrective_workshops'), (int)$r['id'], 'Hapus bengkel ' . addslashes((string)$r['workshop_name']) . '?', 'Hapus Data Bengkel');
            }
            echo '<tr><td>' . (int)$r['id'] . '</td><td><code>' . e($r['workshop_code']) . '</code></td><td><strong>' . e($r['workshop_name']) . '</strong></td><td>' . e($r['address'] ?: '-') . '</td><td><div class="actions" style="display:flex;gap:4px;align-items:center;">' . ($actions ?: '-') . '</div></td></tr>';
        }
    }
    echo '</tbody></table></div></section>';
    render_footer();
}

/**
 * Modul: Bengkel / Workshop Form
 * Proses: Tambah / Edit bengkel.
 */
function handle_route_corrective_workshop_form(PDO $pdo): void
{
    ensure_corrective_maintenance_schema($pdo);
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $user = require_regulation('corrective_admin', 'update');
    } else {
        $user = require_regulation('corrective_admin', 'create');
    }

    $row = ['workshop_name' => '', 'address' => ''];
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM corrective_workshops WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: $row;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $name = trim((string)($_POST['workshop_name'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        if ($name === '') {
            flash('Nama bengkel harus diisi.', 'err');
        } else {
            if ($id > 0) {
                $pdo->prepare("UPDATE corrective_workshops SET workshop_name = ?, address = ? WHERE id = ?")->execute([$name, $address, $id]);
                flash("Bengkel berhasil diupdate.");
                redirect_to('corrective_workshops');
            } else {
                // Auto generate code WKS-001
                $cStmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(workshop_code, 5) AS UNSIGNED)) FROM corrective_workshops");
                $max = (int)$cStmt->fetchColumn();
                $code = 'WKS-' . str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT);
                $pdo->prepare("INSERT INTO corrective_workshops (workshop_code, workshop_name, address) VALUES (?, ?, ?)")->execute([$code, $name, $address]);
                flash("Bengkel {$code} berhasil ditambahkan.");
                redirect_to('corrective_workshops');
            }
        }
    }

    render_header($id > 0 ? 'Edit Bengkel' : 'Tambah Bengkel', $user);
    echo '<section class="panel" style="max-width:600px;"><h1>' . ($id > 0 ? 'Edit' : 'Tambah') . ' Bengkel</h1>';
    echo '<form method="post"><input type="hidden" name="csrf" value="' . csrf_token() . '">';
    echo '<div class="gridOne"><label>Nama Bengkel / Workshop *<input name="workshop_name" required value="' . e($row['workshop_name']) . '" placeholder="Contoh: Bengkel Utama Sejahtera / PT Astra Auto"></label></div>';
    echo '<div class="gridOne" style="margin-top:12px;"><label>Alamat Bengkel<textarea name="address" style="min-height:80px;" placeholder="Alamat lengkap bengkel / workshop...">' . e($row['address'] ?? '') . '</textarea></label></div>';
    echo '<div style="margin-top:20px;"><button class="btn primary">Simpan Data Bengkel</button> ';
    echo '<a class="btn" href="' . route_url('corrective_workshops') . '">Batal</a></div>';
    echo '</form></section>';
    render_footer();
}
function handle_route_api_corrective_data(PDO $pdo): void
{
    require_login();
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? '';

    if ($action === 'get_types') {
        $groupId = (int)($_GET['group_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, type_code, type_name FROM asset_types WHERE asset_group_id = ? AND is_active=1 ORDER BY type_name");
        $stmt->execute([$groupId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'get_assets') {
        $groupId = (int)($_GET['group_id'] ?? 0);
        $typeId = (int)($_GET['type_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, asset_code, alias_code, asset_name, location_label, custodian_name, asset_mode FROM asset_items WHERE asset_group_id = ? AND asset_type_id = ? AND status NOT IN ('inactive', 'deleted') ORDER BY (asset_mode = 'group') DESC, asset_code ASC");
        $stmt->execute([$groupId, $typeId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'get_actions') {
        $groupId = (int)($_GET['group_id'] ?? 0);
        $typeId = (int)($_GET['type_id'] ?? 0);
        
        // Find Job Desk
        $jdStmt = $pdo->prepare("SELECT id, job_desk_name FROM corrective_job_desks WHERE asset_group_id = ? AND (asset_type_id = ? OR asset_type_id IS NULL OR asset_type_id = 0) ORDER BY CASE WHEN asset_type_id = ? THEN 0 ELSE 1 END, id ASC LIMIT 1");
        $jdStmt->execute([$groupId, $typeId, $typeId]);
        $jdRow = $jdStmt->fetch(PDO::FETCH_ASSOC);

        if (!$jdRow) {
            echo json_encode(['desk_name' => null, 'actions' => []]);
            exit;
        }

        $actStmt = $pdo->prepare("SELECT id, action_name AS action_title FROM corrective_action_types WHERE job_desk_name = ? AND is_active = 1 AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY action_name");
        $actStmt->execute([$jdRow['job_desk_name']]);
        echo json_encode(['desk_name' => $jdRow['job_desk_name'], 'actions' => $actStmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    echo json_encode([]);
    exit;
}