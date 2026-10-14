<?php

declare(strict_types=1);

/**
 * PcConnect Field Service Mobile Module
 * Khusus aplikasi mobile smartphone untuk teknisi Corrective Maintenance di lapangan.
 */

function require_corrective_technician(): array
{
    $user = current_user();
    if (!$user) {
        redirect_to('mobile_service_login');
    }
    if (!in_array($user['role'], ['admin', 'corrective_maintenance'], true)) {
        flash('Akses ditolak. Portal ini khusus untuk teknisi dengan role Corrective Maintenance.', 'err');
        redirect_to('mobile_service_login');
    }
    return $user;
}

/**
 * Modul Field Service: Login Mobile
 * Proses: Halaman login khusus untuk teknisi lapangan (Field Service) yang dioptimasi untuk mobile.
 */
function handle_route_mobile_service_login(PDO $pdo): void
{
    ensure_corrective_maintenance_schema($pdo);

    // Jika sudah login dengan role yang sesuai, langsung redirect ke mobile_service
    $curr = current_user();
    if ($curr && in_array($curr['role'], ['admin', 'corrective_maintenance'], true)) {
        redirect_to('mobile_service');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, (string)$user['password_hash'])) {
            if (!in_array($user['role'], ['admin', 'corrective_maintenance'], true)) {
                flash('Akses ditolak. Akun Anda (' . e(function_exists('role_label') ? role_label((string)$user['role']) : $user['role']) . ') bukan role Corrective Maintenance.', 'err');
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['last_activity_at'] = time();
                flash('Selamat datang, ' . $user['name'] . '! Anda berhasil masuk ke Field Service.');
                redirect_to('mobile_service');
            }
        } else {
            flash('Login gagal. Periksa username dan password Anda.', 'err');
        }
    }

    $flash = flash();
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
        <title>Login - PcConnect Field Service</title>
        <style>
            * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
            body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #0f172a; color: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
            .login-card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 30px 24px; width: 100%; max-width: 400px; box-shadow: 0 12px 30px rgba(0,0,0,0.5); }
            .brand-logo { text-align: center; margin-bottom: 24px; }
            .brand-icon { font-size: 46px; display: block; margin-bottom: 8px; }
            .brand-title { font-size: 21px; font-weight: 800; color: #38bdf8; margin: 0; }
            .brand-subtitle { font-size: 13px; color: #94a3b8; margin-top: 4px; }
            label { display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin: 16px 0 6px; }
            input { width: 100%; background: #0f172a; border: 1px solid #475569; color: #f8fafc; border-radius: 10px; padding: 13px 14px; font-size: 15px; outline: none; transition: border-color 0.2s; }
            input:focus { border-color: #38bdf8; box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.25); }
            .btn-login { width: 100%; background: linear-gradient(135deg, #0284c7, #0369a1); color: #fff; border: none; border-radius: 10px; padding: 14px; font-size: 16px; font-weight: 700; cursor: pointer; margin-top: 24px; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.4); }
            .btn-login:active { transform: translateY(1px); filter: brightness(0.95); }
            .flash { padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; line-height: 1.4; }
            .flash-ok { background: #065f46; color: #d1fae5; border: 1px solid #047857; }
            .flash-err { background: #881337; color: #ffe4e6; border: 1px solid #be123c; }
            .login-footer { text-align: center; margin-top: 22px; font-size: 12px; color: #64748b; line-height: 1.5; }
        </style>
    </head>
    <body>
        <div class="login-card">
            <div class="brand-logo">
                <span class="brand-icon">🛠️</span>
                <h1 class="brand-title">PcConnect Field Service</h1>
                <div class="brand-subtitle">Portal Mobile Corrective Maintenance</div>
            </div>

            <?php if ($flash): ?>
                <div class="flash <?= $flash['type'] === 'err' ? 'flash-err' : 'flash-ok' ?>">
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <label>Username
                    <input name="username" required autofocus placeholder="Username teknisi">
                </label>
                <label>Password
                    <input type="password" name="password" required placeholder="••••••••">
                </label>
                <button class="btn-login">Masuk ke Field Service →</button>
            </form>

            <div class="login-footer">
                Khusus teknisi role Corrective Maintenance.<br>
                Sesi otomatis logout jika tidak aktif selama 1 jam.
            </div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Modul Field Service: Logout Mobile
 * Proses: Mengakhiri sesi teknisi lapangan dan kembali ke halaman login mobile.
 */
function handle_route_mobile_service_logout(): never
{
    unset($_SESSION['user_id'], $_SESSION['last_activity_at'], $_SESSION['csrf'], $_SESSION['pending_mobile_code']);
    flash('Anda telah berhasil logout dari Field Service.');
    redirect_to('mobile_service_login');
}

/**
 * Modul Field Service: Dashboard
 * Proses: Menampilkan menu utama atau antarmuka utama teknisi lapangan.
 */
function handle_route_mobile_service(PDO $pdo): void
{
    $user = require_corrective_technician();
    ensure_corrective_maintenance_schema($pdo);

    // Ambil statistik tugas teknisi
    $stats = $pdo->query("SELECT 
        COUNT(CASE WHEN status IN ('open', 'assigned') THEN 1 END) AS count_open,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) AS count_progress,
        COUNT(CASE WHEN status = 'pending_part' THEN 1 END) AS count_pending_part,
        COUNT(CASE WHEN status IN ('resolved', 'closed') AND DATE(resolved_at) = CURRENT_DATE() THEN 1 END) AS count_resolved_today
    FROM corrective_tickets")->fetch(PDO::FETCH_ASSOC);

    // Ambil 4 tiket tugas terbaru
    $recentTickets = $pdo->query("SELECT t.*, 
                                  COALESCE(ai.asset_code, t.pc_id, t.prn_id, 'Unit') AS display_asset_code,
                                  COALESCE(ai.asset_name, ma.name, 'Unit') AS asset_name
                                  FROM corrective_tickets t
                                  LEFT JOIN asset_items ai ON ai.id = t.asset_item_id
                                  LEFT JOIN maintenance_assets ma ON ma.id = t.maintenance_asset_id
                                  WHERE t.status IN ('open', 'assigned', 'in_progress', 'pending_part')
                                  ORDER BY t.priority = 'critical' DESC, t.created_at DESC
                                  LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);

    render_mobile_header('Field Service Mobile', $user);
    ?>
    <!-- Action Cards -->
    <div class="panel" style="background:linear-gradient(135deg, #0369a1, #0f172a);border-color:#0284c7;">
        <h2 style="font-size:18px;color:#0369a1;margin-bottom:6px;">⚡ Perbaikan Cepat di Tempat</h2>
        <p style="font-size:13px;color:#475569;margin:0 0 14px;">Scan QR Unit Aset untuk buka case baru atau langsung selesaikan di lokasi.</p>
        <a href="<?= route_url('mobile_service_scan') ?>" class="btn btn-primary" style="font-size:16px;padding:14px;background:#38bdf8;color:#0f172a;font-weight:700;">
            📷 SCAN QR CODE UNIT
        </a>
    </div>

    <!-- Ringkasan Status -->
    <?php
    $renderCorrectiveCard = function($number, $label, $iconPath, $themeIdx) {
        $themes = [
            ['#eff6ff', '#1e3a8a', '#3b82f6'], // blue
            ['#f0fdf4', '#14532d', '#22c55e'], // green
            ['#fffbeb', '#78350f', '#f59e0b'], // yellow
            ['#fef2f2', '#7f1d1d', '#ef4444'], // red
        ];
        $t = $themes[$themeIdx];
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="' . $t[2] . '"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="' . $iconPath . '"/></svg>';
        $bgImage = "data:image/svg+xml;base64," . base64_encode($svg);

        return '<div style="background-color: ' . $t[0] . '; color: ' . $t[1] . '; border-left: 4px solid ' . $t[2] . '; position: relative; overflow: hidden; padding: 14px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">'
             . '<div style="position: absolute; right: -8px; top: -8px; width: 65px; height: 65px; opacity: 0.15; background-image: url(\'' . $bgImage . '\'); background-size: contain; background-repeat: no-repeat; transform: rotate(-10deg);"></div>'
             . '<div style="position: relative; z-index: 1;">'
             . '<strong style="font-size: 24px; display: block; margin-bottom: 2px; line-height: 1;">' . e($number) . ''
             . '<span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8;">' . e($label) . '</span>'
             . '</div></div>';
    };
    ?>
    <div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:10px;margin-bottom:14px;">
        <?= $renderCorrectiveCard((int)($stats['count_open'] ?? 0), 'Tiket Masuk', 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z', 3) ?>
        <?= $renderCorrectiveCard((int)($stats['count_progress'] ?? 0), 'Sedang Dikerjakan', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 2) ?>
        <?= $renderCorrectiveCard((int)($stats['count_pending_part'] ?? 0), 'Tunggu Sparepart', 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10', 0) ?>
        <?= $renderCorrectiveCard((int)($stats['count_resolved_today'] ?? 0), 'Selesai Hari Ini', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 1) ?>
    </div>

    <!-- Tiket Butuh Tindakan -->
    <div class="panel">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <h2 style="margin:0;">🚨 Tugas Tiket Menunggu</h2>
            <a href="<?= route_url('mobile_service_tasks') ?>" style="font-size:12px;color:#0284c7;">Lihat Semua →</a>
        </div>

        <?php if (!$recentTickets): ?>
            <p style="font-size:13px;color:#64748b;margin:10px 0;">Tidak ada tiket kerusakan yang menunggu.</p>
        <?php else: ?>
            <?php foreach ($recentTickets as $t): ?>
                <div style="background:#fff;;border-radius:8px;padding:12px;margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                        <div>
                            <span style="font-weight:700;color:#0284c7;font-size:14px;"><?= e($t['ticket_code']) ?></span>
                            <div style="font-size:12px;color:#64748b;font-weight:600;margin-top:2px;">
                                <?= e($t['display_asset_code']) ?>
                            </div>
                        </div>
                        <div>
                            <?= ticket_priority_badge($t['priority']) ?>
                        </div>
                    </div>
                    <p style="font-size:13px;margin:6px 0;color:#e2e8f0;"><?= e($t['subject']) ?></p>
                    <div style="font-size:11px;color:#64748b;display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
                        <span>📍 <?= e($t['location_label'] ?: '-') ?></span>
                        <a href="<?= route_url('mobile_service_ticket', ['id' => $t['id']]) ?>" class="btn btn-primary" style="display:inline-block;width:auto;padding:6px 12px;font-size:12px;margin:0;">
                            Buka Tiket →
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
    render_mobile_footer('dashboard');
}

/**
 * Modul Field Service: Scan Aset
 * Proses: Membuka antarmuka kamera/scanner barcode bagi teknisi untuk memindai aset di lapangan.
 */
function handle_route_mobile_service_scan(PDO $pdo): void
{
    $user = require_corrective_technician();

    $rawInput = trim((string)($_POST['code'] ?? $_GET['code'] ?? $_GET['eqr'] ?? ''));

    if ($rawInput !== '') {
        $maintId = resolve_maintenance_asset_id_from_raw_input($pdo, $rawInput);

        if (!$maintId || $maintId <= 0) {
            flash("Unit Aset dengan kode/QR tersebut tidak ditemukan. Pastikan unit telah terdaftar.", 'err');
            redirect_to('mobile_service_scan');
        }

        // Cek apakah ada tiket open/in_progress pada unit ini
        $stmt2 = $pdo->prepare("SELECT id FROM corrective_tickets WHERE maintenance_asset_id = ? AND status IN ('open', 'assigned', 'in_progress', 'pending_part') ORDER BY created_at DESC LIMIT 1");
        $stmt2->execute([$maintId]);
        $existingTicketId = (int)($stmt2->fetchColumn() ?: 0);

        if ($existingTicketId > 0) {
            flash("Unit terverifikasi! Membuka tiket aktif terkait...");
            redirect_to('mobile_service_ticket', ['id' => $existingTicketId, 'scanned' => 1]);
        } else {
            flash("Unit terverifikasi! Silakan catat perbaikan di bawah.");
            redirect_to('mobile_service_direct', ['maintenance_asset_id' => $maintId]);
        }
    }

    render_mobile_header('Scan QR Unit Lapangan', $user);
    ?>
    <div class="panel" style="text-align:center;">
        <h2>📷 Scan QR Unit Aset</h2>
        <p style="font-size:13px;color:#64748b;margin:0 0 12px;">Arahkan kamera ke stiker QR Code pada unit fisik.</p>

        <div id="reader" style="width:100%;max-width:320px;margin:0 auto;border-radius:12px;overflow:hidden;background:#000;"></div>
        <p id="camStatus" style="font-size:12px;color:#0284c7;margin-top:8px;">Menyiapkan kamera live scan...</p>

        <form method="post" id="scanForm" style="margin-top:16px;">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <label style="text-align:left;">Atau Ketik Kode Unit Aset:
                <input name="code" id="codeManual" placeholder="Contoh: IT-CMP-000001" required>
            </label>
            <button class="btn btn-primary" style="margin-top:8px;">Lanjut Eksekusi</button>
        </form>
    </div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
    (function() {
        var html5QrcodeScanner = null;
        var submitted = false;

        function onScanSuccess(decodedText, decodedResult) {
            if (submitted) return;
            submitted = true;
            document.getElementById('codeManual').value = decodedText;
            document.getElementById('camStatus').textContent = "QR Terbaca: " + decodedText + ". Memproses...";
            document.getElementById('scanForm').submit();
        }

        if (window.Html5Qrcode) {
            var html5Qr = new Html5Qrcode("reader");
            html5Qr.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 220, height: 220 } },
                onScanSuccess
            ).catch(function(err) {
                document.getElementById('camStatus').textContent = "Kamera tidak dapat diakses langsung. Silakan ketik kode manual di bawah.";
            });
        }
    })();
    </script>
    <?php
    render_mobile_footer('scan');
}

/**
 * Modul Field Service: Direct Action
 * Proses: Mengambil tindakan langsung pada aset setelah berhasil di-scan tanpa melalui jadwal (ad-hoc).
 */
function handle_route_mobile_service_direct(PDO $pdo): void
{
    $user = require_corrective_technician();
    ensure_corrective_maintenance_schema($pdo);
    $maintId = (int)($_GET['maintenance_asset_id'] ?? 0);
    $unit = get_maintenance_asset_unit($pdo, $maintId);

    if (!$unit) {
        flash('Unit Aset tidak ditemukan.', 'err');
        redirect_to('mobile_service_scan');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $code = generate_next_ticket_code($pdo);
        $subject = trim((string)($_POST['subject'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $priority = trim((string)($_POST['priority'] ?? 'medium'));
        $actionType = trim((string)($_POST['action_type'] ?? 'hardware_repair'));
        $solution = trim((string)($_POST['solution_details'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'resolved'));
        $repairCost = (float)($_POST['repair_cost'] ?? 0);
        $vendorName = trim((string)($_POST['vendor_name'] ?? ''));

        if ($subject === '' || $solution === '') {
            flash('Keluhan masalah dan solusi tindakan perbaikan wajib diisi.', 'err');
            redirect_to('mobile_service_direct', ['maintenance_asset_id' => $maintId]);
        }

        // Upload foto sebelum & sesudah
        $photoBefore = null;
        $photoAfter = null;
        $uploadDir = __DIR__ . '/../../public/uploads/tickets';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        if (!empty($_FILES['photo_before']['tmp_name']) && is_uploaded_file($_FILES['photo_before']['tmp_name'])) {
            $ext = strtolower(pathinfo((string)$_FILES['photo_before']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $filename = 'before_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['photo_before']['tmp_name'], $uploadDir . '/' . $filename)) {
                    $photoBefore = 'uploads/tickets/' . $filename;
                }
            }
        }

        if (!empty($_FILES['photo_after']['tmp_name']) && is_uploaded_file($_FILES['photo_after']['tmp_name'])) {
            $ext = strtolower(pathinfo((string)$_FILES['photo_after']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $filename = 'after_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['photo_after']['tmp_name'], $uploadDir . '/' . $filename)) {
                    $photoAfter = 'uploads/tickets/' . $filename;
                }
            }
        }

        $assetCat = $unit['asset_category'];
        $resolvedAt = ($status === 'resolved' || $status === 'closed') ? date('Y-m-d H:i:s') : null;

        // 1. Simpan Tiket
        $stmt = $pdo->prepare("INSERT INTO corrective_tickets (
            ticket_code, asset_category, asset_item_id, maintenance_asset_id, pc_id, company_id, 
            location_label, reporter_name, reporter_nik, issue_category, priority, 
            subject, description, photo_before, status, assigned_technician_name, resolved_at,
            handled_by_type, handled_by_user_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'internal', ?)");

        $stmt->execute([
            $code, $assetCat, $unit['asset_item_id'], $maintId, $unit['pc_id'] ?: null,
            (int)($unit['company_id'] ?? 0) ?: null, $unit['location_label'] ?? '',
            $unit['custodian_name'] ?: $user['name'], $unit['custodian_nik'] ?? '', 'hardware', $priority,
            $subject, $desc, $photoBefore, $status, $user['name'], $resolvedAt,
            (int)$user['id']
        ]);
        $ticketId = (int)$pdo->lastInsertId();

        // Save requested actions if checked
        if (!empty($_POST['requested_actions']) && is_array($_POST['requested_actions'])) {
            $actStmt = $pdo->prepare("INSERT INTO corrective_ticket_actions (ticket_id, job_action_id, action_title) VALUES (?, ?, ?)");
            foreach ($_POST['requested_actions'] as $actId) {
                $aRow = $pdo->query("SELECT action_name AS action_title FROM corrective_action_types WHERE id = " . (int)$actId)->fetch();
                if ($aRow) {
                    $actStmt->execute([$ticketId, (int)$actId, $aRow['action_title']]);
                }
            }
        }

        // 2. Simpan Tindakan Reparasi
        $repStmt = $pdo->prepare("INSERT INTO corrective_repairs (
            ticket_id, technician_name, action_type, solution_details, vendor_name, repair_cost, photo_after
        ) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $repStmt->execute([
            $ticketId, $user['name'], $actionType, $solution, $vendorName ?: null, $repairCost, $photoAfter
        ]);
        $repairId = (int)$pdo->lastInsertId();

        // 3. Simpan Sparepart jika ada
        $partName = trim((string)($_POST['part_new_name'] ?? ''));
        if ($partName !== '') {
            $partOld = trim((string)($_POST['part_old_name'] ?? ''));
            $partSerial = trim((string)($_POST['part_new_serial'] ?? ''));
            $partCost = (float)($_POST['part_cost'] ?? 0);
            $pdo->prepare("INSERT INTO corrective_repair_parts (repair_id, old_part_name, new_part_name, new_part_serial, part_cost) VALUES (?, ?, ?, ?, ?)")
                ->execute([$repairId, $partOld ?: null, $partName, $partSerial ?: null, $partCost]);
        }

        flash("Perbaikan di tempat berhasil disimpan! Tiket #{$code} selesai.");
        redirect_to('mobile_service');
    }

    render_mobile_header('Eksekusi Perbaikan di Tempat', $user);
    ?>
    <div class="panel" style="border-left:4px solid #38bdf8;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <span class="badge" style="background:#0284c7;color:#fff;">UNIT TERVERIFIKASI</span>
            <?php if (!empty($unit['group_name']) || !empty($unit['type_name'])): ?>
                <span class="badge" style="background:#fff;color:#0284c7;border:1px solid #0284c7;font-size:11px;">
                    <?= e($unit['group_name'] ?: 'IT') ?> &bull; <?= e($unit['type_name'] ?: ($unit['asset_category'] ?? 'Comp')) ?>
                </span>
            <?php endif; ?>
        </div>
        <h2 style="margin:4px 0 2px;color:#0284c7;"><?= e($unit['item_asset_code'] ?: ($unit['asset_code'] ?? 'Unit Aset')) ?></h2>
        <div style="font-size:14px;font-weight:600;;margin-top:2px;"><?= e($unit['name']) ?></div>
        <?php if (!empty($unit['job_desk_name'])): ?>
            <div style="font-size:12px;color:#a5f3fc;margin-top:4px;background:#0369a1;padding:3px 8px;border-radius:6px;display:inline-block;">
                📋 Job Desk: <?= e($unit['job_desk_name']) ?>
            </div>
        <?php endif; ?>
        <div style="font-size:12px;color:#64748b;margin-top:6px;">
            👤 Pemegang: <?= e($unit['custodian_name'] ?: '-') ?> | 📍 Lokasi: <?= e($unit['location_label'] ?: '-') ?>
        </div>
    </div>

    <div class="panel">
        <h2>📝 Form Eksekusi Cepat di Lokasi</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

            <label>Subjek / Keluhan Masalah *
                <input name="subject" required placeholder="Contoh: Laptop lambat / Ganti RAM / Blue screen">
            </label>

            <label>Tingkat Prioritas
                <select name="priority">
                    <option value="medium" selected>Medium (Standard)</option>
                    <option value="high">High (Mendesak)</option>
                    <option value="critical">Critical (Operasional Macet)</option>
                    <option value="low">Low</option>
                </select>
            </label>

            <label>Foto Kerusakan Awal (Opsional)
                <input type="file" name="photo_before" accept="image/*" capture="environment">
            </label>

            <?php
            // Ambil checklist tindakan Job Desk jika ada
            $mobileActions = [];
            if (!empty($unit['job_desk_name'])) {
                $mActStmt = $pdo->prepare("SELECT id, action_name AS action_title FROM corrective_action_types WHERE job_desk_name = ? ORDER BY action_name");
                $mActStmt->execute([$unit['job_desk_name']]);
                $mobileActions = $mActStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            ?>
            <?php if (!empty($mobileActions)): ?>
                <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:12px; margin:12px 0;">
                    <span style="font-weight:700; font-size:13px; color:#0369a1; display:block; margin-bottom:8px;">
                        📋 Checklist Tindakan (<?= e($unit['job_desk_name']) ?>):
                    </span>
                    <?php foreach ($mobileActions as $mAct): ?>
                        <label style="display:flex; align-items:center; gap:8px; font-weight:normal; font-size:13px; margin-bottom:6px; cursor:pointer;">
                            <input type="checkbox" name="requested_actions[]" value="<?= (int)$mAct['id'] ?>">
                            <span><?= e($mAct['action_title']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <label>Jenis Tindakan Perbaikan (<?= e($unit['job_desk_name'] ?: 'Job Desk Corrective') ?>) *
                <select name="action_type" required>
                    <?= corrective_action_type_options($pdo, null, (int)($unit['asset_group_id'] ?? 0), (int)($unit['asset_type_id'] ?? 0), $unit['job_desk_name'] ?? null) ?>
                </select>
            </label>

            <label>Biaya Service / Jasa Perbaikan (Rp)
                <input type="number" name="repair_cost" value="0" placeholder="0">
            </label>

            <label>Tindakan & Solusi yang Dilakukan *
                <textarea name="solution_details" style="min-height:80px;" required placeholder="Jelaskan langkah perbaikan yang telah dilakukan di lokasi..."></textarea>
            </label>

            <!-- Penggantian Part -->
            <div style="background:#fff;;border-radius:8px;padding:12px;margin:12px 0;">
                <span style="font-weight:700;font-size:13px;color:#0284c7;">📦 Ada Penggantian Sparepart?</span>
                <label>Nama Part Baru
                    <input name="part_new_name" placeholder="Misal: SSD 512GB Kingston">
                </label>
                <label>Part Lama yang Digantikan
                    <input name="part_old_name" placeholder="Misal: HDD 500GB Seagate">
                </label>
                <label>Serial Number Part Baru
                    <input name="part_new_serial" placeholder="SN part baru">
                </label>
                <label>Harga Part (Rp)
                    <input type="number" name="part_cost" value="0">
                </label>
            </div>

            <label>Foto Bukti Selesai Perbaikan
                <input type="file" name="photo_after" accept="image/*" capture="environment">
            </label>

            <label>Status Akhir Tiket
                <select name="status">
                    <option value="resolved" selected>✓ Resolved (Perbaikan Selesai)</option>
                    <option value="in_progress">⏳ In Progress (Sedang Dikerjakan)</option>
                    <option value="pending_part">📦 Waiting Part (Menunggu Part)</option>
                </select>
            </label>

            <button class="btn btn-success" style="margin-top:16px;font-size:16px;padding:14px;">
                ✓ SIMPAN & SELESAIKAN PEKERJAAN
            </button>
            <a href="<?= route_url('mobile_service') ?>" class="btn btn-secondary" style="margin-top:8px;">Batal</a>
        </form>
    </div>
    <?php
    render_mobile_footer('scan');
}

/**
 * Modul Field Service: Daftar Tugas
 * Proses: Menampilkan daftar tugas maintenance/perbaikan yang ditugaskan kepada teknisi saat ini.
 */
function handle_route_mobile_service_tasks(PDO $pdo): void
{
    $user = require_corrective_technician();
    ensure_corrective_maintenance_schema($pdo);

    $sql = "SELECT t.*, 
            COALESCE(ai.asset_code, t.pc_id, t.prn_id, 'Unit') AS display_asset_code,
            COALESCE(ai.asset_name, ma.name, 'Unit') AS asset_name
            FROM corrective_tickets t
            LEFT JOIN asset_items ai ON ai.id = t.asset_item_id
            LEFT JOIN maintenance_assets ma ON ma.id = t.maintenance_asset_id
            WHERE t.status IN ('open', 'assigned', 'in_progress', 'pending_part')
            ORDER BY t.priority = 'critical' DESC, t.created_at DESC";
    $tickets = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    render_mobile_header('Daftar Tugas Tiket', $user);
    ?>
    <div class="panel">
        <h2>📋 Daftar Tiket Menunggu Tindakan (<?= count($tickets) ?>)</h2>
        <p style="font-size:13px;color:#64748b;margin:0 0 12px;">Pilih tiket untuk eksekusi atau verifikasi di lokasi.</p>

        <?php if (!$tickets): ?>
            <p style="font-size:14px;color:#10b981;text-align:center;padding:24px 0;">🎉 Luar biasa! Seluruh tiket kerusakan telah selesai ditangani.</p>
        <?php else: ?>
            <?php foreach ($tickets as $t): ?>
                <div style="background:#fff;;border-radius:10px;padding:14px;margin-bottom:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                        <div>
                            <span style="font-weight:700;color:#0284c7;font-size:15px;"><?= e($t['ticket_code']) ?></span>
                            <div style="font-size:13px;font-weight:600;;margin-top:2px;">
                                📍 <?= e($t['display_asset_code']) ?> - <?= e($t['asset_name'] ?: '-') ?>
                            </div>
                        </div>
                        <div>
                            <?= ticket_priority_badge($t['priority']) ?>
                        </div>
                    </div>

                    <p style="font-size:14px;margin:8px 0;color:#e2e8f0;font-weight:600;"><?= e($t['subject']) ?></p>
                    <p style="font-size:12px;color:#64748b;margin:4px 0;"><?= e(mb_strimwidth((string)$t['description'], 0, 80, '...')) ?></p>

                    <div style="font-size:11px;color:#64748b;margin-top:6px;">
                        Pelapor: <?= e($t['reporter_name']) ?> | Lokasi: <?= e($t['location_label'] ?: '-') ?>
                    </div>

                    <a href="<?= route_url('mobile_service_ticket', ['id' => $t['id']]) ?>" class="btn btn-primary" style="margin-top:10px;">
                        Mulai Kerjakan Tiket Ini →
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
    render_mobile_footer('tasks');
}

/**
 * Modul Field Service: Penanganan Tiket
 * Proses: Memungkinkan teknisi merespon, memperbarui, atau menutup tiket masalah langsung dari HP.
 */
function handle_route_mobile_service_ticket(PDO $pdo): void
{
    $user = require_corrective_technician();
    ensure_corrective_maintenance_schema($pdo);

    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT t.*, 
                                  COALESCE(ai.asset_code, t.pc_id, t.prn_id, 'Unit') AS display_asset_code,
                                  COALESCE(ai.asset_name, ma.name, 'Unit') AS asset_name, 
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
        redirect_to('mobile_service_tasks');
    }

    $unit = null;
    if (!empty($ticket['maintenance_asset_id'])) {
        $unit = get_maintenance_asset_unit($pdo, (int)$ticket['maintenance_asset_id']);
    }
    $ticketGroupId = (int)($unit['asset_group_id'] ?? ($ticket['ma_group_id'] ?: ($ticket['ai_group_id'] ?? 0)));
    $ticketTypeId = (int)($unit['asset_type_id'] ?? ($ticket['ma_type_id'] ?: ($ticket['ai_type_id'] ?? 0)));

    render_mobile_header('Kerjakan Tiket #' . $ticket['ticket_code'], $user);
    ?>
    <div class="panel" style="border-left:4px solid #38bdf8;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
                <span style="font-size:12px;color:#64748b;">Tiket ID</span>
                <h2 style="margin:0;color:#0284c7;font-size:18px;"><?= e($ticket['ticket_code']) ?></h2>
            </div>
            <div>
                <?= ticket_status_badge($ticket['status']) ?>
            </div>
        </div>

        <div style="margin-top:10px;font-size:13px;line-height:1.6;">
            <div>Unit Aset: <span style="color:#0284c7;font-weight:700;"><?= e($unit['item_asset_code'] ?: ($ticket['display_asset_code'] ?: '-')) ?></span> (<?= e($ticket['asset_name'] ?: '-') ?>)</div>
            <?php if (!empty($unit['group_name']) || !empty($unit['type_name'])): ?>
                <div>Kategori: <span style="color:#0284c7;font-weight:600;"><?= e($unit['group_name'] ?: 'IT') ?> &bull; <?= e($unit['type_name'] ?: ($unit['asset_category'] ?? 'Comp')) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($unit['job_desk_name'])): ?>
                <div style="margin-top:4px;"><span style="font-size:12px;color:#a5f3fc;background:#0369a1;padding:3px 8px;border-radius:6px;display:inline-block;">📋 Job Desk: <?= e($unit['job_desk_name']) ?></span></div>
            <?php endif; ?>
            <div>Lokasi: <?= e($ticket['location_label'] ?: '-') ?></div>
            <div>Pelapor: <?= e($ticket['reporter_name']) ?> <?= $ticket['reporter_contact'] ? '(' . e($ticket['reporter_contact']) . ')' : '' ?></div>
        </div>

        <div style="background:#fff;border-radius:6px;padding:10px;margin-top:10px;">
            <div style="font-weight:700;;font-size:13px;"><?= e($ticket['subject']) ?></div>
            <div style="font-size:12px;color:#64748b;margin-top:4px;"><?= nl2br(e($ticket['description'] ?: '-')) ?></div>
            <?php if ($ticket['photo_before']): ?>
                <div style="margin-top:8px;">
                    <a href="<?= e($ticket['photo_before']) ?>" target="_blank" style="color:#0284c7;font-size:12px;">📷 Lihat Foto Kerusakan Awal</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <h2>🛠️ Form Tindakan Perbaikan</h2>
        <form method="post" action="<?= route_url('ticket_action') ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
            <input type="hidden" name="technician_name" value="<?= e($user['name']) ?>">

            <label>Jenis Tindakan (<?= e($unit['job_desk_name'] ?? 'Job Desk Corrective') ?>) *
                <select name="action_type" required>
                    <?= corrective_action_type_options($pdo, null, $ticketGroupId, $ticketTypeId, $unit['job_desk_name'] ?? null) ?>
                </select>
            </label>

            <label>Diagnosa / Penyebab Kerusakan
                <textarea name="root_cause_analysis" style="min-height:60px;" placeholder="Penyebab kerusakan jika diketahui..."></textarea>
            </label>

            <label>Tindakan & Solusi Perbaikan *
                <textarea name="solution_details" style="min-height:80px;" required placeholder="Langkah perbaikan yang dilakukan secara detail..."></textarea>
            </label>

            <!-- Penggantian Part -->
            <div style="background:#fff;;border-radius:8px;padding:12px;margin:12px 0;">
                <span style="font-weight:700;font-size:13px;color:#0284c7;">📦 Pasang Sparepart Baru? (Opsional)</span>
                <label>Nama Part Baru
                    <input name="parts[0][new_name]" placeholder="Misal: SSD 512GB NVMe">
                </label>
                <label>Part Lama yang Dilepas
                    <input name="parts[0][old_name]" placeholder="Misal: SSD 256GB Rusak">
                </label>
                <label>Serial Number Part Baru
                    <input name="parts[0][new_serial]" placeholder="SN part baru">
                </label>
                <label>Harga Part (Rp)
                    <input type="number" name="parts[0][cost]" value="0">
                </label>
            </div>

            <label>Foto Bukti Selesai Perbaikan
                <input type="file" name="photo_after" accept="image/*" capture="environment">
            </label>

            <label>Update Status Tiket
                <select name="update_status">
                    <option value="resolved" selected>✓ Resolved (Perbaikan Selesai)</option>
                    <option value="in_progress">⏳ In Progress (Sedang Dikerjakan)</option>
                    <option value="pending_part">📦 Waiting Part (Menunggu Part)</option>
                    <option value="closed">✕ Closed (Tutup Tiket)</option>
                </select>
            </label>

            <button class="btn btn-success" style="margin-top:16px;font-size:16px;padding:14px;">
                ✓ SIMPAN TINDAKAN TEKNISI
            </button>
            <a href="<?= route_url('mobile_service_tasks') ?>" class="btn btn-secondary" style="margin-top:8px;">Kembali</a>
        </form>
    </div>
    <?php
    render_mobile_footer('tasks');
}


