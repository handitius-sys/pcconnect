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
                $_SESSION['user_role'] = $user['role'];
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
    <div class="panel" style="background:linear-gradient(135deg, #0369a1, #0f172a);border-color:#0284c7;color:#fff;">
        <h2 style="font-size:18px;color:#38bdf8;margin-bottom:6px;">⚡ Layanan Corrective Maintenance</h2>
        <p style="font-size:13px;color:#cbd5e1;margin:0 0 14px;">Buat tiket kendala / perbaikan baru atau scan stiker QR unit aset langsung di lokasi.</p>
        <div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:10px;">
            <a href="<?= route_url('mobile_service_direct') ?>" class="btn btn-primary" style="font-size:14px;padding:12px 8px;background:#22c55e;border-color:#16a34a;color:#fff;font-weight:700;text-align:center;text-decoration:none;border-radius:8px;">
                ➕ Buat Tiket Baru
            </a>
            <a href="<?= route_url('mobile_service_scan') ?>" class="btn btn-primary" style="font-size:14px;padding:12px 8px;background:#38bdf8;border-color:#0284c7;color:#0f172a;font-weight:700;text-align:center;text-decoration:none;border-radius:8px;">
                📷 Scan QR Unit
            </a>
        </div>
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
                <div style="background:#fff;border-radius:8px;padding:12px;margin-bottom:10px;">
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
 * Modul Field Service: Scan QR / Barcode & Input Unit Aset Lapangan
 *
 * [PROSES & FITUR]:
 * 1. Antarmuka kamera live scan berbasis html5-qrcode untuk pemindaian instan di lapangan.
 * 2. Pencarian interaktif PENCARIAN INTERAKTIF unit aset yang mendukung pencarian fleksibel:
 *    - Kode Baru (asset_code)
 *    - Kode Lama / Alias (alias_code / Legacy Code)
 *    - Nama Barang / Model
 *    - Serial Number (SN)
 *    - Nama Pengguna / Custodian & Lokasi Penempatan
 * 3. Logika Resolusi Otomatis:
 *    - Jika unit memiliki tiket perbaikan aktif (open/in_progress/pending_part), langsung diarahkan ke detail tiket terkait.
 *    - Jika belum ada tiket aktif, langsung diarahkan ke halaman pencatatan perbaikan langsung (Direct Service).
 */
function handle_route_mobile_service_scan(PDO $pdo): void
{
    $user = require_corrective_technician();

    $rawInput = trim((string)($_POST['code'] ?? $_GET['code'] ?? $_GET['eqr'] ?? ''));

    if ($rawInput !== '') {
        $maintId = resolve_maintenance_asset_id_from_raw_input($pdo, $rawInput);
        $assetItemId = 0;

        if (!$maintId || $maintId <= 0) {
            if (ctype_digit($rawInput)) {
                $stAi = $pdo->prepare("SELECT id FROM asset_items WHERE id = ? LIMIT 1");
                $stAi->execute([(int)$rawInput]);
                $assetItemId = (int)($stAi->fetchColumn() ?: 0);
            }
            if ($assetItemId <= 0) {
                $stAi = $pdo->prepare("SELECT id FROM asset_items WHERE (asset_code = ? OR alias_code = ?) AND status <> 'deleted' LIMIT 1");
                $stAi->execute([$rawInput, $rawInput]);
                $assetItemId = (int)($stAi->fetchColumn() ?: 0);
            }
        }

        if ((!$maintId || $maintId <= 0) && (!$assetItemId || $assetItemId <= 0)) {
            flash("Unit Aset dengan ID atau Kode '{$rawInput}' tidak ditemukan. Pastikan unit telah terdaftar.", 'err');
            redirect_to('mobile_service_scan');
        }

        // Cek apakah ada tiket open/in_progress pada unit ini
        $existingTicketId = 0;
        if ($maintId > 0) {
            $stmt2 = $pdo->prepare("SELECT id FROM corrective_tickets WHERE maintenance_asset_id = ? AND status IN ('open', 'assigned', 'in_progress', 'pending_part') ORDER BY created_at DESC LIMIT 1");
            $stmt2->execute([$maintId]);
            $existingTicketId = (int)($stmt2->fetchColumn() ?: 0);
        } elseif ($assetItemId > 0) {
            $stmt2 = $pdo->prepare("SELECT id FROM corrective_tickets WHERE asset_item_id = ? AND status IN ('open', 'assigned', 'in_progress', 'pending_part') ORDER BY created_at DESC LIMIT 1");
            $stmt2->execute([$assetItemId]);
            $existingTicketId = (int)($stmt2->fetchColumn() ?: 0);
        }

        if ($existingTicketId > 0) {
            flash("Unit terverifikasi! Membuka tiket aktif terkait...");
            redirect_to('mobile_service_ticket', ['id' => $existingTicketId, 'scanned' => 1]);
        } else {
            flash("Unit terverifikasi! Silakan catat perbaikan di bawah.");
            $params = [];
            if ($maintId > 0) $params['maintenance_asset_id'] = $maintId;
            elseif ($assetItemId > 0) $params['asset_item_id'] = $assetItemId;
            redirect_to('mobile_service_direct', $params);
        }
    }

    $PENCARIAN INTERAKTIFServiceAssets = [];
    try {
        $PENCARIAN INTERAKTIFServiceAssets = $pdo->query("SELECT ai.id, ai.asset_code, ai.alias_code, 
                                                     COALESCE(NULLIF(ai.asset_name, ''), ai.model, ai.asset_code) AS asset_name,
                                                     ai.serial_number, ai.custodian_name, 
                                                     COALESCE(loc.location_name, ai.location_label, '-') AS location_name,
                                                     COALESCE(t.type_name, ai.asset_type, '-') AS type_name
                                              FROM asset_items ai
                                              LEFT JOIN asset_locations loc ON loc.id = ai.location_id
                                              LEFT JOIN asset_types t ON t.id = ai.asset_type_id
                                              WHERE ai.status NOT IN ('inactive', 'deleted')
                                              ORDER BY ai.asset_code ASC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ignored) {}
    $cbServiceAssetsJson = json_encode($PENCARIAN INTERAKTIFServiceAssets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    render_mobile_header('Scan QR Unit Lapangan', $user);
    ?>
    <div class="panel" style="text-align:center;">
        <h2>📷 Scan QR Unit Aset</h2>
        <p style="font-size:13px;color:#64748b;margin:0 0 12px;">Arahkan kamera ke stiker QR Code pada unit fisik.</p>

        <div id="reader" style="width:100%;max-width:320px;margin:0 auto;border-radius:12px;overflow:hidden;background:#000;"></div>
        <p id="camStatus" style="font-size:12px;color:#0284c7;margin-top:8px;">Menyiapkan kamera live scan...</p>

        <form method="post" id="scanForm" style="margin-top:16px;text-align:left;">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <div style="position:relative;">
                <label style="font-size:13px;font-weight:700;color:#1e293b;margin-bottom:6px;display:block;">Atau Masukkan ID / Kode Unit Aset:
                    <input name="code" id="codeManual" placeholder="🔍 Ketik kode baru, kode lama (legacy), nama barang, PIC..." required autocomplete="off" style="width:100%;box-sizing:border-box;font-size:13.5px;font-weight:600;padding:10px 36px 10px 12px;border:2px solid #0284c7;border-radius:6px;background:#fff;" onfocus="showMobileAssetDropdown(this.value)" oninput="showMobileAssetDropdown(this.value)">
                </label>
                <div id="mobileAssetDropdown" style="display:none;position:absolute;left:0;right:0;top:calc(100% + 2px);max-height:280px;overflow-y:auto;background:#fff;border:1px solid #cbd5e1;border-radius:8px;box-shadow:0 12px 30px rgba(0,0,0,0.18);z-index:99999;text-align:left;"></div>
            </div>
            <button class="btn btn-primary" style="margin-top:10px;width:100%;padding:11px;font-size:14px;font-weight:700;">Lanjut Eksekusi →</button>
        </form>
    </div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
    (function() {
        var html5QrcodeScanner = null;
        var submitted = false;

        var availableServiceAssets = <?= $cbServiceAssetsJson ?>;

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        window.showMobileAssetDropdown = function(q) {
            var dd = document.getElementById('mobileAssetDropdown');
            if (!dd) return;
            q = (q || '').trim().toLowerCase();

            var matches = availableServiceAssets.filter(function(item) {
                if (!q) return true;
                var code = (item.asset_code || '').toLowerCase();
                var alias = (item.alias_code || '').toLowerCase();
                var name = (item.asset_name || '').toLowerCase();
                var sn = (item.serial_number || '').toLowerCase();
                var cust = (item.custodian_name || '').toLowerCase();
                var loc = (item.location_name || '').toLowerCase();
                var typ = (item.type_name || '').toLowerCase();
                return code.includes(q) || alias.includes(q) || name.includes(q) || sn.includes(q) || cust.includes(q) || loc.includes(q) || typ.includes(q);
            });

            if (matches.length === 0) {
                dd.innerHTML = '<div style="padding:12px;font-size:12px;color:#64748b;text-align:center;">Tidak ada unit aset cocok dengan kata kunci &ldquo;' + escapeHtml(q) + '&rdquo;</div>';
                dd.style.display = 'block';
                return;
            }

            dd.innerHTML = '';
            matches.slice(0, 25).forEach(function(item) {
                var div = document.createElement('div');
                div.style.cssText = 'padding:10px 12px;border-bottom:1px solid #f1f5f9;cursor:pointer;background:#fff;transition:background 0.15s ease;';

                var aliasPart = item.alias_code ? (' <span style="background:#fef3c7;color:#92400e;font-size:11px;font-weight:700;padding:2px 7px;border-radius:4px;border:1px solid #fde68a;">🏷️ Kode Lama: ' + escapeHtml(item.alias_code) + '</span>') : '';
                var subParts = [];
                if (item.type_name && item.type_name !== '-') subParts.push(item.type_name);
                if (item.serial_number && item.serial_number !== '-') subParts.push('SN: ' + item.serial_number);
                if (item.location_name && item.location_name !== '-') subParts.push('📍 ' + item.location_name);
                if (item.custodian_name) subParts.push('👤 ' + item.custodian_name);

                div.innerHTML = '<div style="font-size:13px;font-weight:700;color:#0f172a;"><span style="color:#0284c7;font-family:monospace;">' + escapeHtml(item.asset_code) + '</span>' + aliasPart + ' &bull; ' + escapeHtml(item.asset_name) + '</div>'
                    + (subParts.length > 0 ? ('<div style="font-size:11px;color:#64748b;margin-top:2px;">' + escapeHtml(subParts.join(' &bull; ')) + '</div>') : '');

                div.onmouseenter = function() { div.style.background = '#f0f9ff'; };
                div.onmouseleave = function() { div.style.background = '#fff'; };
                div.onmousedown = function() {
                    var inp = document.getElementById('codeManual');
                    if (inp) inp.value = item.asset_code;
                    dd.style.display = 'none';
                    document.getElementById('scanForm').submit();
                };
                dd.appendChild(div);
            });
            dd.style.display = 'block';
        };

        document.addEventListener('click', function(e) {
            var dd = document.getElementById('mobileAssetDropdown');
            var inp = document.getElementById('codeManual');
            if (dd && inp && !dd.contains(e.target) && e.target !== inp) {
                dd.style.display = 'none';
            }
        });

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
 * Modul Field Service: Pencatatan Perbaikan Langsung (Direct Service) & Tiket Baru Mobile
 *
 * [PROSES & FITUR]:
 * 1. Memungkinkan teknisi langsung mencatat perbaikan saat berada di hadapan unit fisik tanpa birokrasi dropdown bertingkat.
 * 2. Input unit aset via pencarian interaktif yang mendukung Kode Baru dan Kode Lama (Legacy Code).
 * 3. Menyertakan dokumentasi visual lengkap:
 *    - Foto Kondisi Sebelum Perbaikan
 *    - Foto Hasil Sesudah Perbaikan
 *    - Upload Kwitansi / Bukti Nota Biaya Sparepart
 * 4. Mendukung penugasan langsung atau eksekusi tuntas (Resolved) dalam satu alur kerja mobile yang ringkas.
 */
function handle_route_mobile_service_direct(PDO $pdo): void
{
    $user = require_corrective_technician();
    ensure_corrective_maintenance_schema($pdo);

    $rawInput = trim((string)($_POST['code'] ?? $_GET['code'] ?? $_GET['eqr'] ?? ''));
    $preMntId = (int)($_POST['maintenance_asset_id'] ?? $_GET['maintenance_asset_id'] ?? 0);
    $preAssetId = (int)($_POST['asset_item_id'] ?? $_GET['asset_item_id'] ?? 0);
    $prePcId = trim((string)($_GET['pc_id'] ?? ''));

    if ($prePcId !== '' && $preMntId <= 0) {
        $stmt = $pdo->prepare("SELECT maintenance_asset_id FROM pcs WHERE pc_id = ?");
        $stmt->execute([$prePcId]);
        $preMntId = (int)($stmt->fetchColumn() ?: 0);
    }

    // Resolusi dari rawInput jika ada
    if ($rawInput !== '') {
        if (ctype_digit($rawInput) && (int)$rawInput > 0) {
            $candId = (int)$rawInput;
            $stAi = $pdo->prepare("SELECT id FROM asset_items WHERE id = ? AND status <> 'deleted' LIMIT 1");
            $stAi->execute([$candId]);
            if ($stAi->fetchColumn()) {
                $preAssetId = $candId;
            } else {
                $stMa = $pdo->prepare("SELECT id FROM maintenance_assets WHERE id = ? LIMIT 1");
                $stMa->execute([$candId]);
                if ($stMa->fetchColumn()) {
                    $preMntId = $candId;
                }
            }
        }

        if (!$preMntId && !$preAssetId) {
            $resMaintId = resolve_maintenance_asset_id_from_raw_input($pdo, $rawInput);
            if ($resMaintId && $resMaintId > 0) {
                $preMntId = $resMaintId;
            } else {
                $stAi = $pdo->prepare("SELECT id FROM asset_items WHERE (asset_code = ? OR asset_code LIKE ? OR alias_code = ? OR alias_code LIKE ?) AND status <> 'deleted' LIMIT 1");
                $stAi->execute([$rawInput, $rawInput . '%', $rawInput, $rawInput . '%']);
                $aiId = (int)($stAi->fetchColumn() ?: 0);
                if ($aiId > 0) {
                    $preAssetId = $aiId;
                }
            }
        }

        if (!$preMntId && !$preAssetId) {
            flash("Unit Aset dengan ID atau Kode '{$rawInput}' tidak ditemukan. Pastikan ID atau kode unit benar.", 'err');
        }
    }

    $unit = null;
    if ($preMntId > 0) {
        $unit = get_maintenance_asset_unit($pdo, $preMntId);
        if ($unit && !empty($unit['asset_item_id'])) {
            $preAssetId = (int)$unit['asset_item_id'];
        }
    }
    if (!$unit && $preAssetId > 0) {
        $st = $pdo->prepare("SELECT ai.*, ai.id AS asset_item_id, ai.asset_code AS item_asset_code, 
                                    ai.asset_name AS name, ag.group_name, ag.group_code, 
                                    at.type_name, at.type_code, at.job_desk_name,
                                    ma.id AS maintenance_asset_id
                             FROM asset_items ai 
                             LEFT JOIN asset_groups ag ON ag.id = ai.asset_group_id 
                             LEFT JOIN asset_types at ON at.id = ai.asset_type_id 
                             LEFT JOIN maintenance_assets ma ON ma.asset_item_id = ai.id
                             WHERE ai.id = ?");
        $st->execute([$preAssetId]);
        $unit = $st->fetch(PDO::FETCH_ASSOC);
        if ($unit && !empty($unit['maintenance_asset_id'])) {
            $preMntId = (int)$unit['maintenance_asset_id'];
        }
    }

    // JIKA UNIT BELUM DITENTUKAN: Tampilkan antarmuka Scan QR atau Masukkan ID / Kode Unit
    if (!$unit) {
        $PENCARIAN INTERAKTIFServiceAssets = [];
    try {
        $PENCARIAN INTERAKTIFServiceAssets = $pdo->query("SELECT ai.id, ai.asset_code, ai.alias_code, 
                                                     COALESCE(NULLIF(ai.asset_name, ''), ai.model, ai.asset_code) AS asset_name,
                                                     ai.serial_number, ai.custodian_name, 
                                                     COALESCE(loc.location_name, ai.location_label, '-') AS location_name,
                                                     COALESCE(t.type_name, ai.asset_type, '-') AS type_name
                                              FROM asset_items ai
                                              LEFT JOIN asset_locations loc ON loc.id = ai.location_id
                                              LEFT JOIN asset_types t ON t.id = ai.asset_type_id
                                              WHERE ai.status NOT IN ('inactive', 'deleted')
                                              ORDER BY ai.asset_code ASC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ignored) {}
    $cbServiceAssetsJson = json_encode($PENCARIAN INTERAKTIFServiceAssets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        render_mobile_header('Pilih Unit Aset', $user);
        ?>
        <div class="panel" style="text-align:center;">
            <h2 style="font-size:16px;color:#0284c7;margin-top:0;">📷 Scan QR Code Unit Aset</h2>
            <p style="font-size:13px;color:#64748b;margin:0 0 12px;">Arahkan kamera ke stiker QR Code pada unit aset fisik.</p>

            <div id="reader" style="width:100%;max-width:320px;margin:0 auto;border-radius:12px;overflow:hidden;background:#000;"></div>
            <p id="camStatus" style="font-size:12px;color:#0284c7;margin-top:8px;">Menyiapkan kamera live scan...</p>
        </div>

        <div class="panel">
            <h2 style="font-size:16px;color:#0284c7;margin-top:0;">⌨️ Atau Masukkan ID / Kode Unit Aset</h2>
            <p style="font-size:12px;color:#64748b;margin:0 0 10px;">Ketik atau pilih ID angka, Kode Unit Baru, atau <strong>Kode Lama (Legacy)</strong>.</p>
            <form method="get" action="<?= route_url('mobile_service_direct') ?>" id="scanForm">
                <input type="hidden" name="route" value="mobile_service_direct">
                <div style="position:relative;">
                    <label style="font-size:13px;font-weight:700;color:#1e293b;margin-bottom:6px;display:block;">ID, Kode Baru, atau Kode Lama (Legacy):
                        <input name="code" id="codeManual" placeholder="🔍 Ketik kode baru, kode lama (legacy), nama barang, PIC..." required autofocus autocomplete="off" style="width:100%;box-sizing:border-box;font-size:14px;font-weight:600;padding:11px 36px 11px 12px;border:2px solid #0284c7;border-radius:6px;background:#fff;" onfocus="showMobileAssetDropdown(this.value)" oninput="showMobileAssetDropdown(this.value)">
                    </label>
                    <div id="mobileAssetDropdown" style="display:none;position:absolute;left:0;right:0;top:calc(100% + 2px);max-height:280px;overflow-y:auto;background:#fff;border:1px solid #cbd5e1;border-radius:8px;box-shadow:0 12px 30px rgba(0,0,0,0.18);z-index:99999;text-align:left;"></div>
                </div>
                <button class="btn btn-primary" style="margin-top:12px;font-size:15px;padding:12px;width:100%;font-weight:700;background:#0284c7;border-color:#0369a1;color:#fff;">
                    🔍 Verifikasi Unit & Lanjutkan →
                </button>
            </form>
        </div>

        <script src="https://unpkg.com/html5-qrcode"></script>
        <script>
        (function() {
            var submitted = false;
            var availableServiceAssets = <?= $cbServiceAssetsJson ?>;

            function escapeHtml(str) {
                if (!str) return '';
                return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }

            window.showMobileAssetDropdown = function(q) {
                var dd = document.getElementById('mobileAssetDropdown');
                if (!dd) return;
                q = (q || '').trim().toLowerCase();

                var matches = availableServiceAssets.filter(function(item) {
                    if (!q) return true;
                    var code = (item.asset_code || '').toLowerCase();
                    var alias = (item.alias_code || '').toLowerCase();
                    var name = (item.asset_name || '').toLowerCase();
                    var sn = (item.serial_number || '').toLowerCase();
                    var cust = (item.custodian_name || '').toLowerCase();
                    var loc = (item.location_name || '').toLowerCase();
                    var typ = (item.type_name || '').toLowerCase();
                    return code.includes(q) || alias.includes(q) || name.includes(q) || sn.includes(q) || cust.includes(q) || loc.includes(q) || typ.includes(q);
                });

                if (matches.length === 0) {
                    dd.innerHTML = '<div style="padding:12px;font-size:12px;color:#64748b;text-align:center;">Tidak ada unit aset cocok dengan kata kunci &ldquo;' + escapeHtml(q) + '&rdquo;</div>';
                    dd.style.display = 'block';
                    return;
                }

                dd.innerHTML = '';
                matches.slice(0, 25).forEach(function(item) {
                    var div = document.createElement('div');
                    div.style.cssText = 'padding:10px 12px;border-bottom:1px solid #f1f5f9;cursor:pointer;background:#fff;transition:background 0.15s ease;';

                    var aliasPart = item.alias_code ? (' <span style="background:#fef3c7;color:#92400e;font-size:11px;font-weight:700;padding:2px 7px;border-radius:4px;border:1px solid #fde68a;">🏷️ Kode Lama: ' + escapeHtml(item.alias_code) + '</span>') : '';
                    var subParts = [];
                    if (item.type_name && item.type_name !== '-') subParts.push(item.type_name);
                    if (item.serial_number && item.serial_number !== '-') subParts.push('SN: ' + item.serial_number);
                    if (item.location_name && item.location_name !== '-') subParts.push('📍 ' + item.location_name);
                    if (item.custodian_name) subParts.push('👤 ' + item.custodian_name);

                    div.innerHTML = '<div style="font-size:13px;font-weight:700;color:#0f172a;"><span style="color:#0284c7;font-family:monospace;">' + escapeHtml(item.asset_code) + '</span>' + aliasPart + ' &bull; ' + escapeHtml(item.asset_name) + '</div>'
                        + (subParts.length > 0 ? ('<div style="font-size:11px;color:#64748b;margin-top:2px;">' + escapeHtml(subParts.join(' &bull; ')) + '</div>') : '');

                    div.onmouseenter = function() { div.style.background = '#f0f9ff'; };
                    div.onmouseleave = function() { div.style.background = '#fff'; };
                    div.onmousedown = function() {
                        var inp = document.getElementById('codeManual');
                        if (inp) inp.value = item.asset_code;
                        dd.style.display = 'none';
                        document.getElementById('scanForm').submit();
                    };
                    dd.appendChild(div);
                });
                dd.style.display = 'block';
            };

            document.addEventListener('click', function(e) {
                var dd = document.getElementById('mobileAssetDropdown');
                var inp = document.getElementById('codeManual');
                if (dd && inp && !dd.contains(e.target) && e.target !== inp) {
                    dd.style.display = 'none';
                }
            });

            function onScanSuccess(decodedText) {
                if (submitted) return;
                submitted = true;
                document.getElementById('codeManual').value = decodedText;
                document.getElementById('camStatus').textContent = "QR Terbaca: " + decodedText + ". Memverifikasi unit...";
                document.getElementById('scanForm').submit();
            }

            if (window.Html5Qrcode) {
                var html5Qr = new Html5Qrcode("reader");
                html5Qr.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: { width: 220, height: 220 } },
                    onScanSuccess
                ).catch(function(err) {
                    document.getElementById('camStatus').textContent = "Kamera live scan tidak dapat diakses langsung. Silakan ketik ID/Kode unit di bawah.";
                });
            }
        })();
        </script>
        <?php
        render_mobile_footer('scan');
        return;
    }

    // JIKA UNIT SUDAH DITEMUKAN / DIVERIFIKASI:
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $code = generate_next_ticket_code($pdo);
        $assetItemId = (int)($_POST['asset_item_id'] ?? 0) ?: ($preAssetId ?: null);
        $maintAssetId = (int)($_POST['maintenance_asset_id'] ?? 0) ?: ($preMntId ?: null);
        $reporterName = trim((string)($_POST['reporter_name'] ?? ''));
        $reporterNik = trim((string)($_POST['reporter_nik'] ?? ''));
        $reporterContact = trim((string)($_POST['reporter_contact'] ?? ''));
        $issueCat = trim((string)($_POST['issue_category'] ?? 'hardware'));
        $priority = trim((string)($_POST['priority'] ?? 'medium'));
        $subject = trim((string)($_POST['subject'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $odometer = (int)($_POST['vehicle_odometer_km'] ?? 0) ?: null;
        $status = trim((string)($_POST['status'] ?? 'open'));
        if (!in_array($status, ['open', 'in_progress', 'resolved'], true)) {
            $status = 'open';
        }

        if ($subject === '') {
            $unitCode = (string)($unit['item_asset_code'] ?: ($unit['asset_code'] ?? 'Unit'));
            $unitName = (string)($unit['name'] ?: ($unit['asset_name'] ?? ''));
            $jobDesk = (string)($unit['job_desk_name'] ?? '');
            $subject = 'Perbaikan ' . ($jobDesk !== '' ? "[$jobDesk] " : '') . $unitCode . ($unitName !== '' ? ' - ' . $unitName : '');
            if (mb_strlen($subject) > 190) {
                $subject = mb_substr($subject, 0, 187) . '...';
            }
        }

        if ($desc === '') {
            $solDetails = trim((string)($_POST['solution_details'] ?? ''));
            $desc = $solDetails !== '' ? $solDetails : 'Perbaikan unit via Mobile Corrective Maintenance.';
        }

        if ($reporterName === '') {
            $reporterName = trim((string)($unit['custodian_name'] ?? '')) ?: ($user['name'] ?? 'Teknisi');
        }

        if ($reporterName === '') {
            flash('Nama pelapor wajib diisi.', 'err');
            $redirectParams = [];
            if ($maintAssetId) $redirectParams['maintenance_asset_id'] = $maintAssetId;
            elseif ($assetItemId) $redirectParams['asset_item_id'] = $assetItemId;
            redirect_to('mobile_service_direct', $redirectParams);
        }

        $assetCat = $unit['asset_category'] ?? 'IT';
        $pcId = $unit['pc_id'] ?? null;
        $companyId = (int)($unit['company_id'] ?? 0) ?: null;
        $location = trim((string)($_POST['location_label'] ?? ($unit['location_label'] ?? '')));

        // 1. Upload Foto Sebelum Perbaikan
        $photoBefore = null;
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

        // 2. Upload Foto Sesudah Perbaikan
        $photoAfter = null;
        if (!empty($_FILES['photo_after']['tmp_name']) && is_uploaded_file($_FILES['photo_after']['tmp_name'])) {
            $ext = strtolower(pathinfo((string)$_FILES['photo_after']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $filename = 'after_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['photo_after']['tmp_name'], $uploadDir . '/' . $filename)) {
                    $photoAfter = 'uploads/tickets/' . $filename;
                }
            }
        }

        // 3. Upload Foto / Scan Kwitansi atau Invoice
        $invoicePhoto = save_corrective_invoice_photo($_FILES['invoice_photo'] ?? null, $_POST['invoice_photo_base64'] ?? null);

        $handledByType = trim((string)($_POST['handled_by_type'] ?? 'internal'));
        $handledByUserId = (int)($_POST['handled_by_user_id'] ?? 0) ?: null;
        $handledByWorkshopId = (int)($_POST['handled_by_workshop_id'] ?? 0) ?: null;
        if ($handledByType !== 'workshop') { $handledByWorkshopId = null; }
        if ($handledByType !== 'internal') { $handledByUserId = null; }

        $assignedTech = null;
        if ($handledByUserId) {
            $techStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $techStmt->execute([$handledByUserId]);
            $assignedTech = $techStmt->fetchColumn() ?: null;
        }
        if (!$assignedTech && $handledByType === 'internal' && $user['role'] === 'corrective_maintenance') {
            $assignedTech = $user['name'];
            $handledByUserId = (int)$user['id'];
        }

        $resolvedAt = ($status === 'resolved') ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare("INSERT INTO corrective_tickets (
            ticket_code, asset_category, asset_item_id, maintenance_asset_id, pc_id, company_id, 
            location_label, vehicle_odometer_km, reporter_name, reporter_nik, reporter_contact, 
            issue_category, priority, subject, description, photo_before, photo_after, invoice_photo, status, 
            assigned_technician_name, resolved_at, handled_by_type, handled_by_user_id, handled_by_workshop_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $code, $assetCat, $assetItemId, $maintAssetId, $pcId, $companyId,
            $location, $odometer, $reporterName, $reporterNik, $reporterContact,
            $issueCat, $priority, $subject, $desc, $photoBefore, $photoAfter, $invoicePhoto, $status,
            $assignedTech, $resolvedAt, $handledByType, $handledByUserId, $handledByWorkshopId
        ]);
        $ticketId = (int)$pdo->lastInsertId();

        // Simpan checklist Job Desk CM
        if (!empty($_POST['requested_actions']) && is_array($_POST['requested_actions'])) {
            $actStmt = $pdo->prepare("INSERT INTO corrective_ticket_actions (ticket_id, job_action_id, action_title) VALUES (?, ?, ?)");
            foreach ($_POST['requested_actions'] as $actId) {
                $actId = (int)$actId;
                if ($actId <= 0) continue;
                $aRow = $pdo->query("SELECT action_name AS action_title FROM corrective_action_types WHERE id = " . $actId)->fetch();
                if ($aRow) {
                    $actStmt->execute([$ticketId, $actId, $aRow['action_title']]);
                }
            }
        }

        // Simpan Biaya Service, Catatan Sparepart & Catatan Kerja
        $repairCost = (float)($_POST['repair_cost'] ?? 0);
        $solutionDetails = trim((string)($_POST['solution_details'] ?? ''));
        $partNewName = trim((string)($_POST['part_new_name'] ?? ''));
        $partNewSerial = trim((string)($_POST['part_new_serial'] ?? ''));
        $partCost = (float)($_POST['part_cost'] ?? 0);

        if ($repairCost > 0 || $solutionDetails !== '' || $partNewName !== '' || $invoicePhoto !== null || $photoAfter !== null || $status === 'resolved') {
            $actionType = trim((string)($_POST['action_type'] ?? 'corrective'));
            $repStmt = $pdo->prepare("INSERT INTO corrective_repairs (
                ticket_id, technician_name, action_type, solution_details, repair_cost, invoice_photo, photo_after
            ) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $repStmt->execute([
                $ticketId, 
                ($assignedTech ?: $user['name']), 
                $actionType ?: 'corrective', 
                ($solutionDetails ?: 'Pencatatan perbaikan saat tiket dibuat via Field Service Mobile.'), 
                $repairCost, 
                $invoicePhoto, 
                $photoAfter
            ]);
            $repairId = (int)$pdo->lastInsertId();

            if ($partNewName !== '') {
                $pdo->prepare("INSERT INTO corrective_repair_parts (repair_id, new_part_name, new_part_serial, part_cost) VALUES (?, ?, ?, ?)")
                    ->execute([$repairId, $partNewName, $partNewSerial ?: null, $partCost]);
            }
        }

        flash("Tiket baru #{$code} berhasil dibuat.");
        redirect_to('mobile_service_ticket', ['id' => $ticketId]);
    }

    // Ambil data tindakan Job Desk terkait unit ini
    $jobDeskName = (string)($unit['job_desk_name'] ?? '');
    $unitActions = [];
    if ($jobDeskName !== '') {
        $stAct = $pdo->prepare("SELECT id, action_name AS action_title FROM corrective_action_types WHERE job_desk_name = ? ORDER BY action_name");
        $stAct->execute([$jobDeskName]);
        $unitActions = $stAct->fetchAll(PDO::FETCH_ASSOC);
    }
    if (empty($unitActions)) {
        $unitActions = $pdo->query("SELECT id, action_name AS action_title FROM corrective_action_types ORDER BY action_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    $mechanics = $pdo->query("SELECT id, name FROM users WHERE role='corrective_maintenance' AND is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $workshops = $pdo->query("SELECT id, workshop_name FROM corrective_workshops ORDER BY workshop_name")->fetchAll(PDO::FETCH_ASSOC);

    $groupCode = strtoupper((string)($unit['group_code'] ?? ''));
    $isVehicle = ($groupCode === 'VH' || stripos((string)($unit['group_name'] ?? ''), 'VEHICLE') !== false);

    render_mobile_header('Buat Tiket Perbaikan', $user);
    ?>

    <!-- 1. Identitas Unit Aset Terverifikasi -->
    <div class="panel" style="border-left:4px solid #0284c7; background:#f0f9ff;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
            <span class="badge" style="background:#0284c7;color:#fff;font-weight:700;font-size:11px;">✓ UNIT TERVERIFIKASI</span>
            <a href="<?= route_url('mobile_service_direct') ?>" class="btn" style="font-size:11px;padding:4px 8px;background:#fff;border:1px solid #cbd5e1;color:#0284c7;text-decoration:none;border-radius:6px;font-weight:600;">
                🔄 Ganti Unit
            </a>
        </div>
        
        <div style="font-size:18px;font-weight:800;color:#0369a1;">
            <?= e($unit['item_asset_code'] ?: ($unit['asset_code'] ?? 'Unit Aset')) ?>
        </div>
        <div style="font-size:14px;font-weight:600;color:#1e293b;margin-top:2px;">
            <?= e($unit['name'] ?: ($unit['asset_name'] ?? '-')) ?>
        </div>

        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;">
            <?php if (!empty($unit['group_name']) || !empty($unit['type_name'])): ?>
                <span class="badge" style="background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;font-size:12px;font-weight:600;">
                    🏷️ <?= e($unit['group_name'] ?: 'IT') ?> &bull; <?= e($unit['type_name'] ?: ($unit['asset_category'] ?? 'Comp')) ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($unit['job_desk_name'])): ?>
                <span class="badge" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;font-size:12px;font-weight:600;">
                    📋 Job Desk CM: <?= e($unit['job_desk_name']) ?>
                </span>
            <?php endif; ?>
        </div>

        <div style="font-size:12px;color:#64748b;margin-top:8px;border-top:1px dashed #cbd5e1;padding-top:6px;">
            👤 PIC: <strong><?= e($unit['custodian_name'] ?: '-') ?></strong> | 📍 Lokasi: <strong><?= e($unit['location_label'] ?: '-') ?></strong>
        </div>

        <?php if ($isVehicle): ?>
            <div style="margin-top:10px;padding-top:8px;border-top:1px solid #cbd5e1;">
                <label style="font-size:13px;font-weight:700;color:#0369a1;margin-top:0;">Kilometer Kendaraan (KM)
                    <input type="number" name="vehicle_odometer_km" placeholder="Contoh: 45200">
                </label>
            </div>
        <?php endif; ?>
    </div>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="maintenance_asset_id" value="<?= (int)($unit['maintenance_asset_id'] ?? $preMntId) ?>">
        <input type="hidden" name="asset_item_id" value="<?= (int)($unit['asset_item_id'] ?? $preAssetId) ?>">

        <input type="hidden" name="location_label" value="<?= e($unit['location_label'] ?? '') ?>">

        <!-- Foto Sebelum & Sesudah Perbaikan -->
        <div class="panel">
            <h2 style="font-size:16px;color:#0369a1;margin-top:0;">📸 Foto Sebelum & Sesudah Perbaikan</h2>
            <p class="muted" style="font-size:12px;margin:0 0 12px;">Ambil foto kerusakan awal dan foto hasil setelah perbaikan selesai langsung dengan kamera HP.</p>

            <div style="background:#fff7ed; border:1px solid #fed7aa; padding:12px; border-radius:8px; margin-bottom:12px;">
                <label style="font-weight:700; color:#c2410c; font-size:13px; margin:0 0 6px; display:block;">
                    📷 Foto Sebelum Perbaikan (Kondisi Kerusakan)
                </label>
                <input type="file" name="photo_before" accept="image/*" capture="environment" onchange="previewImageFile(this, 'previewBeforeImg', 'previewBeforeWrap')">
                <div id="previewBeforeWrap" style="display:none; margin-top:8px; text-align:center;">
                    <img id="previewBeforeImg" src="" alt="Preview Foto Sebelum" style="max-height:140px; max-width:100%; border-radius:6px; border:1px solid #cbd5e1; object-fit:contain;">
                    <div style="font-size:11px; color:#c2410c; margin-top:3px;">Foto Sebelum Terpilih</div>
                </div>
            </div>

            <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:12px; border-radius:8px;">
                <label style="font-weight:700; color:#15803d; font-size:13px; margin:0 0 6px; display:block;">
                    📷 Foto Sesudah Perbaikan (Bukti Hasil Selesai)
                </label>
                <input type="file" name="photo_after" accept="image/*" capture="environment" onchange="previewImageFile(this, 'previewAfterImg', 'previewAfterWrap')">
                <div id="previewAfterWrap" style="display:none; margin-top:8px; text-align:center;">
                    <img id="previewAfterImg" src="" alt="Preview Foto Sesudah" style="max-height:140px; max-width:100%; border-radius:6px; border:1px solid #cbd5e1; object-fit:contain;">
                    <div style="font-size:11px; color:#15803d; margin-top:3px;">Foto Sesudah Terpilih</div>
                </div>
            </div>
        </div>

        <!-- Penanganan Oleh -->
        <div class="panel">
            <h2 style="font-size:16px;color:#0369a1;margin-top:0;">👥 Penanganan Oleh</h2>
            
            <div style="background:#f8fafc; border:1px solid #cbd5e1; padding:12px; border-radius:8px;">
                <div style="display:flex; gap:16px; margin-bottom:10px;">
                    <label style="font-weight:normal; display:flex; align-items:center; gap:6px; cursor:pointer; margin:0;">
                        <input type="radio" name="handled_by_type" value="internal" checked onclick="toggleHandledBy()">
                        <strong>Internal (Mekanik)</strong>
                    </label>
                    <label style="font-weight:normal; display:flex; align-items:center; gap:6px; cursor:pointer; margin:0;">
                        <input type="radio" name="handled_by_type" value="workshop" onclick="toggleHandledBy()">
                        <strong>Bengkel / Workshop</strong>
                    </label>
                </div>
                
                <div id="handledInternalWrap">
                    <label style="margin-top:0;">Pilih Mekanik Internal
                        <select name="handled_by_user_id">
                            <option value="">-- Belum Ditentukan --</option>
                            <?php foreach ($mechanics as $mec): ?>
                                <option value="<?= $mec['id'] ?>" <?= ((int)$user['id'] === (int)$mec['id']) ? 'selected' : '' ?>><?= e($mec['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div id="handledWorkshopWrap" style="display:none;">
                    <label style="margin-top:0;">Pilih Bengkel / Workshop
                        <select name="handled_by_workshop_id">
                            <option value="">-- Pilih Bengkel --</option>
                            <?php foreach ($workshops as $wks): ?>
                                <option value="<?= $wks['id'] ?>"><?= e($wks['workshop_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </div>
        </div>

        <!-- 5. Tindakan Job Desk CM -->
        <div class="panel">
            <h2 style="font-size:16px;color:#0369a1;margin-top:0;">📋 Tindakan Job Desk CM</h2>
            
            <div id="actionListWrapper" style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #cbd5e1;">
                <h3 style="margin-top:0;font-size:13px;color:#0369a1;">Pilihan Tindakan: <span style="color:#0ea5e9;"><?= e($jobDeskName ?: 'Job Desk CM Kategori Ini') ?></span></h3>
                <div id="dynamicActionRows">
                    <div class="action-row" style="display:flex; gap:8px; margin-bottom:8px; align-items:center;">
                        <select name="requested_actions[]" style="flex:1;">
                            <option value="">-- Pilih Tindakan --</option>
                            <?php foreach ($unitActions as $a): ?>
                                <option value="<?= (int)$a['id'] ?>"><?= e($a['action_title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn danger" onclick="if(document.querySelectorAll('#dynamicActionRows .action-row').length > 1) { this.parentElement.remove(); } else { this.previousElementSibling.value = ''; }" style="padding:6px 10px;font-size:12px;" title="Hapus">✕</button>
                    </div>
                </div>
                <button type="button" class="btn" onclick="addDynamicAction()" style="font-size:13px; padding:6px 12px; margin-top:4px; background:#e0f2fe; border-color:#bae6fd; color:#0369a1; font-weight:600;">+ Tambah Tindakan Lain</button>
            </div>
        </div>

        <!-- 6. Biaya Service, Catatan Sparepart & Kwitansi / Invoice -->
        <div class="panel">
            <h2 style="font-size:16px;color:#0369a1;margin-top:0;">💰 Biaya Service, Sparepart & Kwitansi</h2>
            
            <div class="grid two" style="margin-bottom:10px;">
                <label>Biaya Jasa (Rp)
                    <input type="number" name="repair_cost" value="0" placeholder="0">
                </label>
                <label>Biaya Sparepart (Rp)
                    <input type="number" name="part_cost" value="0" placeholder="0">
                </label>
            </div>

            <div class="grid two" style="margin-bottom:10px;">
                <label>Nama Sparepart Baru
                    <input name="part_new_name" placeholder="Misal: RAM 8GB / Ban / Aki">
                </label>
                <label>Serial / Spek Part Baru
                    <input name="part_new_serial" placeholder="Misal: SN12345 / 195/65 R15">
                </label>
            </div>

            <label>Catatan Kerja / Solusi Teknis
                <textarea name="solution_details" style="min-height:70px;" placeholder="Langkah perbaikan yang dilakukan atau catatan khusus..."></textarea>
            </label>

            <!-- Foto / Scan Kwitansi / Invoice -->
            <div style="margin-top:12px; padding:12px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px;">
                <label style="font-weight:700; display:block; margin:0 0 4px; color:#1e293b; font-size:13px;">
                    🧾 Foto / Scan Kwitansi / Invoice (Opsional)
                </label>
                <p class="muted" style="font-size:12px; margin:0 0 10px;">
                    Unggah file kwitansi / invoice (JPG, PNG, PDF) atau gunakan live kamera smartphone.
                </p>

                <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                    <label class="btn" style="cursor:pointer; display:inline-flex; align-items:center; gap:6px; padding:8px 12px; font-size:13px; background:#fff; border:1px solid #cbd5e1; border-radius:6px; font-weight:500;">
                        📁 File Image / PDF
                        <input type="file" id="invoiceFileInput" name="invoice_photo" accept="image/*,.pdf,application/pdf" style="display:none;" onchange="handleInvoiceFileSelected(this, '')">
                    </label>

                    <button type="button" class="btn primary" onclick="openInvoiceCameraModal('')" style="display:inline-flex; align-items:center; gap:6px; padding:8px 14px; font-size:13px; border-radius:6px;">
                        📷 Live Kamera
                    </button>
                </div>

                <input type="hidden" id="invoicePhotoBase64" name="invoice_photo_base64" value="">

                <div id="invoicePreviewContainer" style="display:none; margin-top:10px; align-items:center; gap:10px; background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:8px;">
                    <img id="invoicePreviewImg" src="" alt="Preview Kwitansi" style="max-height:75px; max-width:100px; border-radius:4px; object-fit:cover; border:1px solid #cbd5e1; display:none;">
                    <div id="invoicePdfBadge" style="display:none; align-items:center; gap:6px; background:#fee2e2; color:#b91c1c; padding:6px 10px; border-radius:4px; font-weight:600; font-size:12px;">
                        📄 <span id="invoicePdfName">Dokumen PDF</span>
                    </div>
                    <div style="flex:1;">
                        <strong id="invoiceFileInfo" style="font-size:12px; color:#334155; display:block;">File terpilih</strong>
                        <span id="invoiceFileSub" class="muted" style="font-size:11px;">Siap disimpan</span>
                    </div>
                    <button type="button" class="btn danger" onclick="clearInvoicePhoto('')" style="padding:4px 8px; font-size:11px;">✕ Hapus</button>
                </div>
            </div>
        </div>

        <!-- 7. Informasi Pelapor -->
        <div class="panel">
            <h2 style="font-size:16px;color:#0369a1;margin-top:0;">👤 Informasi Pelapor</h2>
            
            <label>Nama Pelapor *
                <input name="reporter_name" id="reporterName" value="<?= e($unit['custodian_name'] ?? $user['name'] ?? '') ?>" required placeholder="Nama pemakai / PIC">
            </label>
            
            <div class="grid two">
                <label>NIK Pelapor
                    <input name="reporter_nik" id="reporterNik" value="<?= e($unit['custodian_nik'] ?? '') ?>" placeholder="NIK karyawan">
                </label>
                <label>No. HP / WhatsApp
                    <input name="reporter_contact" placeholder="0812xxxxxx">
                </label>
            </div>
            <?= function_exists('employee_portal_name_picker_html') ? employee_portal_name_picker_html('ticketReporter', 'reporterName', 'reporterNik') : '' ?>
        </div>

        <!-- 8. Status Tiket & Hasil Eksekusi Lapangan -->
        <div class="panel">
            <h2 style="font-size:16px;color:#0369a1;margin-top:0;">⚙️ Status Tiket & Eksekusi</h2>
            
            <label>Status Tiket
                <select name="status">
                    <option value="open">Tiket Baru (Open - Masuk Antrean)</option>
                    <option value="in_progress">⏳ In Progress (Sedang Dikerjakan Langsung)</option>
                    <option value="resolved">✓ Resolved (Perbaikan Selesai di Lokasi)</option>
                </select>
            </label>

            <button class="btn btn-primary" style="margin-top:16px;font-size:16px;padding:14px;background:#16a34a;border-color:#15803d;color:#fff;font-weight:700;width:100%;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                ✓ SIMPAN & TERBITKAN TIKET
            </button>
            <a href="<?= route_url('mobile_service') ?>" class="btn" style="display:block;text-align:center;margin-top:10px;text-decoration:none;border-color:#cbd5e1;color:#64748b;">
                Batal
            </a>
        </div>
    </form>

    <script>
    function toggleHandledBy() {
        var rad = document.querySelector('input[name="handled_by_type"]:checked');
        var type = rad ? rad.value : 'internal';
        if (type === 'internal') {
            document.getElementById('handledInternalWrap').style.display = 'block';
            document.getElementById('handledWorkshopWrap').style.display = 'none';
        } else {
            document.getElementById('handledInternalWrap').style.display = 'none';
            document.getElementById('handledWorkshopWrap').style.display = 'block';
        }
    }
    toggleHandledBy();

    function previewImageFile(input, imgId, wrapId) {
        var wrap = document.getElementById(wrapId);
        var img = document.getElementById(imgId);
        if (!input.files || !input.files[0]) {
            if (wrap) wrap.style.display = 'none';
            return;
        }
        var reader = new FileReader();
        reader.onload = function(e) {
            if (img) img.src = e.target.result;
            if (wrap) wrap.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }

    window.currentActionOptions = document.querySelector('#dynamicActionRows select') ? document.querySelector('#dynamicActionRows select').innerHTML : '';

    function addDynamicAction() {
        var container = document.getElementById('dynamicActionRows');
        if (!container) return;
        
        var div = document.createElement('div');
        div.className = 'action-row';
        div.style.display = 'flex';
        div.style.gap = '8px';
        div.style.marginBottom = '8px';
        div.style.alignItems = 'center';
        
        var sel = document.createElement('select');
        sel.name = 'requested_actions[]';
        sel.style.flex = '1';
        sel.innerHTML = window.currentActionOptions;
        
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn danger';
        btn.style.padding = '6px 10px';
        btn.style.fontSize = '12px';
        btn.title = 'Hapus';
        btn.textContent = '✕';
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
    render_mobile_footer('ticket');
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
                <div style="background:#fff;border-radius:10px;padding:14px;margin-bottom:12px;">
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
                    <a href="<?= e($ticket['photo_before']) ?>" target="_blank" style="color:#0284c7;font-size:12px;">📷 Lihat Foto Kerusakan Awal (Sebelum)</a>
                </div>
            <?php endif; ?>
            <?php if (!empty($ticket['photo_after'])): ?>
                <div style="margin-top:6px;">
                    <a href="<?= e($ticket['photo_after']) ?>" target="_blank" style="color:#16a34a;font-size:12px;">📷 Lihat Foto Hasil Perbaikan (Sesudah)</a>
                </div>
            <?php endif; ?>
            <?php if (!empty($ticket['invoice_photo'])): ?>
                <div style="margin-top:6px;">
                    <a href="<?= e($ticket['invoice_photo']) ?>" target="_blank" style="color:#d97706;font-size:12px;">🧾 Lihat Foto Kwitansi / Nota</a>
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
            <div style="background:#fff;border-radius:8px;padding:12px;margin:12px 0;">
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


