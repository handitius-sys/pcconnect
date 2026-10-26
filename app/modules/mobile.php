<?php

declare(strict_types=1);

/**
 * ============================================================================
 * MODUL MOBILE PREVENTIVE MAINTENANCE (ANTARMUKA TEKNISI LAPANGAN)
 * ============================================================================
 * File: app/modules/mobile.php
 *
 * Mengelola seluruh alur pengerjaan Preventive Maintenance melalui perangkat mobile:
 * 1. Dashboard & Daftar Jadwal Teknisi (`handle_route_mobile_dashboard` & `handle_route_mobile_job`).
 * 2. Scan QR & Validasi Geolocation (`handle_route_mobile_scan`):
 *    - Memverifikasi kesesuaian QR unit aset/bundle dan radius koordinat GPS lokasi unit.
 * 3. Eksekusi Pengerjaan & Upload Foto (`handle_route_mobile_maintenance_do`):
 *    - Tahap "Ambil Foto Sebelum" (`save_before_photo`) dilengkapi indikator Progress Bar & Persentase
 *      Real-Time (`0%` - `100%`) via `XMLHttpRequest` (`xhr.upload.onprogress`) untuk memantau status upload
 *      dan tahapan verifikasi AI Challenge Code agar pengguna tahu proses berjalan (tidak hang).
 *    - Tahap Checklist Pekerjaan & "Ambil Foto Sesudah" (`complete_with_after_photo`) disertai perhitungan
 *      durasi pengerjaan real-time dan penguncian laporan (`locked_at`).
 */

function schedule_has_event(PDO $pdo, int $scheduleId, string $eventType): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM maintenance_timeline WHERE schedule_id=? AND event_type=?');
    $stmt->execute([$scheduleId, $eventType]);
    return (int)$stmt->fetchColumn() > 0;
}

function maintenance_scan_token(array $schedule, string $phase): string
{
    $seed = implode('|', [
        (string)($schedule['asset_id'] ?? $schedule['pc_id'] ?? $schedule['printer_id'] ?? ''),
        (string)$schedule['id'],
        (string)$schedule['scheduled_date'],
        date('Y-m-d'),
        $phase,
        session_id(),
    ]);
    return strtoupper(substr(hash('sha256', $seed), 0, 10));
}

function ensure_photo_challenge(PDO $pdo, int $scheduleId, ?string $existingCode = null): string
{
    $existingCode = trim((string)$existingCode);
    if ($existingCode !== '' && strlen($existingCode) >= 4) {
        return $existingCode;
    }
    $code = (string)random_int(1000, 9999);
    try {
        $pdo->prepare('UPDATE maintenance_schedules SET photo_challenge_code=?, photo_challenge_generated_at=? WHERE id=?')->execute([
            $code,
            date('Y-m-d H:i:s'),
            $scheduleId,
        ]);
    } catch (Throwable $ignored) {
    }
    return $code;
}

function sync_completed_mobile_schedules(PDO $pdo, int $technicianId): void
{
    try {
        $pdo->prepare("UPDATE maintenance_schedules s SET s.status='completed', s.completed_at=COALESCE(s.completed_at,NOW()), s.locked_at=COALESCE(s.locked_at,NOW()) WHERE s.technician_id=? AND s.status<>'completed' AND EXISTS (SELECT 1 FROM maintenance_timeline t WHERE t.schedule_id=s.id AND t.event_type='qr_end_scan')")->execute([$technicianId]);
        $pdo->prepare("UPDATE maintenance_reports r JOIN maintenance_schedules s ON s.id=r.schedule_id SET r.locked_at=COALESCE(r.locked_at,NOW()) WHERE s.technician_id=? AND s.status<>'completed'")->execute([$technicianId]);
        $pdo->prepare("UPDATE maintenance_schedules s SET s.status='completed', s.completed_at=COALESCE(s.completed_at,NOW()), s.locked_at=COALESCE(s.locked_at,NOW()) WHERE s.technician_id=? AND s.status<>'completed' AND EXISTS (SELECT 1 FROM maintenance_reports r WHERE r.schedule_id=s.id)")->execute([$technicianId]);
    } catch (Throwable $ignored) {
    }
}

function expire_stale_mobile_work_or_logout(PDO $pdo, array $user, ?int $scheduleId = null): void
{
    if (($user['role'] ?? '') !== 'technician') {
        return;
    }
    $sql = "SELECT id FROM maintenance_schedules WHERE technician_id=? AND status<>'completed' AND arrival_at IS NOT NULL AND arrival_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $params = [(int)$user['id']];
    if ($scheduleId !== null && $scheduleId > 0) {
        $sql .= ' AND id=?';
        $params[] = $scheduleId;
    }
    $sql .= ' ORDER BY arrival_at ASC LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $expiredId = (int)($stmt->fetchColumn() ?: 0);
    if ($expiredId <= 0) {
        return;
    }

    try {
        $pdo->prepare('UPDATE schedule_jobs SET is_done=0, done_at=NULL, note=NULL WHERE schedule_id=?')->execute([$expiredId]);
    } catch (Throwable $ignored) {
    }
    try {
        $pdo->prepare('DELETE FROM maintenance_reports WHERE schedule_id=? AND locked_at IS NULL')->execute([$expiredId]);
    } catch (Throwable $ignored) {
    }
    add_timeline($pdo, $expiredId, 'mobile_session_expired', 'Scan mulai expired karena teknisi tidak scan selesai dalam 1 jam. Schedule di-reset dan foto harus diambil ulang.', (int)$user['id']);
    $pdo->prepare("UPDATE maintenance_schedules SET status='scheduled', arrival_at=NULL, arrival_ip=NULL, arrival_user_agent=NULL, arrival_lat=NULL, arrival_lng=NULL, arrival_browser=NULL, photo_challenge_code=NULL, photo_challenge_generated_at=NULL WHERE id=? AND status<>'completed'")->execute([$expiredId]);
    unset($_SESSION['user_id'], $_SESSION['pending_mobile_code']);
    flash('Sesi maintenance expired karena lebih dari 1 jam belum scan selesai. Silakan login ulang dan mulai dari Scan Mulai. Foto maintenance harus diambil ulang.', 'err');
    redirect_to('login', ['mode' => 'mobile']);
}

function smart_maintenance_card(array $schedule): string
{
    $hardware = $schedule['general_specs'] ? json_decode((string)$schedule['general_specs'], true) : [];
    $benchmark = $schedule['benchmark'] ? json_decode((string)$schedule['benchmark'], true) : [];
    $score = 100;
    if ((float)($hardware['ram_gb'] ?? 8) < 8) {
        $score -= 15;
    }
    if (isset($benchmark['storage_write_mbps']) && (float)$benchmark['storage_write_mbps'] < 80) {
        $score -= 20;
    }
    if (isset($benchmark['cpu_quick_ms']) && (float)$benchmark['cpu_quick_ms'] > 2500) {
        $score -= 15;
    }
    $html = '<section class="panel"><h2>Smart Maintenance</h2><div class="grid two"><div><strong>Health Score</strong><br>' . e(max(0, $score)) . '%</div><div><strong>RAM</strong><br>' . e($hardware['ram_gb'] ?? '-') . ' GB</div><div><strong>Storage</strong><br>' . e(isset($benchmark['storage_write_mbps']) ? round((float)$benchmark['storage_write_mbps'], 1) . ' MB/s' : '-') . '</div><div><strong>Benchmark</strong><br>' . e(isset($benchmark['cpu_quick_ms']) ? round((float)$benchmark['cpu_quick_ms'], 1) . ' ms' : '-') . '</div></div>';
    $html .= '<p><strong>AI</strong><br>' . e($schedule['ai_recommendation'] ?: 'Belum ada rekomendasi PcNalisa.') . '</p></section>';
    return $html;
}

/**
 * Modul Mobile PWA: Dashboard
 * Proses: Menampilkan ringkasan atau antarmuka utama pada aplikasi progresif (PWA) di HP pengguna.
 */
function mobile_dashboard(PDO $pdo): void
{
    $user = require_role(['admin', 'technician']);
    cancel_expired_maintenance_schedules($pdo);
    expire_stale_mobile_work_or_logout($pdo, $user);
    sync_completed_mobile_schedules($pdo, (int)$user['id']);
    render_mobile_header('Dashboard Teknisi', $user);
    $stmt = $pdo->prepare("SELECT COUNT(*) total, 
        SUM(status='completed') done, 
        SUM(status NOT IN ('completed', 'cancelled') AND arrival_at IS NOT NULL AND EXISTS (SELECT 1 FROM maintenance_reports r WHERE r.schedule_id=maintenance_schedules.id)) waiting_end_scan, 
        SUM(status NOT IN ('completed', 'cancelled') AND arrival_at IS NOT NULL AND NOT EXISTS (SELECT 1 FROM maintenance_reports r WHERE r.schedule_id=maintenance_schedules.id)) progress, 
        SUM(status NOT IN ('completed', 'cancelled') AND arrival_at IS NULL) pending 
        FROM maintenance_schedules 
        WHERE technician_id=? AND scheduled_date<=CURDATE() AND status NOT IN ('cancelled')");
    $stmt->execute([$user['id']]);
    $stats = $stmt->fetch();
    $openTotal = (int)($stats['progress'] ?? 0) + (int)($stats['waiting_end_scan'] ?? 0) + (int)($stats['pending'] ?? 0);
    
    $colorIdx = 0;
    $renderCard = function($number, $label, $iconPath) use (&$colorIdx) {
        $themes = [
            ['#eff6ff', '#1e3a8a', '#3b82f6'], // blue
            ['#f0fdf4', '#14532d', '#22c55e'], // green
            ['#fffbeb', '#78350f', '#f59e0b'], // yellow
            ['#f3e8ff', '#581c87', '#a855f7'], // purple
            ['#fef2f2', '#7f1d1d', '#ef4444'], // red
        ];
        $t = $themes[$colorIdx % count($themes)];
        $colorIdx++;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="' . $t[2] . '"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="' . $iconPath . '"/></svg>';
        $bgImage = "data:image/svg+xml;base64," . base64_encode($svg);

        return '<div class="stat" style="background-color: ' . $t[0] . '; color: ' . $t[1] . '; border-left: 4px solid ' . $t[2] . '; position: relative; overflow: hidden; padding: 14px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">'
             . '<div style="position: absolute; right: -10px; top: -10px; width: 80px; height: 80px; opacity: 0.15; background-image: url(\'' . $bgImage . '\'); background-size: contain; background-repeat: no-repeat; transform: rotate(-10deg);"></div>'
             . '<div style="position: relative; z-index: 1;">'
             . '<strong style="font-size: 26px; display: block; margin-bottom: 2px; line-height: 1;">' . e($number) . '</strong>'
             . '<span style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8;">' . e($label) . '</span>'
             . '</div></div>';
    };

    echo '<section class="panel"><h1>Dashboard</h1><p class="muted">Halo, ' . e($user['name']) . '</p>';
    echo '<div class="grid two">';
    echo $renderCard($openTotal, 'Job Terbuka', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'); // clipboard
    echo $renderCard($stats['done'] ?? 0, 'Done', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'); // check-circle
    echo $renderCard($stats['progress'] ?? 0, 'Progress', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'); // clock
    echo $renderCard($stats['pending'] ?? 0, 'Pending', 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'); // warning
    echo '</div></section>';
    echo '<section class="panel"><div class="actions"><a class="btn primary" href="' . route_url('mobile_schedule') . '">List PC Schedule</a><a class="btn" href="' . route_url('logout') . '">Logout</a></div></section>';
    render_mobile_footer();
}

/**
 * Modul Mobile PWA: Jadwal
 * Proses: Menampilkan jadwal maintenance atau penugasan hari ini untuk pengguna di lapangan.
 */
function mobile_schedule(PDO $pdo): void
{
    $user = require_role(['admin', 'technician']);
    cancel_expired_maintenance_schedules($pdo);
    expire_stale_mobile_work_or_logout($pdo, $user);
    sync_completed_mobile_schedules($pdo, (int)$user['id']);
    render_mobile_header('Schedule', $user);
    $stmt = $pdo->prepare("SELECT s.*, 
        COALESCE(ai.asset_code, s.pc_id, s.printer_id, CONCAT('Unit #', s.id)) AS asset_id, 
        COALESCE(ai.custodian_name, ma.owner_name, p.owner_name, pr.printer_name, '-') AS owner_name, 
        COALESCE(ai.asset_name, ma.name, p.computer_name, pr.printer_name, pr.location, '-') AS computer_name, 
        COALESCE(ai.location_label, ma.location_label, p.location_label, pr.location, '') AS location_label,
        ai.asset_mode,
        EXISTS (SELECT 1 FROM maintenance_reports r WHERE r.schedule_id=s.id) AS has_report 
        FROM maintenance_schedules s 
        LEFT JOIN maintenance_assets ma ON ma.id = s.maintenance_asset_id
        LEFT JOIN asset_items ai ON ai.id = COALESCE(s.asset_item_id, ma.asset_item_id)
        LEFT JOIN pcs p ON (p.pc_id = s.pc_id OR (s.pc_id IS NULL AND p.asset_item_id = s.asset_item_id))
        LEFT JOIN printers pr ON (pr.prn_id = s.printer_id OR (s.printer_id IS NULL AND pr.asset_item_id = s.asset_item_id))
        WHERE s.technician_id=? AND s.status NOT IN ('completed', 'cancelled') 
        ORDER BY s.scheduled_date ASC, s.id ASC");
    $stmt->execute([$user['id']]);
    echo '<section class="panel"><h1>Schedule Saya</h1><p class="muted">Pilih unit aset, lalu scan QR sebelum mulai pekerjaan. Untuk paket bundling cukup scan QR Induk.</p></section>';
    $hasRows = false;
    foreach ($stmt as $row) {
        $hasRows = true;
        $canOpen = !empty($row['arrival_at']);
        $waitingEndScan = $canOpen && (int)($row['has_report'] ?? 0) === 1;
        $displayCode = (string)$row['asset_id'];
        $codeHtml = e($displayCode);
        if (($row['asset_mode'] ?? '') === 'group') {
            $codeHtml .= ' <span class="badge" style="background:#1e3a8a;color:#fff;font-size:11px;">📦 Induk Bundle</span>';
        }
        echo '<section class="panel"><div class="split"><div><strong>' . $codeHtml . '</strong><br><span class="muted">' . e($row['owner_name']) . ' - ' . e($row['computer_name'] ?: '-') . '</span>';
        if (!empty($row['location_label'])) {
            echo '<br><span class="muted" style="font-size:12px;">📍 ' . e($row['location_label']) . '</span>';
        }
        echo '<br><span class="muted">Tanggal kerja: ' . e($row['scheduled_date']) . '</span><br><span class="badge">' . e($row['asset_type'] ?? 'pc') . '</span> <span class="badge">' . e($row['status']) . '</span></div><div class="actions">';
        if ($waitingEndScan) {
            echo '<a class="btn good" href="' . route_url('mobile_scan', ['id' => $row['id'], 'phase' => 'end']) . '">Scan Selesai</a>';
        } else {
            echo $canOpen ? '<a class="btn primary" href="' . route_url('mobile_job', ['id' => $row['id']]) . '">Buka</a>' : '<a class="btn primary" href="' . route_url('mobile_scan', ['id' => $row['id'], 'phase' => 'start']) . '">Scan Mulai</a>';
        }
        echo '</div></div>';
        if ($waitingEndScan) {
            echo '<p class="muted">Checklist dan foto sudah tersimpan. Scan QR sekali lagi untuk mengubah status menjadi Done.</p>';
        }
        if (!empty($row['notes'])) {
            echo '<p class="muted">' . e($row['notes']) . '</p>';
        }
        echo '</section>';
    }
    if (!$hasRows) {
        echo '<section class="panel"><p class="muted">Tidak ada schedule maintenance terbuka untuk user Anda.</p></section>';
    }
    render_mobile_footer();
}

/**
 * Modul Mobile PWA: Scanner
 * Proses: Mengaktifkan fungsionalitas kamera untuk memindai QR code aset.
 */
function mobile_scan(PDO $pdo): void
{
    $rawCode = trim((string)($_GET['code'] ?? $_POST['code'] ?? ''));
    $eqr = trim((string)($_GET['eqr'] ?? $_POST['eqr'] ?? ''));
    $code = '';
    $decryptedOk = false;

    if ($eqr !== '' && function_exists('qr_decrypt_payload')) {
        $decrypted = qr_decrypt_payload(rawurldecode($eqr));
        if ($decrypted === null || $decrypted === '') {
            $decrypted = qr_decrypt_payload($eqr);
        }
        if ($decrypted !== null && $decrypted !== '') {
            $code = strtoupper(trim($decrypted));
            $decryptedOk = true;
        }
    }

    if (!$decryptedOk && $rawCode !== '' && strtoupper($rawCode) !== '[ENCRYPTED_QR]') {
        if (stripos($rawCode, 'http://') !== false || stripos($rawCode, 'https://') !== false || str_contains($rawCode, '?') || str_contains($rawCode, '=')) {
            $parsed = parse_url($rawCode);
            if (!empty($parsed['query'])) {
                parse_str($parsed['query'], $qp);
                $qpLower = array_change_key_case($qp, CASE_LOWER);
                if (!empty($qpLower['eqr']) && function_exists('qr_decrypt_payload')) {
                    $dec = qr_decrypt_payload((string)$qpLower['eqr']);
                    if ($dec !== null && $dec !== '') {
                        $code = strtoupper(trim($dec));
                        $decryptedOk = true;
                    }
                } elseif (!empty($qpLower['code'])) {
                    $code = strtoupper(trim((string)$qpLower['code']));
                    $decryptedOk = true;
                }
            }
            if (!$decryptedOk && (stripos($rawCode, 'http://') !== false || stripos($rawCode, 'https://') !== false)) {
                $pathParts = explode('/', rtrim(parse_url($rawCode, PHP_URL_PATH) ?? '', '/'));
                $lastPart = end($pathParts);
                if ($lastPart && strtolower($lastPart) !== 'index.php') {
                    $rawCode = trim(urldecode($lastPart));
                }
            }
        }

        if (!$decryptedOk && function_exists('qr_decrypt_payload') && !preg_match('/^[A-Z0-9]{2,5}-[A-Z0-9]{2,5}-\d{3,8}$/i', $rawCode)) {
            $decrypted = qr_decrypt_payload($rawCode);
            if ($decrypted !== null && $decrypted !== '' && preg_match('/^[A-Za-z0-9_.\-\s#]+$/', $decrypted)) {
                $code = strtoupper(trim($decrypted));
                $decryptedOk = true;
            }
        }

        if (!$decryptedOk) {
            $code = strtoupper(trim($rawCode));
        }
    }
    $scheduleId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    $phase = (string)($_GET['phase'] ?? $_POST['phase'] ?? 'start');
    if (!in_array($phase, ['start', 'end'], true)) {
        $phase = 'start';
    }
    if (!current_user()) {
        if ($code !== '') {
            $_SESSION['pending_mobile_code'] = $code;
        }
        redirect_to('login', ['mode' => 'mobile']);
    }
    $user = require_role(['admin', 'technician']);
    cancel_expired_maintenance_schedules($pdo);
    expire_stale_mobile_work_or_logout($pdo, $user, $scheduleId > 0 ? $scheduleId : null);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        [$assetType, $assetId, $assetInfo] = find_asset_by_code($pdo, $code);
        if ($assetId === '') {
            flash('INVALID QR. Kode asset tidak valid atau tidak ditemukan.', 'err');
            redirect_to('mobile_scan', $scheduleId > 0 ? ['id' => $scheduleId, 'phase' => $phase] : []);
        }

        $schedule = null;
        if ($scheduleId > 0) {
            $schSql = "SELECT s.*, 
                       ai.id AS ai_id, ai.asset_code AS ai_asset_code, ai.asset_name AS ai_asset_name,
                       ai.custodian_name AS ai_custodian_name, ai.asset_mode AS ai_asset_mode,
                       ai.source_pc_id AS ai_source_pc_id,
                       ai.latitude AS ai_lat, ai.longitude AS ai_lng, ai.location_radius_m AS ai_radius,
                       ma.id AS ma_id, ma.maintenance_asset_code, ma.latitude AS ma_lat, ma.longitude AS ma_lng, 
                       ma.location_radius_m AS ma_radius, ma.location_label AS ma_location_label, 
                       ma.pc_id AS ma_pc_id, ma.printer_id AS ma_printer_id
                       FROM maintenance_schedules s 
                       LEFT JOIN asset_items ai ON ai.id = s.asset_item_id
                       LEFT JOIN maintenance_assets ma ON ma.id = s.maintenance_asset_id 
                       WHERE s.id = ?";
            $schParams = [$scheduleId];
            if ($user['role'] !== 'admin') {
                $schSql .= " AND s.technician_id = ?";
                $schParams[] = $user['id'];
            }
            $stmt = $pdo->prepare($schSql);
            $stmt->execute($schParams);
            $scheduleCandidate = $stmt->fetch();

            if (!$scheduleCandidate) {
                flash($user['role'] === 'admin' ? 'Schedule terbuka tidak ditemukan.' : 'Schedule tidak ada atau bukan penugasan Anda.', 'err');
                redirect_to('mobile_schedule');
            }

            if ($scheduleCandidate['status'] === 'cancelled') {
                flash('Jadwal maintenance ini telah dibatalkan' . (!empty($scheduleCandidate['cancellation_reason']) ? ' (' . $scheduleCandidate['cancellation_reason'] . ')' : '') . '.', 'err');
                redirect_to('mobile_schedule');
            }

            // Cek apakah scan cocok dengan target schedule (mendukung Induk/Anak Bundle, PC ID, Source PC ID, Printer ID, Maintenance Asset)
            $isMatch = false;
            $schedItemId = (int)($scheduleCandidate['asset_item_id'] ?? 0);
            $schedAssetCode = strtoupper(trim((string)($scheduleCandidate['ai_asset_code'] ?? '')));
            $schedPcId = strtoupper(trim((string)($scheduleCandidate['pc_id'] ?: ($scheduleCandidate['ai_source_pc_id'] ?: ($scheduleCandidate['ma_pc_id'] ?? '')))));
            $schedPrinterId = strtoupper(trim((string)($scheduleCandidate['printer_id'] ?: ($scheduleCandidate['ma_printer_id'] ?? ''))));
            $schedMaId = (int)($scheduleCandidate['maintenance_asset_id'] ?? 0);

            if ($assetType === 'asset_item') {
                $scannedItemId = (int)($assetInfo['id'] ?? 0);
                $scannedAssetCode = strtoupper(trim((string)($assetInfo['asset_code'] ?? '')));
                $scannedPcId = strtoupper(trim((string)($assetInfo['pc_id'] ?? ($assetInfo['source_pc_id'] ?? ''))));
                $scannedPrinterId = strtoupper(trim((string)($assetInfo['printer_id'] ?? '')));
                $scannedMaId = (int)($assetInfo['maintenance_asset_id'] ?? 0);

                if ($schedItemId > 0 && $schedItemId === $scannedItemId) {
                    $isMatch = true;
                }
                if (!$isMatch && $schedAssetCode !== '' && $schedAssetCode === $scannedAssetCode) {
                    $isMatch = true;
                }
                if (!$isMatch && !empty($assetInfo['bundle_children'])) {
                    $childIds = array_map('intval', array_column($assetInfo['bundle_children'], 'child_id'));
                    if (in_array($schedItemId, $childIds, true)) {
                        $isMatch = true;
                    }
                }
                if (!$isMatch && !empty($assetInfo['parent_bundle'])) {
                    $parentId = (int)($assetInfo['parent_bundle']['parent_id'] ?? ($assetInfo['parent_bundle']['parent_asset_item_id'] ?? 0));
                    if ($parentId > 0 && $schedItemId === $parentId) {
                        $isMatch = true;
                    }
                }
                if (!$isMatch && $schedItemId > 0 && $scannedItemId > 0 && function_exists('asset_item_contains_child')) {
                    if (asset_item_contains_child($pdo, $scannedItemId, $schedItemId) || asset_item_contains_child($pdo, $schedItemId, $scannedItemId)) {
                        $isMatch = true;
                    }
                }
                if (!$isMatch && $schedPcId !== '' && $scannedPcId !== '' && $schedPcId === $scannedPcId) {
                    $isMatch = true;
                }
                if (!$isMatch && $schedPrinterId !== '' && $scannedPrinterId !== '' && $schedPrinterId === $scannedPrinterId) {
                    $isMatch = true;
                }
                if (!$isMatch && $schedMaId > 0 && $scannedMaId > 0 && $schedMaId === $scannedMaId) {
                    $isMatch = true;
                }
            } elseif ($assetType === 'maintenance_asset') {
                $scannedPcId = strtoupper(trim((string)($assetInfo['pc_id'] ?? '')));
                $scannedPrinterId = strtoupper(trim((string)($assetInfo['printer_id'] ?? '')));
                $scannedAiId = (int)($assetInfo['asset_item_id'] ?? 0);
                $isMatch = ($schedMaId > 0 && $schedMaId === (int)$assetId)
                    || ($schedItemId > 0 && $scannedAiId > 0 && $schedItemId === $scannedAiId)
                    || ($schedPcId !== '' && $scannedPcId !== '' && $schedPcId === $scannedPcId)
                    || ($schedPrinterId !== '' && $scannedPrinterId !== '' && $schedPrinterId === $scannedPrinterId);
            } elseif ($assetType === 'printer') {
                $scannedPrn = strtoupper(trim((string)$assetId));
                $scannedAiId = (int)($assetInfo['asset_item_id'] ?? 0);
                $isMatch = ($schedPrinterId !== '' && $schedPrinterId === $scannedPrn)
                    || ($schedItemId > 0 && $scannedAiId > 0 && $schedItemId === $scannedAiId)
                    || (!empty($assetInfo['maintenance_asset_id']) && $schedMaId > 0 && (int)$assetInfo['maintenance_asset_id'] === $schedMaId);
            } else { // pc
                $scannedPc = strtoupper(trim((string)$assetId));
                $scannedAiId = (int)($assetInfo['asset_item_id'] ?? 0);
                $isMatch = ($schedPcId !== '' && $schedPcId === $scannedPc)
                    || ($schedItemId > 0 && $scannedAiId > 0 && $schedItemId === $scannedAiId)
                    || (!empty($assetInfo['maintenance_asset_id']) && $schedMaId > 0 && (int)$assetInfo['maintenance_asset_id'] === $schedMaId);
            }

            if (!$isMatch) {
                $scannedCodeLabel = (string)($assetInfo['asset_code'] ?? $assetInfo['pc_id'] ?? $assetInfo['printer_id'] ?? $assetInfo['maintenance_asset_code'] ?? $assetId);
                $scannedNameLabel = (string)($assetInfo['asset_name'] ?? $assetInfo['computer_name'] ?? $assetInfo['printer_name'] ?? $assetInfo['name'] ?? '');
                $targetCodeLabel = (string)($scheduleCandidate['ai_asset_code'] ?: ($scheduleCandidate['pc_id'] ?: ($scheduleCandidate['printer_id'] ?: ('#' . $scheduleCandidate['id']))));
                $targetNameLabel = (string)($scheduleCandidate['ai_asset_name'] ?? '');
                flash(
                    'QR Code yang di-scan (' . $scannedCodeLabel . ($scannedNameLabel !== '' ? ' - ' . $scannedNameLabel : '') . ') tidak sesuai dengan target unit aset pada schedule ini (' . $targetCodeLabel . ($targetNameLabel !== '' ? ' - ' . $targetNameLabel : '') . ').',
                    'err'
                );
                redirect_to('mobile_scan', ['id' => $scheduleId, 'phase' => $phase]);
            }

            $schedule = $scheduleCandidate;
        } else {
            $findSql = "SELECT s.*, 
                        ai.id AS ai_id, ai.asset_code AS ai_asset_code,
                        ma.id AS ma_id, ma.maintenance_asset_code, ma.latitude AS ma_lat, ma.longitude AS ma_lng, 
                        ma.location_radius_m AS ma_radius, ma.location_label AS ma_location_label,
                        ma.pc_id AS ma_pc_id, ma.printer_id AS ma_printer_id
                        FROM maintenance_schedules s 
                        LEFT JOIN asset_items ai ON ai.id = s.asset_item_id
                        LEFT JOIN maintenance_assets ma ON ma.id = s.maintenance_asset_id 
                        WHERE s.status NOT IN ('completed', 'cancelled')";
            $findParams = [];
            if ($user['role'] !== 'admin') {
                $findSql .= " AND s.technician_id = ?";
                $findParams[] = $user['id'];
            }

            if ($assetType === 'asset_item') {
                $scannedItemId = (int)$assetInfo['id'];
                $targetIds = [$scannedItemId];
                if (($assetInfo['asset_mode'] ?? '') === 'group') {
                    foreach ($assetInfo['bundle_children'] ?? [] as $ch) {
                        $targetIds[] = (int)$ch['child_id'];
                    }
                } elseif (($assetInfo['asset_mode'] ?? '') === 'child' && !empty($assetInfo['parent_bundle'])) {
                    $targetIds[] = (int)$assetInfo['parent_bundle']['parent_id'];
                }
                $inPlaceholders = implode(',', array_fill(0, count($targetIds), '?'));
                $findSql .= " AND (s.asset_item_id IN ($inPlaceholders) 
                               OR (s.pc_id IS NOT NULL AND s.pc_id = ?) 
                               OR (s.printer_id IS NOT NULL AND s.printer_id = ?))";
                foreach ($targetIds as $tid) {
                    $findParams[] = $tid;
                }
                $findParams[] = !empty($assetInfo['pc_id']) ? $assetInfo['pc_id'] : '__none__';
                $findParams[] = !empty($assetInfo['printer_id']) ? $assetInfo['printer_id'] : '__none__';
            } elseif ($assetType === 'maintenance_asset') {
                $findSql .= " AND (s.maintenance_asset_id = ? 
                               OR (s.pc_id IS NOT NULL AND s.pc_id = ?) 
                               OR (s.printer_id IS NOT NULL AND s.printer_id = ?))";
                $findParams[] = (int)$assetId;
                $findParams[] = !empty($assetInfo['pc_id']) ? $assetInfo['pc_id'] : '__none__';
                $findParams[] = !empty($assetInfo['printer_id']) ? $assetInfo['printer_id'] : '__none__';
            } elseif ($assetType === 'printer') {
                $findSql .= " AND (s.printer_id = ? OR ma.printer_id = ?)";
                $findParams[] = $assetId;
                $findParams[] = $assetId;
            } else { // pc
                $findSql .= " AND (s.pc_id = ? OR ma.pc_id = ?)";
                $findParams[] = $assetId;
                $findParams[] = $assetId;
            }

            $findSql .= " ORDER BY s.scheduled_date ASC, s.id ASC LIMIT 1";
            $findStmt = $pdo->prepare($findSql);
            $findStmt->execute($findParams);
            $schedule = $findStmt->fetch();

            if (!$schedule) {
                flash($user['role'] === 'admin' ? 'Schedule terbuka tidak ada untuk aset ini.' : 'Schedule terbuka tidak ada untuk aset ini atau bukan penugasan Anda.', 'err');
                redirect_to('mobile_schedule');
            }
        }

        $lat = ($_POST['lat'] ?? '') !== '' ? (float)$_POST['lat'] : null;
        $lng = ($_POST['lng'] ?? '') !== '' ? (float)$_POST['lng'] : null;
        if ($assetType === 'asset_item') {
            $locationError = validate_asset_item_scan_location($pdo, (int)$assetInfo['id'], $lat, $lng);
            if ($locationError !== null) {
                flash($locationError, 'err');
                redirect_to('mobile_scan', ['id' => $schedule['id'], 'phase' => $phase]);
            }
        } elseif ($assetType === 'pc') {
            $locationError = validate_pc_scan_location($pdo, $assetId, $lat, $lng);
            if ($locationError !== null) {
                flash($locationError, 'err');
                redirect_to('mobile_scan', ['id' => $schedule['id'], 'phase' => $phase]);
            }
        } elseif ($assetType === 'printer') {
            $locationError = validate_printer_scan_location($pdo, $assetId, $lat, $lng);
            if ($locationError !== null) {
                flash($locationError, 'err');
                redirect_to('mobile_scan', ['id' => $schedule['id'], 'phase' => $phase]);
            }
        } elseif ($assetType === 'maintenance_asset') {
            $locationError = validate_maintenance_asset_scan_location($pdo, (int)$assetId, $lat, $lng);
            if ($locationError !== null) {
                flash($locationError, 'err');
                redirect_to('mobile_scan', ['id' => $schedule['id'], 'phase' => $phase]);
            }
        }
        if ($phase === 'end') {
            if (empty($schedule['arrival_at'])) {
                flash('Scan mulai belum dilakukan untuk schedule ini.', 'err');
                redirect_to('mobile_scan', ['id' => $schedule['id'], 'phase' => 'start']);
            }
            $reportStmt = $pdo->prepare('SELECT id FROM maintenance_reports WHERE schedule_id=? ORDER BY id DESC LIMIT 1');
            $reportStmt->execute([$schedule['id']]);
            $reportId = (int)($reportStmt->fetchColumn() ?: 0);
            if ($reportId <= 0) {
                flash('Simpan checklist dan foto terlebih dahulu, lalu scan QR selesai.', 'err');
                redirect_to('mobile_job', ['id' => $schedule['id']]);
            }
            $scanToken = maintenance_scan_token($schedule, 'end');
            add_timeline($pdo, (int)$schedule['id'], 'qr_end_scan', 'QR selesai tervalidasi. Token: ' . $scanToken, (int)$user['id']);
            $pdo->prepare("UPDATE maintenance_reports SET locked_at=NOW() WHERE id=?")->execute([$reportId]);
            $pdo->prepare("UPDATE maintenance_schedules SET status='completed', completed_at=NOW(), locked_at=NOW() WHERE id=?")->execute([$schedule['id']]);
            flash('Scan selesai valid. Maintenance completed dan report locked.');
            redirect_to('mobile_job', ['id' => $schedule['id']]);
        }
        if (empty($schedule['arrival_at']) || !in_array($schedule['status'], ['validated', 'in_progress', 'reopened'], true)) {
            $pdo->prepare("UPDATE maintenance_schedules SET status='in_progress', arrival_at=COALESCE(arrival_at,NOW()), arrival_ip=?, arrival_user_agent=?, arrival_lat=?, arrival_lng=?, arrival_browser=? WHERE id=?")->execute([
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $lat,
                $lng,
                substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 180),
                $schedule['id'],
            ]);
            $scanToken = maintenance_scan_token($schedule, 'start');
            add_timeline($pdo, (int)$schedule['id'], 'qr_start_scan', 'QR mulai tervalidasi dan pekerjaan dibuka. Token: ' . $scanToken, (int)$user['id']);
        }
        flash('QR mulai valid. Checklist maintenance terbuka.');
        redirect_to('mobile_job', ['id' => $schedule['id']]);
    }

    render_mobile_header('Scan QR', $user);
    if (($user['role'] ?? '') === 'technician' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $code = '';
    }
    $targetInfoHtml = '';
    if ($scheduleId > 0) {
        try {
            $tStmt = $pdo->prepare("SELECT s.id, s.scheduled_date,
                COALESCE(ai.asset_code, s.pc_id, s.printer_id, CONCAT('Unit #', s.id)) AS target_code,
                COALESCE(ai.asset_name, ma.name, p.computer_name, pr.printer_name, '-') AS target_name,
                COALESCE(ai.custodian_name, ma.owner_name, p.owner_name, '-') AS target_owner,
                ai.asset_mode
                FROM maintenance_schedules s
                LEFT JOIN maintenance_assets ma ON ma.id = s.maintenance_asset_id
                LEFT JOIN asset_items ai ON ai.id = COALESCE(s.asset_item_id, ma.asset_item_id)
                LEFT JOIN pcs p ON (p.pc_id = s.pc_id OR (s.pc_id IS NULL AND p.asset_item_id = s.asset_item_id))
                LEFT JOIN printers pr ON (pr.prn_id = s.printer_id OR (s.printer_id IS NULL AND pr.asset_item_id = s.asset_item_id))
                WHERE s.id = ? LIMIT 1");
            $tStmt->execute([$scheduleId]);
            $tRow = $tStmt->fetch();
            if ($tRow) {
                $bundleBadge = (($tRow['asset_mode'] ?? '') === 'group') ? ' <span class="badge" style="background:#1e3a8a;color:#fff;font-size:11px;">📦 Induk Bundle</span>' : '';
                $targetInfoHtml = '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-left:4px solid #2563eb;border-radius:6px;padding:10px 12px;margin-bottom:12px;font-size:13px;">'
                    . '<div style="font-size:11px;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:0.4px;margin-bottom:2px;">Target Unit Aset Schedule Ini:</div>'
                    . '<div style="font-weight:800;font-size:15px;color:#0f172a;">' . e($tRow['target_code']) . $bundleBadge . '</div>'
                    . '<div style="color:#334155;">' . e($tRow['target_name']) . ($tRow['target_owner'] && $tRow['target_owner'] !== '-' ? ' &bull; <strong>' . e($tRow['target_owner']) . '</strong>' : '') . '</div>'
                    . '</div>';
            }
        } catch (Throwable $ignored) {
        }
    }
    $scanTitle = $phase === 'end' ? 'Scan QR Selesai' : 'Scan QR Mulai';
    $codeReadonly = ($user['role'] ?? '') === 'technician' ? ' readonly' : '';
    $manualNote = ($user['role'] ?? '') === 'technician' ? '<p class="muted">Untuk teknisi, Kode Unit Aset hanya bisa terisi dari live scan kamera.</p>' : '<p class="muted">Admin masih dapat mengetik Kode Unit Aset untuk troubleshooting.</p>';
    echo '<section class="panel"><h1>' . e($scanTitle) . '</h1>' . $targetInfoHtml . '<p class="muted">QR terenkripsi otomatis divalidasi oleh sistem.</p><div id="reader" style="display:none;width:100%;max-width:420px"></div><video id="video" class="scan-video" autoplay muted playsinline></video><p id="cameraStatus" class="camera-note">Tekan Mulai Live Scan. Kamera dan GPS wajib aktif untuk teknisi.</p><p><button type="button" id="startCamera" class="btn primary">Mulai Live Scan</button></p>' . $manualNote . '<form method="post" id="scanForm"><input type="hidden" name="csrf" value="' . csrf_token() . '"><input type="hidden" name="id" value="' . e($scheduleId) . '"><input type="hidden" name="phase" value="' . e($phase) . '"><input type="hidden" id="lat" name="lat"><input type="hidden" id="lng" name="lng"><input type="hidden" id="eqr" name="eqr" value="' . e($eqr) . '"><label>Kode Unit Aset<input id="code" name="code" value="' . e($code) . '" placeholder="Terisi otomatis dari live scan QR" required' . $codeReadonly . '></label><button class="btn primary">Validasi QR</button></form></section>';
    echo '<script src="https://unpkg.com/html5-qrcode" onerror="window.__qrLibFailed=true"></script><script>(function(){var code=document.getElementById("code"),eqrInput=document.getElementById("eqr"),status=document.getElementById("cameraStatus"),video=document.getElementById("video"),reader=document.getElementById("reader"),detector=null,timer=null,html5=null,submitted=false,gpsReady=false;function extractData(raw){var v=String(raw||"").trim();try{var u=new URL(v,window.location.href);if(u.searchParams.get("eqr")){return {eqr:u.searchParams.get("eqr")};}if(u.searchParams.get("code")){return {code:u.searchParams.get("code").trim()};}v=u.pathname;}catch(e){if(v.indexOf("eqr=")>=0){return {eqr:v.split("eqr=").pop().split("&")[0]};}if(v.indexOf("code=")>=0){return {code:v.split("code=").pop().split("&")[0]};}}v=decodeURIComponent(v).trim();if(v.indexOf("/")>=0){v=v.split("/").pop();}v=v.trim().toUpperCase();var m=v.match(/[A-Z0-9_-]+-[A-Z0-9]{2,12}/);return {code:m?m[0]:v};}function handleScan(raw){if(submitted){return;}var res=extractData(raw);if(res.eqr){eqrInput.value=res.eqr;code.value="[ENCRYPTED_QR]";status.textContent="QR terenkripsi terbaca. Memvalidasi...";submitted=true;setTimeout(function(){document.getElementById("scanForm").submit();},400);return;}if(res.code){code.value=res.code;status.textContent="QR terbaca: "+res.code+". Memvalidasi...";submitted=true;setTimeout(function(){document.getElementById("scanForm").submit();},400);}}if(navigator.geolocation){navigator.geolocation.getCurrentPosition(function(p){document.getElementById("lat").value=p.coords.latitude.toFixed(7);document.getElementById("lng").value=p.coords.longitude.toFixed(7);gpsReady=true;status.textContent="GPS siap. Akurasi sekitar "+Math.round(p.coords.accuracy)+" meter.";},function(){},{enableHighAccuracy:true,timeout:15000,maximumAge:0});}if("BarcodeDetector" in window){detector=new BarcodeDetector({formats:["qr_code"]});}document.getElementById("startCamera").onclick=async function(){if(window.Html5Qrcode){try{video.style.display="none";reader.style.display="block";html5=new Html5Qrcode("reader");await html5.start({facingMode:"environment"},{fps:10,qrbox:{width:240,height:240}},function(decoded){handleScan(decoded);});return;}catch(e){}}if(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia){try{var s=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:"environment"}}});video.srcObject=s;video.style.display="block";clearInterval(timer);timer=setInterval(async function(){try{if(detector){var r=await detector.detect(video);if(r&&r[0]){handleScan(r[0].rawValue);}}}catch(e){}},800);}catch(e){status.textContent="Kamera tidak dapat diakses.";}}};})();</script>';
    render_mobile_footer();
}

/**
 * Modul Mobile PWA: Detail Pekerjaan (Job)
 * Proses: Menampilkan rincian dari satu pekerjaan maintenance beserta checklist yang harus diisi teknisi.
 */
function mobile_job(PDO $pdo): void
{
    $user = require_role(['admin', 'technician']);
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    ensure_base_schema_from_sql($pdo);
    ensure_photo_challenge_schema($pdo);
    ensure_maintenance_work_schema($pdo);
    cancel_expired_maintenance_schedules($pdo);
    expire_stale_mobile_work_or_logout($pdo, $user, $id > 0 ? $id : null);
    $jobSql = 'SELECT s.*, 
        COALESCE(ai.asset_code, s.pc_id, s.printer_id, CONCAT("Unit #", s.id)) AS asset_id, 
        COALESCE(ai.custodian_name, ma.owner_name, p.owner_name, pr.printer_name, "-") AS owner_name, 
        COALESCE(ai.asset_name, ma.name, p.computer_name, pr.printer_name, pr.location, "-") AS computer_name, 
        COALESCE(p.physical_condition, pr.physical_condition) AS physical_condition, 
        p.general_specs, p.benchmark, p.ai_recommendation,
        ai.location_label AS ma_location_label
        FROM maintenance_schedules s 
        LEFT JOIN maintenance_assets ma ON ma.id = s.maintenance_asset_id 
        LEFT JOIN asset_items ai ON ai.id = COALESCE(s.asset_item_id, ma.asset_item_id) 
        LEFT JOIN pcs p ON (p.pc_id = s.pc_id OR (s.pc_id IS NULL AND (ma.pc_id IS NOT NULL AND p.pc_id COLLATE utf8mb4_unicode_ci = ma.pc_id COLLATE utf8mb4_unicode_ci OR p.asset_item_id = s.asset_item_id))) 
        LEFT JOIN printers pr ON (pr.prn_id = s.printer_id OR (s.printer_id IS NULL AND (ma.printer_id IS NOT NULL AND pr.prn_id COLLATE utf8mb4_unicode_ci = ma.printer_id COLLATE utf8mb4_unicode_ci OR pr.asset_item_id = s.asset_item_id))) 
        WHERE s.id=?';
    if ($user['role'] === 'admin') {
        $stmt = $pdo->prepare($jobSql);
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare($jobSql . ' AND s.technician_id=?');
        $stmt->execute([$id, $user['id']]);
    }
    $schedule = $stmt->fetch();
    if (!$schedule) {
        http_response_code(404);
        exit('Schedule tidak ditemukan.');
    }
    if ($schedule['status'] === 'cancelled') {
        flash('Jadwal maintenance ini telah dibatalkan' . (!empty($schedule['cancellation_reason']) ? ' (' . $schedule['cancellation_reason'] . ')' : '') . '.', 'err');
        redirect_to('mobile_schedule');
    }
    if (empty($schedule['arrival_at'])) {
        flash('Scan QR asset terlebih dahulu sebelum memulai pekerjaan.', 'err');
        redirect_to('mobile_schedule');
    }

    $locked = $schedule['status'] === 'completed';
    $hasBeforePhotos = !empty($schedule['before_photos']);

    if (isset($_GET['retake_before']) && $_GET['retake_before'] === '1' && !$locked) {
        $pdo->prepare('UPDATE maintenance_schedules SET before_photos=NULL, before_photo_at=NULL WHERE id=?')->execute([$id]);
        add_timeline($pdo, $id, 'before_photo_reset', 'Foto sebelum di-reset untuk pengambilan ulang.', (int)$user['id']);
        flash('Foto sebelum di-reset. Silakan ambil foto sebelum yang baru.');
        redirect_to('mobile_job', ['id' => $id]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string)($_POST['action'] ?? 'complete_job');

        if ($action === 'save_before') {
            if ($locked) {
                flash('Report sudah locked.', 'err');
                redirect_to('mobile_job', ['id' => $id]);
            }
            $uploadMeta = [];
            $before = save_uploaded_photos('before_photos', $id, 'before', $uploadMeta);
            if (empty($before)) {
                flash('Foto sebelum wajib diambil dari kamera langsung.', 'err');
                redirect_to('mobile_job', ['id' => $id]);
            }
            $photoAuditMeta = build_photo_audit_meta($pdo, $id, $uploadMeta, (string)($_POST['photo_evidence_meta'] ?? ''));
            $locationError = photo_evidence_location_error($photoAuditMeta);
            if ($locationError !== null) {
                flash($locationError, 'err');
                redirect_to('mobile_job', ['id' => $id]);
            }

            $challengeCode = trim((string)($schedule['photo_challenge_code'] ?? ''));
            if ($challengeCode === '') {
                $challengeCode = ensure_photo_challenge($pdo, $id);
            }
            $beforeUrl = $before[0] ?? '';
            $aiAudit = evaluate_photo_challenge_ai($pdo, $id, $beforeUrl, $challengeCode);
            $photoAuditMeta['ai_challenge_audit'] = $aiAudit;
            $photoAuditMeta['photo_challenge_code'] = $challengeCode;

            $nowStr = date('Y-m-d H:i:s');
            $pdo->prepare('UPDATE maintenance_schedules SET before_photos=?, before_photo_at=?, photo_audit_meta=? WHERE id=?')->execute([
                json_encode($before, JSON_UNESCAPED_SLASHES),
                $nowStr,
                json_encode($photoAuditMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $id,
            ]);
            $aiNote = !empty($aiAudit['note']) ? ' [AI: ' . $aiAudit['note'] . ']' : '';
            add_timeline($pdo, $id, 'before_photo_saved', 'Foto sebelum berhasil disimpan pada ' . $nowStr . '. Kode challenge: ' . $challengeCode . $aiNote . '. Teknisi mulai pengerjaan checklist.', (int)$user['id']);
            flash('Foto sebelum berhasil disimpan. Checklist job desk kini dapat dipilih dan dikerjakan.');
            redirect_to('mobile_job', ['id' => $id]);
        }

        if ($action === 'complete_job') {
            if ($locked) {
                flash('Report sudah locked.', 'err');
                redirect_to('mobile_job', ['id' => $id]);
            }
            $pdo->prepare('UPDATE schedule_jobs SET is_done=0, done_at=NULL WHERE schedule_id=?')->execute([$id]);
            save_schedule_job_notes($pdo, $id, $_POST['job_notes'] ?? []);
            foreach ($_POST['done'] ?? [] as $jobId) {
                $pdo->prepare('UPDATE schedule_jobs SET is_done=1, done_at=NOW() WHERE schedule_id=? AND job_id=?')->execute([$id, (int)$jobId]);
            }
            $uploadMeta = [];
            $process = save_uploaded_photos('process_photos', $id, 'process', $uploadMeta);
            $after = save_uploaded_photos('after_photos', $id, 'after', $uploadMeta);
            if (empty($after)) {
                flash('Foto sesudah wajib diambil dari kamera langsung.', 'err');
                redirect_to('mobile_job', ['id' => $id]);
            }
            $before = json_decode((string)$schedule['before_photos'], true) ?: [];
            if (empty($before)) {
                flash('Foto sebelum belum tersimpan. Ambil foto sebelum terlebih dahulu.', 'err');
                redirect_to('mobile_job', ['id' => $id]);
            }

            // Ambil photo_audit_meta dari schedule untuk memuat riwayat upload foto sebelum & evaluasi AI
            $schStmt = $pdo->prepare('SELECT photo_audit_meta, photo_challenge_code FROM maintenance_schedules WHERE id=?');
            $schStmt->execute([$id]);
            $schRow = $schStmt->fetch() ?: [];
            $schMeta = json_decode((string)($schRow['photo_audit_meta'] ?? ''), true) ?: [];

            $combinedUploadMeta = array_merge($schMeta['uploads'] ?? [], $uploadMeta);
            $photoAuditMeta = build_photo_audit_meta($pdo, $id, $combinedUploadMeta, (string)($_POST['photo_evidence_meta'] ?? ''));
            $durationConfirmed = (!empty($_POST['duration_confirmed']) && (string)$_POST['duration_confirmed'] === '1');

            if (!empty($schMeta['ai_challenge_audit'])) {
                $photoAuditMeta['ai_challenge_audit'] = $schMeta['ai_challenge_audit'];
            }
            $photoAuditMeta['photo_challenge_code'] = (string)($schRow['photo_challenge_code'] ?? '');

            // Hitung selisih waktu real: waktu foto sesudah dikurangi waktu foto sebelum
            $startTimeStr = (string)($schedule['before_photo_at'] ?? $schedule['arrival_at'] ?? '');
            $startTime = $startTimeStr !== '' ? strtotime($startTimeStr) : 0;
            $endTime = time();
            $elapsedSeconds = $startTime > 0 ? max(0, $endTime - $startTime) : 0;
            $realDurationMinutes = max(0.1, round($elapsedSeconds / 60, 1));
            $minimumMinutes = schedule_minimum_photo_minutes($pdo, $id);

            $photoAuditMeta['real_duration_minutes'] = $realDurationMinutes;
            $photoAuditMeta['minimum_duration_minutes'] = $minimumMinutes;

            // CEGAT jika durasi pengerjaan < minimal job desk dan teknisi belum konfirmasi "Ya":
            if ($realDurationMinutes < $minimumMinutes && !$durationConfirmed) {
                $_SESSION['duration_warning_schedule_id'] = $id;
                flash('Anda mengerjakannya job desk ini di bawah minimal waktu pengerjaan , apakah Anda yakin sudah selesai', 'err');
                redirect_to('mobile_job', ['id' => $id, 'duration_warning' => '1']);
            }

            if ($durationConfirmed) {
                $photoAuditMeta['duration_override_approved'] = true;
            }

            $locationError = photo_evidence_location_error($photoAuditMeta);
            if ($locationError !== null) {
                flash($locationError, 'err');
                redirect_to('mobile_job', ['id' => $id]);
            }
            unset($_SESSION['duration_warning_schedule_id']);

            $signaturePath = save_signature_data((string)($_POST['signature_data'] ?? ''), $id);
            $hash = hash('sha256', $id . '|' . ($schedule['asset_id'] ?? '') . '|' . json_encode($before) . '|' . json_encode($process) . '|' . json_encode($after) . '|' . $signaturePath);
            try {
                $stmt = $pdo->prepare('INSERT INTO maintenance_reports (schedule_id, technician_id, condition_rating, physical_condition, additional_notes, before_photos, process_photos, after_photos, photo_challenge_code, photo_audit_meta, signature_name, signature_path, report_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    $id,
                    $user['id'],
                    $_POST['condition_rating'] ?? null,
                    trim((string)($_POST['physical_condition'] ?? '')),
                    trim((string)($_POST['additional_notes'] ?? '')),
                    json_encode($before, JSON_UNESCAPED_SLASHES),
                    json_encode($process, JSON_UNESCAPED_SLASHES),
                    json_encode($after, JSON_UNESCAPED_SLASHES),
                    (string)($schRow['photo_challenge_code'] ?? ''),
                    json_encode($photoAuditMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    trim((string)($_POST['signature_name'] ?? '')),
                    $signaturePath,
                    $hash,
                ]);
                $pdo->prepare("UPDATE maintenance_reports SET locked_at=NOW() WHERE schedule_id=? AND locked_at IS NULL")->execute([$id]);
                $pdo->prepare("UPDATE maintenance_schedules SET status='completed', completed_at=NOW(), locked_at=NOW() WHERE id=?")->execute([$id]);
                if (($schedule['asset_type'] ?? 'pc') === 'printer') {
                    if (!empty($schedule['printer_id'])) {
                        $pdo->prepare('UPDATE printers SET physical_condition=? WHERE prn_id=?')->execute([trim((string)($_POST['physical_condition'] ?? '')), $schedule['printer_id']]);
                    }
                } else {
                    if (!empty($schedule['pc_id'])) {
                        $pdo->prepare('UPDATE pcs SET physical_condition=? WHERE pc_id=?')->execute([trim((string)($_POST['physical_condition'] ?? '')), $schedule['pc_id']]);
                    }
                }
                $evidenceAudit = photo_evidence_audit_summary($pdo, array_merge($latestReport ?? [], ['schedule_id' => $id, 'photo_audit_meta' => json_encode($photoAuditMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'before_photos' => json_encode($before, JSON_UNESCAPED_SLASHES), 'after_photos' => json_encode($after, JSON_UNESCAPED_SLASHES)]));
                $durationLogNote = ($realDurationMinutes < $minimumMinutes)
                    ? " Waktu real pengerjaan: {$realDurationMinutes} menit (di bawah estimasi minimal {$minimumMinutes} menit, disetujui selesai oleh teknisi)."
                    : " Waktu real pengerjaan: {$realDurationMinutes} menit (memenuhi minimal {$minimumMinutes} menit).";
                add_timeline($pdo, $id, 'work_completed', 'Checklist dan foto tersimpan. Schedule langsung completed.' . $durationLogNote . ' Score: ' . $evidenceAudit['score'] . '/100. Hash: ' . $hash, (int)$user['id']);
                flash('Checklist dan foto tersimpan. Schedule maintenance sudah complete dan report locked.', 'ok');
            } catch (Throwable $e) {
                ensure_photo_challenge_schema($pdo);
                flash('Gagal menyimpan report maintenance: ' . $e->getMessage(), 'err');
            }
            redirect_to('mobile_job', ['id' => $id]);
        }
    }

    render_mobile_header('Pengerjaan Maintenance', $user);
    $reportStmt = $pdo->prepare('SELECT * FROM maintenance_reports WHERE schedule_id=? ORDER BY id DESC LIMIT 1');
    $reportStmt->execute([$id]);
    $latestReport = $reportStmt->fetch() ?: [];
    $minimumMinutes = schedule_minimum_photo_minutes($pdo, $id);
    $noteSelect = schedule_job_note_supported($pdo) ? 'sj.note' : "'' note";
    $jobsStmt = $pdo->prepare("SELECT j.*, sj.is_done, sj.done_at, $noteSelect FROM schedule_jobs sj JOIN maintenance_jobs j ON j.id=sj.job_id WHERE sj.schedule_id=? ORDER BY j.title");
    $jobsStmt->execute([$id]);
    $jobsList = $jobsStmt->fetchAll();

    $assetDisplayTitle = (string)$schedule['asset_id'];
    echo '<section class="panel"><h1>' . e($assetDisplayTitle) . '</h1><p><strong>' . e($schedule['owner_name']) . '</strong> - ' . e($schedule['computer_name'] ?: '-') . '</p>';
    if (!empty($schedule['ma_location_label'])) {
        echo '<p class="muted" style="margin-top:-6px;font-size:13px;">📍 ' . e($schedule['ma_location_label']) . '</p>';
    }
    echo '<p class="muted">Tanggal kerja: ' . e($schedule['scheduled_date']) . '</p><span class="badge">' . e($schedule['asset_type'] ?? 'pc') . '</span> <span class="badge">' . e($schedule['status']) . '</span></section>';
    echo smart_maintenance_card($schedule);

    if (!$hasBeforePhotos && !$locked) {
        $challengeCode = ensure_photo_challenge($pdo, $id, $schedule['photo_challenge_code'] ?? null);

        echo '<section class="panel" style="border-left:4px solid #0284c7;background:#f0f9ff;"><div style="display:flex;gap:10px;align-items:center;"><div><strong style="color:#0369a1;font-size:16px;">Tahap 1 dari 2: Foto Sebelum</strong><p class="muted" style="margin:4px 0 0 0;font-size:13px;">Foto sebelum wajib diambil dari kamera langsung di depan unit aset sebelum checklist job desk dapat dipilih dan dikerjakan.</p></div></div></section>';

        echo '<section class="panel" style="background:#fffbeb;border:2px dashed #f59e0b;border-radius:12px;padding:16px;margin-bottom:16px;text-align:center;">'
            . '<div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#b45309;">Dynamic Challenge Code (Anti-Duplikasi)</div>'
            . '<div style="font-size:42px;font-weight:900;letter-spacing:6px;color:#d97706;margin:8px 0;font-family:monospace;">' . e($challengeCode) . '</div>'
            . '<div style="background:#ffffff;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;margin-top:10px;text-align:left;">'
            . '<div style="font-weight:700;color:#92400e;font-size:13px;display:flex;align-items:center;gap:6px;margin-bottom:6px;">'
            . '📸 <span>Cara Foto yang Benar:</span>'
            . '</div>'
            . '<ol style="margin:0 0 0 18px;padding:0;font-size:13px;color:#78350f;line-height:1.55;">'
            . '<li>Tulis angka <strong style="color:#b45309;font-size:15px;">' . e($challengeCode) . '</strong> pada <strong>potongan kertas putih kecil (± 4 cm x 1 cm)</strong>.</li>'
            . '<li><strong>Dekatkan potongan kertas tersebut dengan stiker QR Code label</strong> pada unit aset.</li>'
            . '<li>Posisikan kamera sehingga <strong>kertas kode, stiker QR code, dan fisik unit terlihat bersamaan</strong> dalam 1 frame foto.</li>'
            . '</ol>'
            . '</div>'
            . '</section>';
        
        echo '<section class="panel"><h2>Daftar Job Desk Terjadwal</h2><p class="muted">Batas durasi kumulatif seluruh pekerjaan: <strong>' . (int)$minimumMinutes . ' Menit</strong></p>';
        if (!$jobsList) {
            echo '<p class="danger-text">Belum ada job desk dipilih untuk schedule ini.</p>';
        } else {
            foreach ($jobsList as $job) {
                $estM = max(1, (int)($job['estimated_minutes'] ?? 5));
                echo '<div class="check-row" style="opacity:0.75;"><input type="checkbox" disabled><div><span class="check-title">' . e($job['title']) . ' <small class="badge muted">+' . $estM . ' m</small></span><br><small class="muted">' . e($job['description'] ?: 'Job desk terdaftar') . '</small></div></div>';
            }
        }
        echo '</section>';

        echo '<section class="panel"><h2>Ambil Foto Sebelum</h2>'
            . '<div style="background:#eff6ff;border-left:4px solid #2563eb;padding:10px 14px;border-radius:6px;margin-bottom:14px;font-size:13px;color:#1e40af;line-height:1.45;">'
            . '📌 <strong>Petunjuk Foto:</strong> Dekatkan potongan kertas putih kecil (± 4 cm x 1 cm) berisi kode <strong style="color:#b45309;">' . e($challengeCode) . '</strong> dengan stiker QR Code label berserta terlihat fisik unitnya.'
            . '</div>'
            . '<form id="beforePhotoForm" method="post" enctype="multipart/form-data" onsubmit="try{localStorage.setItem(\'pcconnect_before_' . (int)$id . '\', Date.now().toString());}catch(e){}">'
            . '<input type="hidden" name="csrf" value="' . csrf_token() . '">'
            . '<input type="hidden" name="action" value="save_before">'
            . '<input type="hidden" id="photoEvidenceMeta" name="photo_evidence_meta" value="">'
            . '<p id="photoCompressStatus" class="camera-note">Gunakan kamera langsung di lokasi unit aset.</p>'
            . '<label>Foto Sebelum (Kamera Langsung)<input class="photo-input" data-photo-group="before" data-photo-slot="1" type="file" name="before_photos[]" accept="image/*" capture="environment" required></label>'
            . '<div id="beforePhotoPreviewBox" style="display:none;margin-top:10px;padding:10px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;text-align:center;">'
            . '<img id="beforePhotoPreviewImg" src="" alt="Preview Foto Sebelum" style="max-height:180px;max-width:100%;border-radius:6px;object-fit:contain;border:1px solid #e2e8f0;">'
            . '<div id="beforePhotoPreviewInfo" style="font-size:12px;color:#0f766e;font-weight:600;margin-top:6px;"></div>'
            . '</div>'
            . '<div id="photoProcessProgressBox" style="display:none;margin-top:12px;padding:12px 14px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;">'
            . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">'
            . '<span id="photoProgressLabel" style="font-size:13px;font-weight:700;color:#0369a1;">Memproses foto...</span>'
            . '<span id="photoProgressPct" style="font-size:14px;font-weight:800;color:#0284c7;background:#e0f2fe;padding:2px 8px;border-radius:999px;">0%</span>'
            . '</div>'
            . '<div style="width:100%;height:10px;background:#dbeafe;border-radius:999px;overflow:hidden;">'
            . '<div id="photoProgressBar" style="width:0%;height:100%;background:linear-gradient(90deg,#2563eb,#0ea5e9);border-radius:999px;transition:width 0.2s ease;"></div>'
            . '</div>'
            . '<div id="photoProgressDetail" style="font-size:11.5px;color:#475569;margin-top:6px;">Menyiapkan...</div>'
            . '</div>'
            . '<button type="submit" id="btnSaveBefore" class="btn primary" style="width:100%;margin-top:12px;padding:12px;font-size:15px;">Simpan Foto Sebelum & Buka Checklist</button>'
            . '</form></section>';
    } else {
        $startTime = strtotime((string)($schedule['before_photo_at'] ?? $schedule['arrival_at'] ?? 'now'));
        $targetTime = date('H:i', $startTime + ($minimumMinutes * 60));
        $elapsedSec = max(0, time() - $startTime);
        $elapsedMin = round($elapsedSec / 60, 1);

        if (!$locked) {
            $challengeNote = !empty($schedule['photo_challenge_code']) ? ' | Kode Challenge: <strong style="color:#b45309;">' . e($schedule['photo_challenge_code']) . '</strong>' : '';
            echo '<section class="panel" style="border-left:4px solid #16a34a;background:#f0fdf4;"><div style="display:flex;justify-content:space-between;align-items:center;"><div><strong style="color:#15803d;font-size:15px;">Tahap 2 dari 2: Checklist & Pengerjaan</strong><br><span class="muted" style="font-size:12px;">Foto sebelum tersimpan pada: ' . e($schedule['before_photo_at'] ?: $schedule['arrival_at']) . $challengeNote . '</span></div><div><a class="btn" style="font-size:12px;padding:4px 8px;" href="' . route_url('mobile_job', ['id' => $id, 'retake_before' => '1']) . '" onclick="try{localStorage.removeItem(\'pcconnect_before_' . (int)$id . '\');}catch(e){}return confirm(\'Ambil ulang foto sebelum? Waktu mulai pengerjaan akan di-reset.\');">Ambil Ulang Foto</a></div></div></section>';

            $savedBefore = json_decode((string)$schedule['before_photos'], true) ?: [];
            if ($savedBefore) {
                render_photo_block('Foto Sebelum (Tersimpan)', json_encode($savedBefore));
            }

            echo '<section class="panel" style="background:#f8fafc;border:1px solid #e2e8f0;padding:12px 16px;"><div style="display:flex;justify-content:space-between;align-items:center;"><div><strong style="font-size:14px;">⏱️ Durasi Kumulatif Job Desk: ' . (int)$minimumMinutes . ' Menit</strong><br><span class="muted" style="font-size:12px;">Mulai: ' . date('H:i', $startTime) . ' | Selesai Paling Cepat: ' . $targetTime . '</span></div><div id="liveTimerBadge" style="font-weight:bold;font-size:14px;padding:4px 10px;border-radius:6px;background:#e0f2fe;color:#0369a1;">Menghitung...</div></div><div id="liveTimerStatus" style="font-size:12px;margin-top:6px;color:#475569;"></div></section>';
        }

        echo '<section class="panel ' . ($locked ? 'readonly' : '') . '"><h2>Checklist Job Desk</h2>'
            . '<form id="jobForm" method="post" enctype="multipart/form-data">'
            . '<input type="hidden" name="csrf" value="' . csrf_token() . '">'
            . '<input type="hidden" name="action" value="complete_job">'
            . '<input type="hidden" id="photoEvidenceMeta" name="photo_evidence_meta" value="">'
            . '<input type="hidden" id="durationConfirmed" name="duration_confirmed" value="0">'
            . '<input type="hidden" id="realDurationMinutes" name="real_duration_minutes" value="">';

        $hasJobs = false;
        foreach ($jobsList as $job) {
            $hasJobs = true;
            $estM = max(1, (int)($job['estimated_minutes'] ?? 5));
            echo '<div class="check-row"><input type="checkbox" name="done[]" value="' . e($job['id']) . '"' . ($job['is_done'] ? ' checked' : '') . ($locked ? ' disabled' : '') . '><div><span class="check-title">' . e($job['title']) . ' <small class="badge muted">+' . $estM . ' m</small></span>' . ($job['done_at'] ? '<small class="muted">Dikerjakan: ' . e($job['done_at']) . '</small>' : '') . '<label class="check-note-label">Keterangan<input name="job_notes[' . e($job['id']) . ']" value="' . e($job['note'] ?? '') . '" placeholder="Boleh kosong" ' . ($locked ? 'readonly' : '') . '></label></div></div>';
        }
        if (!$hasJobs) {
            echo '<p class="danger-text">Belum ada jobdesk dipilih untuk schedule ini.</p>';
        }

        echo '<div class="grid two"><label>Foto Proses 1 (Opsional)<input class="photo-input" data-photo-group="process" data-photo-slot="1" type="file" name="process_photos[]" accept="image/*" capture="environment" ' . ($locked ? 'disabled' : '') . '></label><label>Foto Proses 2 (Opsional)<input class="photo-input" data-photo-group="process" data-photo-slot="2" type="file" name="process_photos[]" accept="image/*" capture="environment" ' . ($locked ? 'disabled' : '') . '></label></div>';
        echo '<label>Foto Sesudah - Kamera Langsung<input class="photo-input" data-photo-group="after" data-photo-slot="1" type="file" name="after_photos[]" accept="image/*" capture="environment" ' . ($locked ? 'disabled' : 'required') . '></label>';
        echo '<label>Kondisi Fisik Akhir<select name="condition_rating" ' . ($locked ? 'disabled' : '') . '><option>Excellent</option><option>Good</option><option>Fair</option><option>Need Repair</option><option>Need Replace</option></select></label>';
        echo '<label>Catatan Kondisi Fisik<textarea name="physical_condition" ' . ($locked ? 'readonly' : '') . '>' . e($latestReport['physical_condition'] ?? $schedule['physical_condition'] ?? '') . '</textarea></label><label>Catatan Teknisi<textarea name="additional_notes" ' . ($locked ? 'readonly' : '') . '>' . e($latestReport['additional_notes'] ?? '') . '</textarea></label>';
        echo '<label>Nama Pemakai PC / User<input name="signature_name" value="' . e($latestReport['signature_name'] ?? '') . '" ' . ($locked ? 'readonly' : '') . '></label><canvas id="sig" class="signature-pad"></canvas><input type="hidden" id="signatureData" name="signature_data"><p><button type="button" class="btn" id="clearSig" ' . ($locked ? 'disabled' : '') . '>Clear Signature</button></p>';
        echo '<div id="photoProcessProgressBox" style="display:none;margin-top:12px;margin-bottom:10px;padding:12px 14px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;">'
            . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">'
            . '<span id="photoProgressLabel" style="font-size:13px;font-weight:700;color:#0369a1;">Memproses foto...</span>'
            . '<span id="photoProgressPct" style="font-size:14px;font-weight:800;color:#0284c7;background:#e0f2fe;padding:2px 8px;border-radius:999px;">0%</span>'
            . '</div>'
            . '<div style="width:100%;height:10px;background:#dbeafe;border-radius:999px;overflow:hidden;">'
            . '<div id="photoProgressBar" style="width:0%;height:100%;background:linear-gradient(90deg,#2563eb,#0ea5e9);border-radius:999px;transition:width 0.2s ease;"></div>'
            . '</div>'
            . '<div id="photoProgressDetail" style="font-size:11.5px;color:#475569;margin-top:6px;">Menyiapkan...</div>'
            . '</div>';
        echo $locked ? '<p class="muted">Report locked. Schedule complete.</p><a class="btn" href="' . route_url('report_print', ['id' => $id]) . '">Lihat Report</a>' : '<button type="submit" id="btnSubmitJob" class="btn primary" style="width:100%;padding:12px;font-size:15px;">Simpan Pekerjaan & Selesai</button>';
        echo '</form></section>';

        // Modal Peringatan Durasi di Bawah Minimal
        echo '<div id="durationWarningModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.75);z-index:99999;align-items:center;justify-content:center;padding:16px;">'
            . '<div style="background:#fff;border-radius:14px;padding:22px 20px;max-width:380px;width:100%;box-shadow:0 20px 25px -5px rgba(0,0,0,0.3);text-align:center;">'
            . '<div style="font-size:42px;line-height:1;margin-bottom:12px;">⚠️</div>'
            . '<h3 style="margin:0 0 10px;font-size:17px;font-weight:700;color:#0f172a;">Peringatan Durasi</h3>'
            . '<p style="font-size:14px;color:#334155;line-height:1.5;margin:0 0 16px;font-weight:500;">'
            . 'Anda mengerjakannya job desk ini di bawah minimal waktu pengerjaan , apakah Anda yakin sudah selesai'
            . '</p>'
            . '<div id="modalDurationDetail" style="font-size:13px;background:#f1f5f9;border-radius:8px;padding:8px 12px;margin-bottom:18px;color:#475569;text-align:left;">'
            . 'Waktu berjalan: <strong id="modalElapsedText">0 Menit</strong><br>'
            . 'Estimasi minimal: <strong id="modalMinText">0 Menit</strong>'
            . '</div>'
            . '<div style="display:flex;gap:10px;">'
            . '<button type="button" id="btnModalNo" class="btn" style="flex:1;padding:12px;font-size:15px;font-weight:600;background:#e2e8f0;color:#1e293b;border:none;border-radius:8px;cursor:pointer;">Tidak</button>'
            . '<button type="button" id="btnModalYes" class="btn primary" style="flex:1;padding:12px;font-size:15px;font-weight:600;border-radius:8px;cursor:pointer;">Ya</button>'
            . '</div>'
            . '</div>'
            . '</div>';

        if ($locked && $latestReport) {
            render_photo_block('Foto Sebelum Maintenance', $latestReport['before_photos'] ?? '');
            render_photo_block('Foto Proses Maintenance', $latestReport['process_photos'] ?? '');
            render_photo_block('Foto Sesudah Maintenance', $latestReport['after_photos'] ?? '');
        }
    }

    echo '<script>';
    echo '(function(){';
    echo 'var hidden=document.getElementById("photoEvidenceMeta"),form=hidden?hidden.form:null,evidence=[];';
    echo 'function save(){if(hidden){var isConf=document.getElementById("durationConfirmed");hidden.value=JSON.stringify({captured_at:new Date().toISOString(),duration_override_approved:!!(isConf&&isConf.value==="1"),items:evidence});}}';
    echo 'function record(input){var file=input.files&&input.files[0]?input.files[0]:null,item={group:input.getAttribute("data-photo-group")||"",slot:input.getAttribute("data-photo-slot")||"",selected_at:new Date().toISOString(),live_camera_hint:input.hasAttribute("capture"),file_name:file?file.name:"",file_size:file?file.size:0,file_type:file?file.type:"",file_last_modified:file&&file.lastModified?new Date(file.lastModified).toISOString():""};evidence=evidence.filter(function(old){return !(old.group===item.group&&old.slot===item.slot);});evidence.push(item);save();if(navigator.geolocation){navigator.geolocation.getCurrentPosition(function(pos){item.gps={lat:pos.coords.latitude,lng:pos.coords.longitude,accuracy_m:pos.coords.accuracy,taken_at:new Date().toISOString()};save();},function(err){item.gps_error=err.message||"GPS tidak tersedia";save();},{enableHighAccuracy:true,timeout:10000,maximumAge:0});}}';
    echo 'document.querySelectorAll(".photo-input").forEach(function(input){input.addEventListener("change",function(){record(input);});});';
    echo 'if(form){form.addEventListener("submit",save);}';

    echo 'var status=document.getElementById("photoCompressStatus");';
    echo 'var progBox=document.getElementById("photoProcessProgressBox"),progLbl=document.getElementById("photoProgressLabel"),progPct=document.getElementById("photoProgressPct"),progBar=document.getElementById("photoProgressBar"),progDet=document.getElementById("photoProgressDetail");';
    echo 'function setProgress(pct,label,detail,isDone,isErr){';
    echo 'if(!progBox)return;progBox.style.display="block";var p=Math.max(0,Math.min(100,Math.round(pct)));';
    echo 'if(progPct)progPct.textContent=p+"%";if(progBar)progBar.style.width=p+"%";if(progLbl&&label)progLbl.textContent=label;if(progDet&&detail!==undefined)progDet.textContent=detail;';
    echo 'if(isErr){progBox.style.background="#fef2f2";progBox.style.borderColor="#fecaca";if(progLbl)progLbl.style.color="#b91c1c";if(progPct){progPct.style.background="#fee2e2";progPct.style.color="#b91c1c";}if(progBar)progBar.style.background="#ef4444";}';
    echo 'else if(isDone){progBox.style.background="#f0fdf4";progBox.style.borderColor="#bbf7d0";if(progLbl)progLbl.style.color="#15803d";if(progPct){progPct.style.background="#dcfce7";progPct.style.color="#15803d";}if(progBar)progBar.style.background="#16a34a";}';
    echo 'else{progBox.style.background="#f0f9ff";progBox.style.borderColor="#bae6fd";if(progLbl)progLbl.style.color="#0369a1";if(progPct){progPct.style.background="#e0f2fe";progPct.style.color="#0284c7";}if(progBar)progBar.style.background="linear-gradient(90deg,#2563eb,#0ea5e9)";}';
    echo '}';

    echo 'async function compressFile(file,onStep){';
    echo 'if(!file||!file.type.match(/^image\\//)){if(onStep)onStep(100,"Foto siap diunggah");return file;}';
    echo 'var origKb=Math.round(file.size/1024);';
    echo 'if(onStep)onStep(15,"Membaca foto dari kamera...","Ukuran asli: "+origKb+" KB");';
    echo 'if(file.size<=1048576){if(onStep)onStep(100,"✅ Foto siap diunggah (100%)","Ukuran: "+origKb+" KB (tidak perlu kompresi)");return file;}';
    echo 'var img=new Image();var url=URL.createObjectURL(file);';
    echo 'await new Promise(function(resolve,reject){img.onload=resolve;img.onerror=reject;img.src=url;});';
    echo 'if(onStep)onStep(35,"Menyesuaikan resolusi gambar...","Resolusi asli: "+img.width+"x"+img.height+" px");';
    echo 'var maxSide=1600,scale=Math.min(1,maxSide/Math.max(img.width,img.height)),w=Math.max(1,Math.round(img.width*scale)),h=Math.max(1,Math.round(img.height*scale)),canvas=document.createElement("canvas"),ctx=canvas.getContext("2d");';
    echo 'canvas.width=w;canvas.height=h;ctx.drawImage(img,0,0,w,h);URL.revokeObjectURL(url);';
    echo 'var quality=.82,blob=null,stepIdx=0;';
    echo 'while(quality>=.45){';
    echo 'stepIdx++;var curPct=Math.min(92,45+stepIdx*10);';
    echo 'if(onStep)onStep(curPct,"Mengompres ukuran foto...","Tahap "+stepIdx+" (kualitas "+Math.round(quality*100)+"%)");';
    echo 'blob=await new Promise(function(resolve){canvas.toBlob(resolve,"image/jpeg",quality);});';
    echo 'if(blob&&blob.size<=1048576){break;}quality-=.08;';
    echo '}';
    echo 'if(!blob){if(onStep)onStep(100,"✅ Foto siap diunggah (100%)","Ukuran: "+origKb+" KB");return file;}';
    echo 'var finalFile=new File([blob],file.name.replace(/\\.[^.]+$/,"")+".jpg",{type:"image/jpeg",lastModified:Date.now()});';
    echo 'var finalKb=Math.round(finalFile.size/1024);';
    echo 'if(onStep)onStep(100,"✅ Foto selesai diproses (100%)","Ukuran awal "+origKb+" KB -> "+finalKb+" KB (Siap disimpan)");';
    echo 'return finalFile;';
    echo '}';

    echo 'var isCompressing=false;';
    echo 'document.querySelectorAll(".photo-input").forEach(function(input){input.addEventListener("change",async function(){';
    echo 'if(!input.files||!input.files.length){return;}';
    echo 'isCompressing=true;';
    echo 'var submitBtn=document.getElementById("btnSaveBefore")||document.getElementById("btnSubmitJob");';
    echo 'var origBtnText=submitBtn?submitBtn.textContent:"";';
    echo 'if(submitBtn){submitBtn.disabled=true;}';
    echo 'if(status){status.textContent="Memproses & mengompres foto kamera...";}';
    echo 'setProgress(10,"Memproses foto kamera...","Mohon tunggu sebentar...",false,false);';
    echo 'try{';
    echo 'var dt=new DataTransfer();';
    echo 'for(var i=0;i<input.files.length;i++){';
    echo 'var cFile=await compressFile(input.files[i],function(p,lbl,det){setProgress(p,lbl,det,p>=100,false);if(submitBtn&&p<100){submitBtn.textContent="⏳ Memproses Foto ("+Math.round(p)+"%)...";}});';
    echo 'dt.items.add(cFile);';
    echo '}';
    echo 'input.files=dt.files;';
    echo 'record(input);';
    echo 'if(input.getAttribute("data-photo-group")==="before"&&input.files[0]){';
    echo 'var prevBox=document.getElementById("beforePhotoPreviewBox"),prevImg=document.getElementById("beforePhotoPreviewImg"),prevInfo=document.getElementById("beforePhotoPreviewInfo");';
    echo 'if(prevBox&&prevImg){prevImg.src=URL.createObjectURL(input.files[0]);prevBox.style.display="block";if(prevInfo)prevInfo.textContent="Foto Sebelum siap dikirim ("+Math.round(input.files[0].size/1024)+" KB)";}';
    echo '}';
    echo 'if(status){status.textContent="Foto siap diunggah (100%). Silakan klik tombol Simpan di bawah.";}';
    echo '}catch(err){';
    echo 'setProgress(100,"Gagal mengompres otomatis, menggunakan file asli",(err&&err.message)?err.message:"",false,true);';
    echo '}finally{';
    echo 'isCompressing=false;';
    echo 'if(submitBtn){submitBtn.disabled=false;submitBtn.textContent=origBtnText;}';
    echo '}';
    echo '});});';

    echo 'var beforeForm=document.getElementById("beforePhotoForm");';
    echo 'if(beforeForm){';
    echo 'beforeForm.addEventListener("submit",function(e){';
    echo 'if(isCompressing){alert("Mohon tunggu proses persiapan foto hingga 100%.");e.preventDefault();return false;}';
    echo 'var fInput=beforeForm.querySelector("input[name=\'before_photos[]\']");';
    echo 'if(!fInput||!fInput.files||!fInput.files.length){alert("Silakan ambil Foto Sebelum terlebih dahulu.");e.preventDefault();return false;}';
    echo 'e.preventDefault();';
    echo 'save();';
    echo 'try{localStorage.setItem("pcconnect_before_' . (int)$id . '", Date.now().toString());}catch(ex){}';
    echo 'var btn=document.getElementById("btnSaveBefore");';
    echo 'var origTxt=btn?btn.textContent:"Simpan Foto Sebelum & Buka Checklist";';
    echo 'if(btn){btn.disabled=true;btn.textContent="⏳ Mengunggah Foto... 0%";}';
    echo 'setProgress(2,"📤 Mengunggah Foto Sebelum ke Server...","Menghubungkan ke server...",false,false);';
    echo 'var fd=new FormData(beforeForm);';
    echo 'var xhr=new XMLHttpRequest();';
    echo 'var serverTimer=null,serverSec=0,curSimPct=75;';
    echo 'xhr.open("POST",beforeForm.action||window.location.href,true);';
    echo 'xhr.upload.onprogress=function(ev){';
    echo 'if(ev.lengthComputable&&ev.total>0){';
    echo 'var rawPct=Math.round((ev.loaded/ev.total)*100);';
    echo 'var uiPct=Math.min(75,Math.max(3,Math.round(rawPct*0.75)));';
    echo 'var loadedKb=Math.round(ev.loaded/1024),totalKb=Math.round(ev.total/1024);';
    echo 'setProgress(uiPct,"📤 Mengunggah Foto Sebelum ("+rawPct+"% terkirim)","Terkirim "+loadedKb+" KB dari "+totalKb+" KB",false,false);';
    echo 'if(btn)btn.textContent="⏳ Mengunggah Foto... "+uiPct+"%";';
    echo 'if(rawPct>=100&&!serverTimer){';
    echo 'curSimPct=76;';
    echo 'setProgress(curSimPct,"🔍 Memvalidasi Lokasi GPS & Kode Challenge...","Foto diterima server, sedang memverifikasi bukti foto (0 dtk)...",false,false);';
    echo 'if(btn)btn.textContent="⏳ Memverifikasi Foto... "+curSimPct+"%";';
    echo 'serverTimer=setInterval(function(){';
    echo 'serverSec++;if(curSimPct<96){curSimPct+=2;}';
    echo 'setProgress(curSimPct,"🔍 Memvalidasi Lokasi GPS & Kode Challenge...","Proses verifikasi server berjalan aktif ("+serverSec+" detik)...",false,false);';
    echo 'if(btn)btn.textContent="⏳ Memverifikasi Foto... "+curSimPct+"% ("+serverSec+"s)";';
    echo '},800);';
    echo '}';
    echo '}';
    echo '};';
    echo 'xhr.onload=function(){';
    echo 'if(serverTimer){clearInterval(serverTimer);serverTimer=null;}';
    echo 'if(xhr.status>=200&&xhr.status<400){';
    echo 'setProgress(100,"✅ Foto Sebelum Berhasil Diproses (100%)!","Membuka halaman checklist pengerjaan...",true,false);';
    echo 'if(btn)btn.textContent="✅ Selesai (100%) - Memuat...";';
    echo 'var targetUrl=xhr.responseURL||window.location.href;';
    echo 'window.location.href=targetUrl;';
    echo '}else{';
    echo 'setProgress(100,"❌ Gagal menyimpan ke server (HTTP "+xhr.status+")","Silakan klik tombol Simpan untuk mencoba lagi.",false,true);';
    echo 'if(btn){btn.disabled=false;btn.textContent=origTxt;}';
    echo '}';
    echo '};';
    echo 'xhr.onerror=function(){';
    echo 'if(serverTimer){clearInterval(serverTimer);serverTimer=null;}';
    echo 'setProgress(100,"❌ Koneksi jaringan terputus saat mengunggah","Periksa sinyal internet/Wi-Fi Anda lalu coba lagi.",false,true);';
    echo 'if(btn){btn.disabled=false;btn.textContent=origTxt;}';
    echo '};';
    echo 'xhr.send(fd);';
    echo 'return false;';
    echo '});';
    echo '}';

    if ($hasBeforePhotos && !$locked) {
        $startTimeMs = (int)($startTime * 1000);
        $minMs = (int)($minimumMinutes * 60 * 1000);
        echo 'var localBeforeKey = "pcconnect_before_' . (int)$id . '";';
        echo 'var localBefore = null; try{localBefore = localStorage.getItem(localBeforeKey);}catch(e){}';
        echo 'var serverStartTimeMs = ' . $startTimeMs . ';';
        echo 'var startTimeMs = (localBefore && parseInt(localBefore,10) > 0) ? parseInt(localBefore,10) : serverStartTimeMs;';
        echo 'var minMinutes = ' . (int)$minimumMinutes . ';';
        echo 'var minMs = ' . $minMs . ';';

        echo 'function updateTimer(){';
        echo 'var now=Date.now();var elapsedMs=Math.max(0,now-startTimeMs);var elapsedSec=Math.floor(elapsedMs/1000);var elMin=Math.floor(elapsedSec/60);var elSec=elapsedSec%60;var elStr=(elMin<10?"0":"")+elMin+":"+(elSec<10?"0":"")+elSec;';
        echo 'var badge=document.getElementById("liveTimerBadge");var status=document.getElementById("liveTimerStatus");if(!badge)return;';
        echo 'if(elapsedMs<minMs){';
        echo 'var remainSec=Math.ceil((minMs-elapsedMs)/1000);var remMin=Math.floor(remainSec/60);var remSec=remainSec%60;var remStr=(remMin<10?"0":"")+remMin+":"+(remSec<10?"0":"")+remSec;';
        echo 'badge.style.background="#fef3c7";badge.style.color="#b45309";badge.textContent="⏳ "+elStr+" / "+minMinutes+"m";';
        echo 'if(status)status.textContent="Sisa waktu pengerjaan: "+remStr+" lagi sebelum batas minimal kumulasi tercapai.";';
        echo '}else{';
        echo 'badge.style.background="#dcfce7";badge.style.color="#15803d";badge.textContent="✅ "+elStr+" (Lengkap)";';
        echo 'if(status)status.textContent="Batas durasi kumulatif terpenuhi ("+minMinutes+"m). Anda dapat menyelesaikan pengerjaan.";';
        echo '}';
        echo '}updateTimer();setInterval(updateTimer,1000);';

        echo 'var durationModal = document.getElementById("durationWarningModal");';
        echo 'var btnModalYes = document.getElementById("btnModalYes");';
        echo 'var btnModalNo = document.getElementById("btnModalNo");';
        echo 'var jobForm = document.getElementById("jobForm");';

        echo 'function interceptDurationSubmit(e){';
        echo 'var afterInput = jobForm ? jobForm.querySelector("input[name=\'after_photos[]\']") : null;';
        echo 'if(afterInput && !afterInput.files.length){';
        echo 'alert("Foto sesudah wajib diambil dari kamera langsung.");if(e)e.preventDefault();return false;';
        echo '}';
        echo 'var sigCanvas = document.getElementById("sig");';
        echo 'if(sigCanvas){document.getElementById("signatureData").value = sigCanvas.toDataURL("image/png");}';
        echo 'var now=Date.now();var elapsedMs=Math.max(0,now-startTimeMs);var elapsedMin=Math.round((elapsedMs/60000)*10)/10;';
        echo 'document.getElementById("realDurationMinutes").value = elapsedMin;';
        echo 'var durationConfirmed = document.getElementById("durationConfirmed");';
        echo 'if(elapsedMs < minMs && (!durationConfirmed || durationConfirmed.value !== "1")){';
        echo 'if(e){e.preventDefault();e.stopPropagation();}';
        echo 'if(durationModal){';
        echo 'document.getElementById("modalElapsedText").textContent = elapsedMin + " Menit";';
        echo 'document.getElementById("modalMinText").textContent = minMinutes + " Menit";';
        echo 'durationModal.style.display = "flex";';
        echo '}else{';
        echo 'if(confirm("Anda mengerjakannya job desk ini di bawah minimal waktu pengerjaan , apakah Anda yakin sudah selesai")){';
        echo 'if(durationConfirmed)durationConfirmed.value="1";jobForm.submit();';
        echo '}';
        echo '}';
        echo 'return false;';
        echo '}';
        echo 'return true;';
        echo '}';

        echo 'if(jobForm){jobForm.addEventListener("submit", interceptDurationSubmit);}';

        echo 'if(btnModalNo){';
        echo 'btnModalNo.addEventListener("click", function(){';
        echo 'if(durationModal)durationModal.style.display = "none";';
        echo '});}';

        echo 'if(btnModalYes){';
        echo 'btnModalYes.addEventListener("click", function(){';
        echo 'var durationConfirmed = document.getElementById("durationConfirmed");';
        echo 'if(durationConfirmed)durationConfirmed.value = "1";';
        echo 'var hidden = document.getElementById("photoEvidenceMeta");';
        echo 'if(hidden){try{var p=JSON.parse(hidden.value||"{}");p.duration_override_approved=true;hidden.value=JSON.stringify(p);}catch(ex){}}';
        echo 'if(durationModal)durationModal.style.display = "none";';
        echo 'try{localStorage.removeItem(localBeforeKey);}catch(e){}';
        echo 'if(jobForm)jobForm.submit();';
        echo '});}';

        echo 'if(window.location.search.indexOf("duration_warning=1") !== -1 && durationModal){';
        echo 'var now=Date.now();var elapsedMs=Math.max(0,now-startTimeMs);var elapsedMin=Math.round((elapsedMs/60000)*10)/10;';
        echo 'document.getElementById("modalElapsedText").textContent = elapsedMin + " Menit";';
        echo 'document.getElementById("modalMinText").textContent = minMinutes + " Menit";';
        echo 'durationModal.style.display = "flex";';
        echo '}';
    }

    echo 'var c=document.getElementById("sig"),x=c?c.getContext("2d"):null,down=false;function fit(){if(!c)return;c.width=c.clientWidth;c.height=160;if(x){x.lineWidth=2;x.lineCap="round";}}fit();function pos(e){if(!c)return{x:0,y:0};var r=c.getBoundingClientRect(),t=e.touches?e.touches[0]:e;return{x:t.clientX-r.left,y:t.clientY-r.top};}if(c&&x){c.addEventListener("pointerdown",function(e){down=true;var p=pos(e);x.beginPath();x.moveTo(p.x,p.y);});c.addEventListener("pointermove",function(e){if(!down)return;var p=pos(e);x.lineTo(p.x,p.y);x.stroke();});window.addEventListener("pointerup",function(){down=false;});var clr=document.getElementById("clearSig");if(clr)clr.onclick=function(){x.clearRect(0,0,c.width,c.height);};}';
    echo '})();';
    echo '</script>';
    render_mobile_footer();
}

/**
 * Modul Mobile PWA: Riwayat
 * Proses: Menampilkan log riwayat pekerjaan yang pernah diselesaikan oleh teknisi/pengguna.
 */
function mobile_history(PDO $pdo): void
{
    $user = require_technician();
    expire_stale_mobile_work_or_logout($pdo, $user);
    render_mobile_header('History', $user);
    $stmt = $pdo->prepare("SELECT s.*, 
        COALESCE(ai.asset_code, s.pc_id, s.printer_id, CONCAT('Unit #', s.id)) AS asset_id, 
        COALESCE(ai.custodian_name, ma.owner_name, p.owner_name, pr.printer_name, '-') AS owner_name,
        COALESCE(ai.asset_name, ma.name, p.computer_name, pr.printer_name, pr.location, '-') AS computer_name
        FROM maintenance_schedules s 
        LEFT JOIN maintenance_assets ma ON ma.id = s.maintenance_asset_id 
        LEFT JOIN asset_items ai ON ai.id = COALESCE(s.asset_item_id, ma.asset_item_id) 
        LEFT JOIN pcs p ON (p.pc_id = s.pc_id OR (s.pc_id IS NULL AND ma.pc_id IS NOT NULL AND p.pc_id COLLATE utf8mb4_unicode_ci = ma.pc_id COLLATE utf8mb4_unicode_ci)) 
        LEFT JOIN printers pr ON (pr.prn_id = s.printer_id OR (s.printer_id IS NULL AND ma.printer_id IS NOT NULL AND pr.prn_id COLLATE utf8mb4_unicode_ci = ma.printer_id COLLATE utf8mb4_unicode_ci)) 
        WHERE s.technician_id=? 
        ORDER BY s.scheduled_date DESC, s.id DESC LIMIT 80");
    $stmt->execute([$user['id']]);
    echo '<section class="panel"><h1>History</h1></section>';
    foreach ($stmt as $row) {
        $displayCode = (string)$row['asset_id'];
        $codeHtml = e($displayCode);
        echo '<section class="panel"><div class="split"><div><strong>' . $codeHtml . '</strong><br><span class="muted">' . e($row['scheduled_date']) . ' - ' . e($row['owner_name']) . ' (' . e($row['computer_name'] ?: '-') . ')</span><br><span class="badge">' . e($row['status']) . '</span></div><a class="btn" href="' . route_url('mobile_job', ['id' => $row['id']]) . '">Buka</a></div></section>';
    }
    render_mobile_footer();
}

