<?php

declare(strict_types=1);

/**
 * Modul Mobile Peminjaman Aset (Mobile Asset Loans)
 * Versi mobile responsif untuk teknisi/petugas serah-terima alat kerja & unit aset.
 * Mendukung Live Camera QR Code Scanner, Pencarian Master Pengguna, & Pengembalian Cepat.
 */

function require_mobile_loan_user(): array
{
    if (!isset($_SESSION['user_id'])) {
        flash('Silakan login terlebih dahulu untuk mengakses Peminjaman Aset Mobile.', 'err');
        redirect_to('login', ['mode' => 'mobile']);
    }
    return require_role(['admin', 'maintenance_admin', 'technician', 'corrective_maintenance', 'loan_officer']);
}

function save_mobile_loan_photo(array $fileEntry, string $prefix = 'loan'): ?string
{
    if (empty($fileEntry['tmp_name']) || !is_uploaded_file($fileEntry['tmp_name'])) {
        return null;
    }
    if (($fileEntry['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return null;
    }
    $uploadDir = __DIR__ . '/../../public/uploads/loans';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }
    $ext = strtolower(pathinfo((string)($fileEntry['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $ext = 'jpg';
    }
    $filename = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $uploadDir . '/' . $filename;
    if (move_uploaded_file($fileEntry['tmp_name'], $targetPath)) {
        if (function_exists('compress_image_to_limit')) {
            compress_image_to_limit($targetPath);
        }
        return 'uploads/loans/' . $filename;
    }
    return null;
}

/**
 * 1. Dashboard Peminjaman Mobile
 */
/**
 * Modul Mobile Loan: Daftar Peminjaman
 * Proses: Menampilkan daftar transaksi peminjaman di antarmuka mobile / smartphone.
 */
function handle_route_mobile_asset_loans(PDO $pdo): void
{
    $user = require_mobile_loan_user();
    $tab = (string)($_GET['tab'] ?? 'active');
    $q = trim((string)($_GET['q'] ?? ''));

    // Statistik
    $stats = [
        'active' => (int)$pdo->query("SELECT COUNT(*) FROM asset_loans WHERE status = 'active'")->fetchColumn(),
        'overdue' => (int)$pdo->query("SELECT COUNT(*) FROM asset_loans WHERE status = 'active' AND expected_return_date IS NOT NULL AND expected_return_date < CURDATE()")->fetchColumn(),
        'returned' => (int)$pdo->query("SELECT COUNT(*) FROM asset_loans WHERE status = 'returned'")->fetchColumn(),
    ];

    $where = ['1=1'];
    $params = [];

    if ($tab === 'active') {
        $where[] = "al.status = 'active'";
    } elseif ($tab === 'overdue') {
        $where[] = "al.status = 'active' AND al.expected_return_date IS NOT NULL AND al.expected_return_date < CURDATE()";
    } elseif ($tab === 'returned') {
        $where[] = "al.status = 'returned'";
    }

    if ($q !== '') {
        $where[] = "(al.loan_code LIKE ? OR al.borrower_name LIKE ? OR al.borrower_nik LIKE ? OR al.borrower_department LIKE ? OR EXISTS (
            SELECT 1 FROM asset_loan_items ali2 
            JOIN asset_items ai2 ON ai2.id = ali2.asset_item_id 
            WHERE ali2.loan_id = al.id AND (ai2.asset_code LIKE ? OR ai2.asset_name LIKE ?)
        ))";
        $w = '%' . $q . '%';
        $params = array_merge($params, [$w, $w, $w, $w, $w, $w]);
    }

    $sql = "SELECT al.*, u1.name AS officer_name,
            (SELECT COUNT(*) FROM asset_loan_items WHERE loan_id = al.id) AS total_items,
            (SELECT COUNT(*) FROM asset_loan_items WHERE loan_id = al.id AND status = 'borrowed') AS borrowed_items
            FROM asset_loans al
            LEFT JOIN users u1 ON u1.id = al.officer_user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY al.id DESC LIMIT 60";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ambil item ringkas untuk setiap pinjaman
    $loanIds = array_column($loans, 'id');
    $itemsMap = [];
    if (!empty($loanIds)) {
        $inSql = implode(',', array_fill(0, count($loanIds), '?'));
        $itemQ = $pdo->prepare("SELECT ali.loan_id, ali.status AS item_status, ai.asset_code, ai.asset_name
                                FROM asset_loan_items ali
                                JOIN asset_items ai ON ai.id = ali.asset_item_id
                                WHERE ali.loan_id IN ($inSql)
                                ORDER BY ali.id ASC");
        $itemQ->execute($loanIds);
        foreach ($itemQ->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $itemsMap[$row['loan_id']][] = $row;
        }
    }

    render_mobile_header('Peminjaman Aset', $user);
    ?>
    <!-- Aksi Cepat Mobile -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;">
        <a href="<?= route_url('mobile_asset_loan_create') ?>" class="btn btn-primary" style="margin:0;padding:12px 10px;font-size:13px;font-weight:700;">
            <span>➕</span> Pinjam Baru
        </a>
        <a href="<?= route_url('mobile_asset_loan_return') ?>" class="btn btn-success" style="margin:0;padding:12px 10px;font-size:13px;font-weight:700;">
            <span>🔄</span> Scan Kembali
        </a>
    </div>

    <!-- Ringkasan Status -->
    <?php
    $renderLoanCard = function($number, $label, $iconPath, $tabId, $isActive, $isDanger = false) use ($tab) {
        if ($isDanger) {
            $t = $isActive ? ['#fef2f2', '#7f1d1d', '#ef4444'] : ['#fef2f2', '#7f1d1d', '#ef4444'];
        } else if ($tabId === 'active') {
            $t = $isActive ? ['#eff6ff', '#1e3a8a', '#3b82f6'] : ['#eff6ff', '#1e3a8a', '#3b82f6'];
        } else {
            $t = $isActive ? ['#f0fdf4', '#14532d', '#22c55e'] : ['#f0fdf4', '#14532d', '#22c55e'];
        }
        
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="' . $t[2] . '"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="' . $iconPath . '"/></svg>';
        $bgImage = "data:image/svg+xml;base64," . base64_encode($svg);

        $border = $isActive ? "5px solid {$t[2]}" : "4px solid {$t[2]}";
        $opacity = $isActive ? "1" : "0.7";
        
        return '<a href="' . route_url('mobile_asset_loans', ['tab' => $tabId]) . '" style="text-decoration:none; display:block; opacity:'.$opacity.'; background-color: ' . $t[0] . '; color: ' . $t[1] . '; border-left: ' . $border . '; position: relative; overflow: hidden; padding: 12px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">'
             . '<div style="position: absolute; right: -8px; top: -8px; width: 60px; height: 60px; opacity: 0.15; background-image: url(\'' . $bgImage . '\'); background-size: contain; background-repeat: no-repeat; transform: rotate(-10deg);"></div>'
             . '<div style="position: relative; z-index: 1;">'
             . '<strong style="font-size: 24px; display: block; margin-bottom: 2px; line-height: 1;">' . e($number) . '</strong>'
             . '<span style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8;">' . e($label) . '</span>'
             . '</div></a>';
    };
    ?>
    <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:6px;margin-bottom:12px;">
        <?= $renderLoanCard($stats['active'], 'Dipinjam', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'active', $tab === 'active') ?>
        <?= $renderLoanCard($stats['overdue'], 'Terlambat', 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z', 'overdue', $tab === 'overdue', $stats['overdue'] > 0) ?>
        <?= $renderLoanCard($stats['returned'], 'Kembali', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'returned', $tab === 'returned') ?>
    </div>

    <!-- Pencarian -->
    <form method="get" style="margin-bottom:12px;display:flex;gap:6px;">
        <input type="hidden" name="route" value="mobile_asset_loans">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <input name="q" value="<?= e($q) ?>" placeholder="Cari peminjam / kode alat..." style="flex:1;font-size:13px;padding:8px 10px;">
        <button type="submit" class="btn btn-secondary" style="width:auto;margin:0;padding:8px 14px;font-size:13px;">Cari</button>
        <?php if ($q !== ''): ?>
            <a href="<?= route_url('mobile_asset_loans', ['tab' => $tab]) ?>" class="btn btn-secondary" style="width:auto;margin:0;padding:8px 12px;">✕</a>
        <?php endif; ?>
    </form>

    <!-- Daftar Peminjaman -->
    <?php if (empty($loans)): ?>
        <div class="panel" style="text-align:center;padding:30px 16px;color:#64748b;">
            <div style="font-size:32px;margin-bottom:8px;">📭</div>
            <div style="font-size:14px;font-weight:600;">Tidak ada data peminjaman aset.</div>
            <div style="font-size:12px;margin-top:4px;">Gunakan tombol "Pinjam Baru" untuk mencatat serah-terima unit aset.</div>
        </div>
    <?php else: ?>
        <?php foreach ($loans as $loan): ?>
            <?php
            $isOverdue = ($loan['status'] === 'active' && !empty($loan['expected_return_date']) && $loan['expected_return_date'] < date('Y-m-d'));
            $items = $itemsMap[$loan['id']] ?? [];
            ?>
            <div class="panel" style="<?= $isOverdue ? 'border-color:#ef4444;' : '' ?>">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                    <div>
                        <div style="font-family:monospace;font-size:14px;font-weight:700;color:#0284c7;">
                            <?= e($loan['loan_code']) ?>
                        </div>
                        <div style="font-size:15px;font-weight:700;;margin-top:2px;">
                            <?= e($loan['borrower_name']) ?>
                        </div>
                        <div style="font-size:12px;color:#64748b;">
                            <?= e($loan['borrower_department'] ?: '-') ?> &bull; NIK: <?= e($loan['borrower_nik'] ?: '-') ?>
                        </div>
                    </div>
                    <div>
                        <?php if ($loan['status'] === 'returned'): ?>
                            <span class="badge badge-returned">Sudah Kembali</span>
                        <?php elseif ($isOverdue): ?>
                            <span class="badge badge-overdue">Terlambat</span>
                        <?php else: ?>
                            <span class="badge badge-active">Sedang Dipinjam</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info Tanggal -->
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-top:10px;padding:8px;background:#f1f5f9;border-radius:6px;">
                    <div>
                        <span style="color:#64748b;">Dipinjam:</span>
                        <strong style="color:#64748b;"><?= date('d/m/Y', strtotime($loan['loan_date'])) ?></strong>
                    </div>
                    <div>
                        <span style="color:#64748b;">Jatuh Tempo:</span>
                        <strong style="color:<?= $isOverdue ? '#f87171' : '#cbd5e1' ?>;">
                            <?= !empty($loan['expected_return_date']) ? date('d/m/Y', strtotime($loan['expected_return_date'])) : '-' ?>
                        </strong>
                    </div>
                </div>

                <!-- List Items Ringkas -->
                <div style="margin-top:10px;">
                    <div style="font-size:11px;color:#64748b;font-weight:700;text-transform:uppercase;margin-bottom:4px;">
                        Unit Aset (<?= count($items) ?>):
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                        <?php foreach ($items as $it): ?>
                            <span style="font-size:11px;padding:3px 6px;border-radius:4px;background:#334155;color:#e2e8f0;display:inline-flex;align-items:center;gap:4px;">
                                <strong style="color:#0284c7;font-family:monospace;"><?= e($it['asset_code']) ?></strong>
                                <span><?= e($it['asset_name']) ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tombol Aksi -->
                <div style="display:flex;gap:6px;margin-top:12px;">
                    <?php if ($loan['status'] === 'active'): ?>
                        <a href="<?= route_url('mobile_asset_loan_return', ['id' => $loan['id']]) ?>" class="btn btn-success" style="flex:1;margin:0;padding:8px 10px;font-size:13px;">
                            <span>🔄</span> Proses Pengembalian
                        </a>
                    <?php endif; ?>
                    <a href="<?= route_url('mobile_asset_loan_detail', ['id' => $loan['id']]) ?>" class="btn btn-secondary" style="<?= $loan['status'] === 'active' ? 'width:auto;min-width:75px;' : 'flex:1;' ?>margin:0;padding:8px 10px;font-size:13px;">
                        <span>👁️</span> Detail
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php
    render_mobile_footer('dashboard');
}

/**
 * 2. Form Peminjaman Baru Mobile (Scan QR Camera + Autocomplete Pengguna)
 */
/**
 * Modul Mobile Loan: Buat Peminjaman
 * Proses: Form khusus mobile untuk mencatat transaksi peminjaman baru di lapangan.
 */
function handle_route_mobile_asset_loan_create(PDO $pdo): void
{
    $user = require_mobile_loan_user();

    // Proses Simpan POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $borrowerName = trim((string)($_POST['borrower_name'] ?? ''));
        $borrowerNik = trim((string)($_POST['borrower_nik'] ?? ''));
        $borrowerDept = trim((string)($_POST['borrower_department'] ?? ''));
        $borrowerPhone = trim((string)($_POST['borrower_phone'] ?? ''));
        $loanDate = trim((string)($_POST['loan_date'] ?? date('Y-m-d')));
        $expectedReturn = trim((string)($_POST['expected_return_date'] ?? ''));
        $purpose = trim((string)($_POST['purpose'] ?? ''));
        $assetItemIds = $_POST['asset_item_ids'] ?? [];
        $conditionOut = $_POST['condition_out'] ?? [];
        $notesOut = $_POST['item_notes_out'] ?? [];

        if ($borrowerName === '') {
            flash('Nama peminjam wajib diisi atau dipilih dari Master Pengguna.', 'err');
            redirect_to('mobile_asset_loan_create');
        }

        if (empty($assetItemIds) || !is_array($assetItemIds)) {
            flash('Scan atau pilih minimal 1 unit aset yang akan dipinjam.', 'err');
            redirect_to('mobile_asset_loan_create');
        }

        try {
            $pdo->beginTransaction();

            // Generate Kode Peminjaman LN-YYYYMM-XXXX
            $prefix = 'LN-' . date('Ym') . '-';
            $stmtSeq = $pdo->prepare("SELECT COUNT(*) FROM asset_loans WHERE loan_code LIKE ?");
            $stmtSeq->execute([$prefix . '%']);
            $seq = (int)$stmtSeq->fetchColumn() + 1;
            $loanCode = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

            // Double check keunikan kode
            $chkStmt = $pdo->prepare("SELECT COUNT(*) FROM asset_loans WHERE loan_code = ?");
            $chkStmt->execute([$loanCode]);
            if ((int)$chkStmt->fetchColumn() > 0) {
                $loanCode = $prefix . str_pad((string)($seq + mt_rand(1, 99)), 4, '0', STR_PAD_LEFT);
            }

            // Insert Header
            $insHeader = $pdo->prepare("INSERT INTO asset_loans 
                (loan_code, borrower_name, borrower_nik, borrower_department, borrower_phone, loan_date, expected_return_date, purpose, officer_user_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
            $insHeader->execute([
                $loanCode,
                $borrowerName,
                $borrowerNik ?: null,
                $borrowerDept ?: null,
                $borrowerPhone ?: null,
                $loanDate ?: date('Y-m-d'),
                $expectedReturn ?: null,
                $purpose ?: null,
                $user['id'] ?? null
            ]);
            $loanId = (int)$pdo->lastInsertId();

            // Status borrowed ID
            $borrowedStatusId = (int)$pdo->query("SELECT id FROM asset_statuses WHERE status_code = 'BORROWED' LIMIT 1")->fetchColumn();

            // Insert Items & Update Asset Status
            $insItem = $pdo->prepare("INSERT INTO asset_loan_items 
                (loan_id, asset_item_id, condition_out, notes_out, photo_out, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'borrowed', NOW())");

            $updAsset = $pdo->prepare("UPDATE asset_items 
                SET status = 'borrowed', asset_status_id = ?, custodian_name = ?, custodian_nik = ?, updated_at = NOW() 
                WHERE id = ?");

            $firstPhotoOut = null;
            foreach ($assetItemIds as $aid) {
                $aid = (int)$aid;
                if ($aid <= 0) continue;

                $cond = trim((string)($conditionOut[$aid] ?? 'Normal / Baik'));
                $note = trim((string)($notesOut[$aid] ?? ''));

                $photoOut = null;
                $fileKey = 'photo_out_' . $aid;
                if (!empty($_FILES[$fileKey]['tmp_name']) && is_uploaded_file($_FILES[$fileKey]['tmp_name'])) {
                    $photoOut = save_mobile_loan_photo($_FILES[$fileKey], 'loan_out_' . $aid);
                    if ($firstPhotoOut === null && $photoOut) {
                        $firstPhotoOut = $photoOut;
                    }
                }

                $insItem->execute([$loanId, $aid, $cond ?: 'Normal / Baik', $note ?: null, $photoOut]);
                $updAsset->execute([$borrowedStatusId ?: null, $borrowerName, $borrowerNik ?: null, $aid]);
            }

            if ($firstPhotoOut !== null) {
                $updLoanPhoto = $pdo->prepare("UPDATE asset_loans SET photo_out = ? WHERE id = ?");
                $updLoanPhoto->execute([$firstPhotoOut, $loanId]);
            }

            $pdo->commit();
            flash("Peminjaman <strong>{$loanCode}</strong> untuk <strong>{$borrowerName}</strong> berhasil disimpan!", 'ok');
            redirect_to('mobile_asset_loans');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('Gagal menyimpan peminjaman aset: ' . $e->getMessage(), 'err');
            redirect_to('mobile_asset_loan_create');
        }
    }

    $employees = [];
    try {
        if (function_exists('employee_search_rows')) {
            $employees = employee_search_rows($pdo, '', 500);
        }
        if (empty($employees) && db_table_exists($pdo, 'employee_directory')) {
            $hasName = db_column_exists($pdo, 'employee_directory', 'name');
            $nameExpr = $hasName ? "COALESCE(NULLIF(name, ''), employee_name, '')" : "employee_name";
            $employees = $pdo->query("SELECT nik, $nameExpr AS name, department FROM employee_directory WHERE is_active = 1 ORDER BY name ASC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $ignored) {}

    $availableLoanAssets = [];
    try {
        $availableLoanAssets = $pdo->query("SELECT ai.id, ai.asset_code, ai.asset_name, ai.brand, ai.model, ai.serial_number,
                                                   ai.custodian_name, ai.custodian_nik,
                                                   g.group_name, COALESCE(t.type_name, ai.asset_type, '-') AS type_name, loc.location_name
                                            FROM asset_items ai
                                            LEFT JOIN asset_groups g ON g.id = ai.asset_group_id
                                            LEFT JOIN asset_types t ON t.id = ai.asset_type_id
                                            LEFT JOIN asset_locations loc ON loc.id = ai.location_id
                                            WHERE ai.status IN ('active', 'available', 'ready', 'normal', 'standalone')
                                              AND LOWER(ai.status) <> 'deleted'
                                            ORDER BY ai.asset_code ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ignored) {}

    render_mobile_header('Pinjam Aset Baru', $user);
    ?>
    <div class="panel">
        <h2 style="display:flex;align-items:center;gap:6px;">
            <span>➕</span> Catat Peminjaman Aset
        </h2>
        <form method="post" action="<?= route_url('mobile_asset_loan_create') ?>" enctype="multipart/form-data" onsubmit="return validateMobileLoanForm()">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

            <!-- 1. Peminjam (Single-Box Autocomplete Master Pengguna) -->
            <div style="background:#f1f5f9;border-radius:8px;padding:12px;margin-bottom:12px;">
                <div style="font-size:12px;font-weight:700;color:#0284c7;text-transform:uppercase;margin-bottom:6px;">
                    👤 1. Identitas Peminjam (Master Pengguna)
                </div>
                <div style="position:relative;margin-bottom:8px;">
                    <input type="text" id="employeeSearchInput" placeholder="Ketik nama karyawan, NIK, atau Dept..." autocomplete="off" style="width:100%;box-sizing:border-box;padding:9px 12px;background:#fff;border:1.5px solid #0284c7;border-radius:6px;font-size:13px;">
                    <div id="employeeDropdownList" style="display:none;position:absolute;top:calc(100% + 2px);left:0;right:0;max-height:220px;overflow-y:auto;background:#fff;border:1px solid #cbd5e1;border-radius:6px;z-index:99999;box-shadow:0 10px 25px rgba(0,0,0,0.18);"></div>
                </div>
                <div id="borrowerSourceBadge" style="display:none;margin-top:6px;font-size:11px;background:#065f46;color:#d1fae5;padding:4px 8px;border-radius:4px;font-weight:600;"></div>

                <div style="margin-top:8px;">
                    <label>Nama Peminjam <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="borrower_name" id="borrowerNameInput" required placeholder="Nama lengkap peminjam">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <div>
                        <label>NIK / ID Karyawan</label>
                        <input type="text" name="borrower_nik" id="borrowerNikInput" placeholder="NIK">
                    </div>
                    <div>
                        <label>Departemen</label>
                        <input type="text" name="borrower_department" id="borrowerDeptInput" placeholder="Departemen">
                    </div>
                </div>
                <div>
                    <label>No. Telepon / WhatsApp</label>
                    <input type="text" name="borrower_phone" placeholder="Contoh: 08123456789">
                </div>
            </div>

            <!-- 2. Tanggal Peminjaman -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;">
                <div>
                    <label>Tanggal Pinjam</label>
                    <input type="date" name="loan_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div>
                    <label>Batas Pengembalian</label>
                    <input type="date" name="expected_return_date" value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                </div>
            </div>

            <!-- 3. Scanner Kamera QR Live & Barcode Input -->
            <div style="background:#f1f5f9;border:1px solid #0284c7;border-radius:8px;padding:12px;margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <div style="font-size:12px;font-weight:700;color:#0284c7;text-transform:uppercase;">
                        📷 2. Scan QR / Masukkan Kode Unit / Cari Pengguna <span style="color:#ef4444;">*</span>
                    </div>
                    <button type="button" id="btnToggleCamera" onclick="toggleCameraScanner()" style="background:#0284c7;color:#fff;border:none;border-radius:6px;padding:6px 10px;font-size:11px;font-weight:700;cursor:pointer;">
                        📷 Buka Kamera
                    </button>
                </div>

                <!-- Box Scanner Kamera Live -->
                <div id="cameraScannerBox" style="display:none;margin-bottom:10px;text-align:center;">
                    <div id="reader" style="width:100%;max-width:280px;margin:0 auto;border-radius:8px;overflow:hidden;background:#000;"></div>
                    <p id="camStatus" style="color:#0284c7;font-size:11px;margin:6px 0;">Menyiapkan kamera live QR...</p>
                </div>

                <!-- Manual / Barcode input Single-Box Combobox -->
                <div style="position:relative;">
                    <div style="display:flex;gap:6px;">
                        <input type="text" id="barcodeAssetInput" placeholder="🔍 Cari kode, nama aset, SN, pengguna..." autocomplete="off" style="font-family:monospace;font-weight:700;font-size:13px;border:1.5px solid #0284c7;border-radius:6px;padding:9px 12px;width:100%;box-sizing:border-box;" onfocus="renderAssetDropdown(this.value)" oninput="renderAssetDropdown(this.value)" onkeydown="if(event.key==='Enter'){event.preventDefault();var dd=document.getElementById('assetDropdownList');if(dd)dd.style.display='none';lookupAndAddAsset(this.value);}">
                        <button type="button" class="btn btn-primary" onclick="var dd=document.getElementById('assetDropdownList');if(dd)dd.style.display='none';lookupAndAddAsset(document.getElementById('barcodeAssetInput').value)" style="width:auto;margin:0;padding:8px 14px;white-space:nowrap;font-size:13px;">
                            + Tambah
                        </button>
                    </div>
                    <div id="assetDropdownList" style="display:none;position:absolute;top:calc(100% + 2px);left:0;right:0;max-height:220px;overflow-y:auto;background:#fff;border:1px solid #cbd5e1;border-radius:6px;z-index:99999;box-shadow:0 10px 25px rgba(0,0,0,0.18);"></div>
                </div>
                <div id="scanFeedbackAlert" style="display:none;margin-top:8px;padding:8px 10px;border-radius:6px;font-size:12px;font-weight:600;"></div>
            </div>

            <!-- 4. Keranjang Unit Terpilih -->
            <div style="margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                    <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;">
                        Unit Aset Terpilih
                    </div>
                    <span id="selectedCountBadge" class="badge badge-active">0 unit</span>
                </div>

                <div id="loanItemsContainer"></div>
                <div id="emptyLoanItemsMsg" style="padding:20px;text-align:center;background:#f1f5f9;border-radius:8px;border:1px dashed #334155;color:#64748b;font-size:12px;">
                    Belum ada unit aset yang ditambahkan. Silakan scan QR code pada unit fisik atau ketik kode unit di atas.
                </div>
            </div>

            <!-- 5. Keperluan Pinjam -->
            <div style="margin-bottom:16px;">
                <label>Keperluan / Catatan Pinjam</label>
                <textarea name="purpose" placeholder="Contoh: Pekerjaan maintenance server lapangan / presentasi cabang..." style="min-height:70px;"></textarea>
            </div>

            <!-- Tombol Simpan -->
            <button type="submit" class="btn btn-primary" style="padding:14px;font-size:15px;font-weight:700;">
                💾 Simpan Peminjaman Aset
            </button>
            <a href="<?= route_url('mobile_asset_loans') ?>" class="btn btn-secondary" style="margin-top:6px;">
                Batal
            </a>
        </form>
    </div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
    var scannedItems = [];
    var availableLoanAssets = <?= json_encode($availableLoanAssets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var html5QrCode = null;
    var isCameraOpen = false;

    function escapeHtml(str) {
        if (!str) return "";
        return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function renderAssetDropdown(q) {
        var dd = document.getElementById("assetDropdownList");
        if (!dd) return;
        q = (q || "").trim().toLowerCase();
        
        var matches = availableLoanAssets.filter(function(item) {
            if (!q) return true;
            var code = (item.asset_code || "").toLowerCase();
            var name = (item.asset_name || "").toLowerCase();
            var brand = (item.brand || "").toLowerCase();
            var model = (item.model || "").toLowerCase();
            var group = (item.group_name || "").toLowerCase();
            var type = (item.type_name || "").toLowerCase();
            var loc = (item.location_name || "").toLowerCase();
            var sn = (item.serial_number || "").toLowerCase();
            var custName = (item.custodian_name || "").toLowerCase();
            var custNik = (item.custodian_nik || "").toLowerCase();
            return code.includes(q) || name.includes(q) || brand.includes(q) || model.includes(q) || group.includes(q) || type.includes(q) || loc.includes(q) || sn.includes(q) || custName.includes(q) || custNik.includes(q);
        });

        if (matches.length === 0) {
            dd.innerHTML = "<div style='padding:10px 14px;font-size:12px;color:#64748b;text-align:center;'>Tidak ada unit aset tersedia yang cocok dengan \"" + escapeHtml(q) + "\"</div>";
            dd.style.display = "block";
            return;
        }

        dd.innerHTML = "";
        matches.slice(0, 20).forEach(function(item) {
            var subParts = [];
            if (item.brand || item.model) subParts.push((item.brand ? item.brand + " " : "") + (item.model || ""));
            if (item.serial_number) subParts.push("SN: " + item.serial_number);
            if (item.group_name || item.type_name) subParts.push((item.group_name || "") + " › " + (item.type_name || ""));
            if (item.location_name) subParts.push("📍 " + item.location_name);
            if (item.custodian_name) subParts.push("👤 Pengguna: " + item.custodian_name + (item.custodian_nik ? " (" + item.custodian_nik + ")" : ""));

            var div = document.createElement("div");
            div.style.padding = "9px 12px";
            div.style.borderBottom = "1px solid #f1f5f9";
            div.style.cursor = "pointer";
            div.style.background = "#fff";
            div.innerHTML = "<div style='font-weight:700;color:#0284c7;font-family:monospace;font-size:13px;'>" + escapeHtml(item.asset_code) + " &bull; " + escapeHtml(item.asset_name || "-") + "</div>"
                + "<div style='font-size:11px;color:#64748b;margin-top:2px;'>" + escapeHtml(subParts.join(" &bull; ")) + "</div>";
            div.onmouseenter = function() { div.style.background = "#f0f9ff"; };
            div.onmouseleave = function() { div.style.background = "#fff"; };
            div.onmousedown = function() {
                dd.style.display = "none";
                var input = document.getElementById("barcodeAssetInput");
                if (input) input.value = "";
                lookupAndAddAsset(item.asset_code);
            };
            dd.appendChild(div);
        });
        dd.style.display = "block";
    }

    document.addEventListener("click", function(e) {
        var dd = document.getElementById("assetDropdownList");
        var input = document.getElementById("barcodeAssetInput");
        if (dd && input && !dd.contains(e.target) && e.target !== input) {
            dd.style.display = "none";
        }
    });

    function previewItemPhoto(input, targetId) {
        var target = document.getElementById(targetId);
        if (!target) return;
        target.innerHTML = "";
        if (input.files && input.files[0]) {
            var file = input.files[0];
            var reader = new FileReader();
            reader.onload = function(e) {
                target.innerHTML = 
                    "<div style='position:relative;display:inline-block;'>"
                    + "  <img src='" + e.target.result + "' style='width:48px;height:48px;object-fit:cover;border-radius:6px;border:2px solid #38bdf8;display:block;'>"
                    + "  <button type='button' onclick='clearItemPhoto(\"" + input.id + "\", \"" + targetId + "\")' style='position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;border:none;border-radius:50%;width:18px;height:18px;font-size:11px;font-weight:bold;line-height:18px;padding:0;cursor:pointer;'>✕</button>"
                    + "</div>"
                    + "<span style='font-size:10px;color:#0284c7;font-weight:600;'>Foto Siap</span>";
            };
            reader.readAsDataURL(file);
        }
    }

    function clearItemPhoto(inputId, targetId) {
        var input = document.getElementById(inputId);
        if (input) input.value = "";
        var target = document.getElementById(targetId);
        if (target) target.innerHTML = "";
    }

    function renderScannedContainer() {
        var container = document.getElementById("loanItemsContainer");
        var emptyMsg = document.getElementById("emptyLoanItemsMsg");
        var badge = document.getElementById("selectedCountBadge");

        if (scannedItems.length === 0) {
            container.innerHTML = "";
            emptyMsg.style.display = "block";
            badge.textContent = "0 unit";
            badge.className = "badge";
            badge.style.background = "#475569";
            return;
        }

        emptyMsg.style.display = "none";
        badge.textContent = scannedItems.length + " unit terpilih";
        badge.className = "badge badge-active";
        badge.style.background = "#0284c7";

        // Hapus card yang sudah tidak ada di scannedItems
        var existingCards = container.querySelectorAll("[data-item-id]");
        existingCards.forEach(function(card) {
            var cid = parseInt(card.getAttribute("data-item-id"), 10);
            if (!scannedItems.some(function(it) { return it.id === cid; })) {
                card.remove();
            }
        });

        // Tambahkan card untuk item baru tanpa me-reset isi input yang sudah ada
        scannedItems.forEach(function(item) {
            if (container.querySelector("[data-item-id='" + item.id + "']")) {
                return;
            }

            var card = document.createElement("div");
            card.setAttribute("data-item-id", item.id);
            card.id = "card_item_" + item.id;
            card.style.background = "#0f172a";
            card.style.border = "1px solid #334155";
            card.style.borderRadius = "8px";
            card.style.padding = "10px";
            card.style.marginBottom = "8px";

            card.innerHTML = 
                "<div style='display:flex;justify-content:space-between;align-items:flex-start;gap:6px;'>"
                + "<div>"
                + "  <div style='font-family:monospace;font-weight:800;color:#0284c7;font-size:13px;'>"
                + "    <input type='hidden' name='asset_item_ids[]' value='" + item.id + "'>"
                + "    " + item.asset_code
                + "  </div>"
                + "  <div style='font-size:13px;font-weight:700;;margin-top:2px;'>" + (item.asset_name || "-") + "</div>"
                + "  <div style='font-size:11px;color:#64748b;'>" + (item.brand ? (item.brand + " ") : "") + (item.model || "") + "</div>"
                + "</div>"
                + "<button type='button' onclick='removeScannedItem(" + item.id + ")' style='background:#dc2626;color:#fff;border:none;border-radius:4px;padding:4px 8px;font-size:11px;cursor:pointer;'>✕ Hapus</button>"
                + "</div>"
                + "<div style='margin-top:8px;display:grid;grid-template-columns:1fr 1fr;gap:6px;'>"
                + "  <div>"
                + "    <div style='font-size:10px;color:#64748b;margin-bottom:2px;'>Kondisi Awal</div>"
                + "    <input name='condition_out[" + item.id + "]' value='Normal / Baik' placeholder='Kondisi' style='padding:6px;font-size:12px;'>"
                + "  </div>"
                + "  <div>"
                + "    <div style='font-size:10px;color:#64748b;margin-bottom:2px;'>Catatan Fisik</div>"
                + "    <input name='item_notes_out[" + item.id + "]' value='' placeholder='Kelengkapan...' style='padding:6px;font-size:12px;'>"
                + "  </div>"
                + "</div>"
                + "<div style='margin-top:8px;padding-top:8px;border-top:1px dashed #334155;'>"
                + "  <div style='font-size:10px;color:#64748b;margin-bottom:4px;display:flex;justify-content:space-between;align-items:center;'>"
                + "    <span>📷 Foto Unit Saat Dipinjam</span>"
                + "    <span style='font-size:10px;color:#64748b;'>(Kamera HP)</span>"
                + "  </div>"
                + "  <div style='display:flex;align-items:center;gap:8px;flex-wrap:wrap;'>"
                + "    <label for='photo_out_" + item.id + "' class='btn btn-secondary' style='margin:0;padding:6px 10px;font-size:11px;cursor:pointer;display:inline-flex;align-items:center;gap:4px;'>"
                + "      <span>📸 Ambil / Pilih Foto</span>"
                + "    </label>"
                + "    <input type='file' id='photo_out_" + item.id + "' name='photo_out_" + item.id + "' accept='image/*' capture='environment' style='display:none;' onchange='previewItemPhoto(this, \"preview_out_" + item.id + "\")'>"
                + "    <div id='preview_out_" + item.id + "' style='display:flex;align-items:center;gap:6px;'></div>"
                + "  </div>"
                + "</div>";

            container.appendChild(card);
        });
    }

    function removeScannedItem(id) {
        scannedItems = scannedItems.filter(function(x) { return x.id !== id; });
        renderScannedContainer();
        showFeedback("Unit telah dihapus dari daftar pinjaman.", "info");
    }

    function showFeedback(msg, type) {
        var fb = document.getElementById("scanFeedbackAlert");
        if (!fb) return;
        fb.style.display = "block";
        fb.textContent = msg;
        if (type === "success") {
            fb.style.background = "#065f46";
            fb.style.color = "#d1fae5";
            fb.style.border = "1px solid #047857";
        } else if (type === "error") {
            fb.style.background = "#881337";
            fb.style.color = "#ffe4e6";
            fb.style.border = "1px solid #be123c";
        } else {
            fb.style.background = "#1e3a8a";
            fb.style.color = "#dbeafe";
            fb.style.border = "1px solid #3b82f6";
        }
    }

    function lookupAndAddAsset(code) {
        code = (code || "").trim();
        if (!code) {
            showFeedback("Silakan masukkan atau scan kode unit aset terlebih dahulu.", "error");
            return;
        }

        var already = scannedItems.some(function(item) {
            return item.asset_code.toUpperCase() === code.toUpperCase() || String(item.id) === code;
        });
        if (already) {
            showFeedback("Unit " + code + " sudah ada dalam daftar di bawah.", "error");
            document.getElementById("barcodeAssetInput").value = "";
            return;
        }

        showFeedback("Memverifikasi unit: " + code + "...", "info");
        var url = "index.php?route=api_lookup_asset_for_loan&code=" + encodeURIComponent(code);

        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(resp) {
                if (!resp || !resp.ok || !resp.found) {
                    showFeedback(resp && resp.message ? resp.message : "Unit aset tidak ditemukan di database.", "error");
                    return;
                }
                if (!resp.is_available) {
                    showFeedback(resp.message || "Unit tidak dapat dipinjam saat ini.", "error");
                    return;
                }
                var item = resp.data;
                if (scannedItems.some(function(x) { return x.id === item.id; })) {
                    showFeedback("Unit " + item.asset_code + " sudah ada dalam daftar.", "error");
                    document.getElementById("barcodeAssetInput").value = "";
                    return;
                }

                scannedItems.push({
                    id: item.id,
                    asset_code: item.asset_code,
                    asset_name: item.asset_name,
                    brand: item.brand,
                    model: item.model,
                    group_name: item.group_name,
                    type_name: item.type_name
                });

                renderScannedContainer();
                showFeedback("✓ Unit " + item.asset_code + " berhasil ditambahkan! Silakan foto kondisi aset di bawah.", "success");
                document.getElementById("barcodeAssetInput").value = "";
                setTimeout(function() {
                    var el = document.getElementById("card_item_" + item.id);
                    if (el) el.scrollIntoView({ behavior: "smooth", block: "center" });
                }, 150);
            })
            .catch(function(err) {
                showFeedback("Gagal menghubungi server: " + err.message, "error");
            });
    }

    function toggleCameraScanner() {
        var box = document.getElementById("cameraScannerBox");
        var btn = document.getElementById("btnToggleCamera");
        if (isCameraOpen) {
            if (html5QrCode) {
                html5QrCode.stop().then(function() {
                    html5QrCode.clear();
                    box.style.display = "none";
                    btn.textContent = "📷 Buka Kamera";
                    isCameraOpen = false;
                }).catch(function() {
                    box.style.display = "none";
                    btn.textContent = "📷 Buka Kamera";
                    isCameraOpen = false;
                });
            } else {
                box.style.display = "none";
                btn.textContent = "📷 Buka Kamera";
                isCameraOpen = false;
            }
        } else {
            box.style.display = "block";
            btn.textContent = "✕ Tutup Kamera";
            isCameraOpen = true;
            startCameraScanner();
        }
    }

    function startCameraScanner() {
        var camStatus = document.getElementById("camStatus");
        camStatus.textContent = "Menyalakan kamera live...";
        html5QrCode = new Html5Qrcode("reader");
        var config = { fps: 10, qrbox: { width: 220, height: 220 } };
        html5QrCode.start({ facingMode: "environment" }, config, function(decodedText) {
            camStatus.textContent = "QR Terbaca: " + decodedText;
            toggleCameraScanner(); // Otomatis keluar dari foto scan QR setelah berhasil membaca kode
            lookupAndAddAsset(decodedText);
        }).catch(function(err) {
            camStatus.textContent = "Kamera tidak dapat diakses (" + err + ").";
        });
    }

    // Single-Box Autocomplete Master Pengguna (Mobile)
    (function() {
        var searchInput = document.getElementById("employeeSearchInput");
        var dropdown = document.getElementById("employeeDropdownList");
        var nikInput = document.getElementById("borrowerNikInput");
        var nameInput = document.getElementById("borrowerNameInput");
        var deptInput = document.getElementById("borrowerDeptInput");
        var badge = document.getElementById("borrowerSourceBadge");
        var employees = <?php echo json_encode($employees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        function hideDropdown() {
            setTimeout(function() { if (dropdown) dropdown.style.display = "none"; }, 220);
        }

        function selectEmployee(item) {
            if (nameInput) nameInput.value = item.name || "";
            if (nikInput) nikInput.value = item.nik || "";
            if (deptInput) deptInput.value = item.department || "";
            if (searchInput) searchInput.value = (item.name || "") + (item.nik ? (" (" + item.nik + ")") : "");
            if (badge) {
                badge.style.display = "block";
                badge.textContent = "✓ Terpilih: " + (item.name || item.nik) + (item.department ? (" (" + item.department + ")") : "");
            }
            if (dropdown) dropdown.style.display = "none";
        }

        function renderEmployees(q) {
            if (!dropdown) return;
            q = (q || "").trim().toLowerCase();
            var filtered = employees.filter(function(emp) {
                if (!q) return true;
                var name = (emp.name || "").toLowerCase();
                var nik = (emp.nik || "").toLowerCase();
                var dept = (emp.department || "").toLowerCase();
                return name.includes(q) || nik.includes(q) || dept.includes(q);
            }).slice(0, 30);

            dropdown.innerHTML = "";
            if (filtered.length === 0) {
                dropdown.innerHTML = "<div style='padding:10px;color:#94a3b8;font-size:12px;'>Tidak ada karyawan cocok di Master Pengguna.</div>";
                dropdown.style.display = "block";
                return;
            }
            filtered.forEach(function(row) {
                var div = document.createElement("div");
                div.style.padding = "8px 12px";
                div.style.cursor = "pointer";
                div.style.borderBottom = "1px solid #f1f5f9";
                div.style.fontSize = "13px";
                div.innerHTML = "<strong style='color:#0f172a;'>" + (row.name || "-") + "</strong>"
                    + " <span style='color:#64748b;font-size:11px;'>(" + (row.nik || "-") + ")</span>"
                    + (row.department ? (" &bull; <span style='color:#0284c7;font-size:11px;font-weight:600;'>" + row.department + "</span>") : "");
                div.onmousedown = function() { selectEmployee(row); };
                dropdown.appendChild(div);
            });
            dropdown.style.display = "block";
        }

        if (searchInput) {
            searchInput.addEventListener("input", function() {
                renderEmployees(this.value);
            });
            searchInput.addEventListener("focus", function() {
                renderEmployees(this.value);
            });
            searchInput.addEventListener("blur", hideDropdown);
        }
    })();

    function validateMobileLoanForm() {
        var name = document.getElementById("borrowerNameInput").value.trim();
        if (!name) {
            alert("Nama peminjam wajib diisi.");
            document.getElementById("borrowerNameInput").focus();
            return false;
        }
        if (scannedItems.length === 0) {
            alert("Scan atau masukkan minimal 1 unit aset yang akan dipinjam terlebih dahulu.");
            document.getElementById("barcodeAssetInput").focus();
            return false;
        }
        return true;
    }

    renderScannedContainer();
    </script>
    <?php
    render_mobile_footer('create');
}

/**
 * 3. Pengembalian Aset Mobile (Cari Cepat lewat QR Scan / Pilih Transaksi)
 */
/**
 * Modul Mobile Loan: Pengembalian Aset
 * Proses: Form khusus mobile untuk memproses pengembalian unit fisik aset.
 */
function handle_route_mobile_asset_loan_return(PDO $pdo): void
{
    $user = require_mobile_loan_user();
    $id = (int)($_GET['id'] ?? $_POST['loan_id'] ?? 0);

    // Jika ID diberikan, proses form pengembalian
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM asset_loans WHERE id = ?");
        $stmt->execute([$id]);
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$loan) {
            flash('Data peminjaman tidak ditemukan.', 'err');
            redirect_to('mobile_asset_loans');
        }

        if ($loan['status'] === 'returned') {
            flash('Peminjaman nomor ' . $loan['loan_code'] . ' sudah berstatus dikembalikan.', 'info');
            redirect_to('mobile_asset_loan_detail', ['id' => $id]);
        }

        // Proses POST Pengembalian
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $actualReturnDate = trim((string)($_POST['actual_return_date'] ?? ''));
            if ($actualReturnDate === '') {
                $actualReturnDate = date('Y-m-d H:i:s');
            } else {
                $actualReturnDate = date('Y-m-d H:i:s', strtotime($actualReturnDate));
            }
            $officerNotes = trim((string)($_POST['officer_notes'] ?? ''));
            $itemStatuses = $_POST['item_status'] ?? [];
            $conditionsIn = $_POST['condition_in'] ?? [];
            $notesIn = $_POST['notes_in'] ?? [];

            $activeStatusId = (int)$pdo->query("SELECT id FROM asset_statuses WHERE status_code = 'ACTIVE' LIMIT 1")->fetchColumn();
            $repairStatusId = (int)$pdo->query("SELECT id FROM asset_statuses WHERE status_code = 'REPAIR' LIMIT 1")->fetchColumn();
            $lostStatusId = (int)$pdo->query("SELECT id FROM asset_statuses WHERE status_code = 'LOST' LIMIT 1")->fetchColumn();

            try {
                $pdo->beginTransaction();

                $updItem = $pdo->prepare("UPDATE asset_loan_items 
                    SET condition_in = ?, notes_in = ?, photo_in = COALESCE(?, photo_in), returned_at = ?, status = ? 
                    WHERE id = ? AND loan_id = ?");

                $updAsset = $pdo->prepare("UPDATE asset_items 
                    SET status = ?, asset_status_id = ?, custodian_name = NULL, custodian_nik = NULL, updated_at = NOW() 
                    WHERE id = ?");

                $itemQuery = $pdo->prepare("SELECT id, asset_item_id FROM asset_loan_items WHERE loan_id = ?");
                $itemQuery->execute([$id]);
                $existingItems = $itemQuery->fetchAll(PDO::FETCH_ASSOC);

                $firstPhotoIn = null;
                foreach ($existingItems as $it) {
                    $itemId = (int)$it['id'];
                    $assetId = (int)$it['asset_item_id'];

                    $st = in_array(($itemStatuses[$itemId] ?? ''), ['returned', 'damaged', 'lost'], true) ? $itemStatuses[$itemId] : 'returned';
                    $cond = trim((string)($conditionsIn[$itemId] ?? 'Normal / Baik'));
                    $note = trim((string)($notesIn[$itemId] ?? ''));

                    $photoIn = null;
                    $fileKey = 'photo_in_' . $itemId;
                    if (!empty($_FILES[$fileKey]['tmp_name']) && is_uploaded_file($_FILES[$fileKey]['tmp_name'])) {
                        $photoIn = save_mobile_loan_photo($_FILES[$fileKey], 'loan_in_' . $itemId);
                        if ($firstPhotoIn === null && $photoIn) {
                            $firstPhotoIn = $photoIn;
                        }
                    }

                    $updItem->execute([$cond ?: 'Normal / Baik', $note ?: null, $photoIn, $actualReturnDate, $st, $itemId, $id]);

                    if ($st === 'damaged') {
                        $updAsset->execute(['repair', $repairStatusId ?: null, $assetId]);
                    } elseif ($st === 'lost') {
                        $updAsset->execute(['lost', $lostStatusId ?: null, $assetId]);
                    } else {
                        $updAsset->execute(['active', $activeStatusId ?: null, $assetId]);
                    }
                }

                $updHeader = $pdo->prepare("UPDATE asset_loans 
                    SET status = 'returned', actual_return_date = ?, photo_in = COALESCE(?, photo_in), return_officer_user_id = ?, officer_notes = CONCAT(COALESCE(officer_notes,''), '\n[Pengembalian Mobile]: ', ?) 
                    WHERE id = ?");
                $updHeader->execute([$actualReturnDate, $firstPhotoIn, $user['id'] ?? null, $officerNotes ?: 'Semua unit dikembalikan via mobile', $id]);

                $pdo->commit();
                flash("Pengembalian unit untuk transaksi <strong>{$loan['loan_code']}</strong> berhasil dicatat!", 'ok');
                redirect_to('mobile_asset_loans');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                flash('Gagal memproses pengembalian: ' . $e->getMessage(), 'err');
                redirect_to('mobile_asset_loan_return', ['id' => $id]);
            }
        }

        // Ambil data item
        $itemsStmt = $pdo->prepare("SELECT ali.*, ai.asset_code, ai.asset_name, ai.brand, ai.model, g.group_name, COALESCE(t.type_name, ai.asset_type, '-') AS type_name
            FROM asset_loan_items ali
            JOIN asset_items ai ON ai.id = ali.asset_item_id
            LEFT JOIN asset_groups g ON g.id = ai.asset_group_id
            LEFT JOIN asset_types t ON t.id = ai.asset_type_id
            WHERE ali.loan_id = ?
            ORDER BY ali.id ASC");
        $itemsStmt->execute([$id]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        render_mobile_header('Pengembalian Aset', $user);
        ?>
        <div class="panel">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <div style="font-size:11px;color:#64748b;text-transform:uppercase;font-weight:700;">Konfirmasi Pengembalian</div>
                    <div style="font-family:monospace;font-size:16px;font-weight:800;color:#0284c7;margin-top:2px;">
                        <?= e($loan['loan_code']) ?>
                    </div>
                </div>
                <span class="badge badge-active">Sedang Dipinjam</span>
            </div>

            <div style="margin-top:10px;padding:10px;background:#f1f5f9;border-radius:8px;font-size:13px;">
                <div style="font-weight:700;;"><?= e($loan['borrower_name']) ?></div>
                <div style="color:#64748b;font-size:12px;">
                    <?= e($loan['borrower_department'] ?: '-') ?> &bull; NIK: <?= e($loan['borrower_nik'] ?: '-') ?>
                </div>
                <div style="margin-top:6px;font-size:12px;color:#64748b;">
                    Dipinjam: <strong><?= date('d/m/Y', strtotime($loan['loan_date'])) ?></strong> | Batas: <strong><?= !empty($loan['expected_return_date']) ? date('d/m/Y', strtotime($loan['expected_return_date'])) : '-' ?></strong>
                </div>
            </div>

            <form method="post" action="<?= route_url('mobile_asset_loan_return', ['id' => $id]) ?>" enctype="multipart/form-data" style="margin-top:14px;">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="loan_id" value="<?= $id ?>">

                <label>Waktu Pengembalian Aktual</label>
                <input type="datetime-local" name="actual_return_date" value="<?= date('Y-m-d\TH:i') ?>" required>

                <div style="margin-top:14px;">
                    <div style="font-size:12px;font-weight:700;color:#0284c7;text-transform:uppercase;margin-bottom:8px;">
                        Checklist Fisik Unit Dikembalikan (<?= count($items) ?>):
                    </div>

                    <?php foreach ($items as $idx => $it): ?>
                        <div style="background:#f1f5f9;;border-radius:8px;padding:12px;margin-bottom:10px;">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:6px;">
                                <div>
                                    <div style="font-family:monospace;font-weight:800;color:#0284c7;font-size:13px;">
                                        <?= e($it['asset_code']) ?>
                                    </div>
                                    <div style="font-size:13px;font-weight:700;;">
                                        <?= e($it['asset_name']) ?>
                                    </div>
                                    <div style="font-size:11px;color:#64748b;">
                                        <?= e($it['brand']) ?> <?= e($it['model']) ?>
                                    </div>
                                </div>
                                <div style="font-size:11px;color:#64748b;text-align:right;">
                                    Awal: <em><?= e($it['condition_out'] ?: 'Normal') ?></em>
                                </div>
                            </div>

                            <?php if (!empty($it['photo_out'])): ?>
                                <div style="margin-top:8px;padding:6px 8px;background:#fff;border-radius:6px;display:flex;align-items:center;gap:8px;">
                                    <a href="<?= e($it['photo_out']) ?>" target="_blank" style="flex-shrink:0;">
                                        <img src="<?= e($it['photo_out']) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:4px;border:1px solid #475569;" alt="Foto Pinjam">
                                    </a>
                                    <div style="font-size:11px;">
                                        <div style="color:#64748b;font-weight:600;">Foto Saat Dipinjam</div>
                                        <a href="<?= e($it['photo_out']) ?>" target="_blank" style="color:#0284c7;">Lihat ukuran penuh ↗</a>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div style="margin-top:8px;">
                                <label style="font-size:11px;margin:4px 0 2px;">Status Pengembalian</label>
                                <select name="item_status[<?= (int)$it['id'] ?>]" style="padding:8px;font-size:13px;">
                                    <option value="returned">✓ Kembali Normal / Baik</option>
                                    <option value="damaged">⚠️ Kembali Kondisi Rusak</option>
                                    <option value="lost">❌ Hilang / Tidak Kembali</option>
                                </select>
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:6px;">
                                <div>
                                    <label style="font-size:11px;margin:2px 0;">Kondisi Fisik</label>
                                    <input name="condition_in[<?= (int)$it['id'] ?>]" value="Normal / Baik" placeholder="Kondisi" style="padding:6px;font-size:12px;">
                                </div>
                                <div>
                                    <label style="font-size:11px;margin:2px 0;">Catatan Unit</label>
                                    <input name="notes_in[<?= (int)$it['id'] ?>]" value="" placeholder="Keterangan..." style="padding:6px;font-size:12px;">
                                </div>
                            </div>

                            <div style="margin-top:8px;padding-top:8px;border-top:1px dashed #334155;">
                                <div style="font-size:10px;color:#64748b;margin-bottom:4px;display:flex;justify-content:space-between;align-items:center;">
                                    <span>📷 Foto Unit Saat Dikembalikan</span>
                                    <span style="font-size:10px;color:#64748b;">(Kamera HP)</span>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                    <label for="photo_in_<?= (int)$it['id'] ?>" class="btn btn-secondary" style="margin:0;padding:6px 10px;font-size:11px;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
                                        <span>📸 Ambil / Pilih Foto</span>
                                    </label>
                                    <input type="file" id="photo_in_<?= (int)$it['id'] ?>" name="photo_in_<?= (int)$it['id'] ?>" accept="image/*" capture="environment" style="display:none;" onchange="previewItemPhoto(this, 'preview_in_<?= (int)$it['id'] ?>')">
                                    <div id="preview_in_<?= (int)$it['id'] ?>" style="display:flex;align-items:center;gap:6px;"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top:10px;">
                    <label>Catatan Petugas Pemeriksa</label>
                    <textarea name="officer_notes" placeholder="Catatan kelengkapan saat pengembalian diterima..." style="min-height:70px;"></textarea>
                </div>

                <button type="submit" class="btn btn-success" style="padding:14px;font-size:15px;font-weight:700;margin-top:14px;" onclick="return confirm('Konfirmasi pencatatan pengembalian unit aset ini?')">
                    ✅ Konfirmasi Pengembalian Unit
                </button>
                <a href="<?= route_url('mobile_asset_loans') ?>" class="btn btn-secondary" style="margin-top:6px;">
                    Batal
                </a>
            </form>
        </div>

        <script>
        function previewItemPhoto(input, targetId) {
            var target = document.getElementById(targetId);
            if (!target) return;
            target.innerHTML = "";
            if (input.files && input.files[0]) {
                var file = input.files[0];
                var reader = new FileReader();
                reader.onload = function(e) {
                    target.innerHTML = 
                        "<div style='position:relative;display:inline-block;'>"
                        + "  <img src='" + e.target.result + "' style='width:48px;height:48px;object-fit:cover;border-radius:6px;border:2px solid #38bdf8;display:block;'>"
                        + "  <button type='button' onclick='clearItemPhoto(\"" + input.id + "\", \"" + targetId + "\")' style='position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;border:none;border-radius:50%;width:18px;height:18px;font-size:11px;font-weight:bold;line-height:18px;padding:0;cursor:pointer;'>✕</button>"
                        + "</div>"
                        + "<span style='font-size:10px;color:#0284c7;font-weight:600;'>Foto Siap</span>";
                };
                reader.readAsDataURL(file);
            }
        }

        function clearItemPhoto(inputId, targetId) {
            var input = document.getElementById(inputId);
            if (input) input.value = "";
            var target = document.getElementById(targetId);
            if (target) target.innerHTML = "";
        }
        </script>
        <?php
        render_mobile_footer('return');
        return;
    }

    // Jika tanpa ID: Tampilkan Scanner Kamera QR untuk cari transaksi peminjaman aktif!
    $activeLoans = $pdo->query("SELECT al.id, al.loan_code, al.borrower_name, al.borrower_department, al.loan_date, al.expected_return_date,
        (SELECT COUNT(*) FROM asset_loan_items WHERE loan_id = al.id AND status = 'borrowed') AS borrowed_items
        FROM asset_loans al
        WHERE al.status = 'active'
        ORDER BY al.id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);

    render_mobile_header('Scan Pengembalian Aset', $user);
    ?>
    <div class="panel">
        <h2 style="display:flex;align-items:center;gap:6px;">
            <span>🔄</span> Pengembalian Cepat Unit Aset
        </h2>
        <p style="font-size:12px;color:#64748b;margin:0 0 10px 0;">
            Scan stiker QR code pada unit alat fisik yang dikembalikan untuk langsung memproses verifikasinya.
        </p>

        <!-- Live Camera Scanner Box -->
        <div style="background:#f1f5f9;border:1px solid #0284c7;border-radius:8px;padding:12px;text-align:center;">
            <div id="reader" style="width:100%;max-width:280px;margin:0 auto;border-radius:8px;overflow:hidden;background:#000;"></div>
            <p id="camStatus" style="color:#0284c7;font-size:12px;margin:8px 0 4px 0;">Kamera scanner live QR aktif</p>
            <div id="scanReturnFeedback" style="display:none;margin-top:8px;padding:8px 10px;border-radius:6px;font-size:12px;font-weight:600;text-align:left;"></div>
        </div>

        <!-- Manual input fallback -->
        <div style="display:flex;gap:6px;margin-top:10px;">
            <input type="text" id="manualCodeInput" placeholder="Atau ketik kode unit (contoh: IT-CMP-000001)..." style="font-family:monospace;font-size:13px;" onkeydown="if(event.key==='Enter'){event.preventDefault();lookupLoanForReturn(this.value);}">
            <button type="button" class="btn btn-primary" onclick="lookupLoanForReturn(document.getElementById('manualCodeInput').value)" style="width:auto;margin:0;padding:8px 14px;white-space:nowrap;font-size:13px;">
                Cari
            </button>
        </div>
    </div>

    <!-- Atau Pilih dari Transaksi Aktif -->
    <div style="margin-top:14px;">
        <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:8px;">
            Atau Pilih Transaksi Dipinjam (<?= count($activeLoans) ?>):
        </div>

        <?php if (empty($activeLoans)): ?>
            <div class="panel" style="text-align:center;padding:24px 14px;color:#64748b;font-size:13px;">
                Tidak ada peminjaman aktif saat ini. Semua alat telah dikembalikan.
            </div>
        <?php else: ?>
            <?php foreach ($activeLoans as $al): ?>
                <div class="panel" style="padding:12px;margin-bottom:8px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                        <div>
                            <div style="font-family:monospace;font-weight:800;color:#0284c7;font-size:13px;">
                                <?= e($al['loan_code']) ?>
                            </div>
                            <div style="font-size:14px;font-weight:700;;margin-top:2px;">
                                <?= e($al['borrower_name']) ?>
                            </div>
                            <div style="font-size:11px;color:#64748b;">
                                <?= e($al['borrower_department'] ?: '-') ?> &bull; <?= (int)$al['borrowed_items'] ?> unit
                            </div>
                        </div>
                        <a href="<?= route_url('mobile_asset_loan_return', ['id' => $al['id']]) ?>" class="btn btn-success" style="width:auto;margin:0;padding:6px 12px;font-size:12px;">
                            Kembalikan →
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
    var html5QrCode = null;

    function showReturnFeedback(msg, type) {
        var fb = document.getElementById("scanReturnFeedback");
        if (!fb) return;
        fb.style.display = "block";
        fb.textContent = msg;
        if (type === "success") {
            fb.style.background = "#065f46";
            fb.style.color = "#d1fae5";
            fb.style.border = "1px solid #047857";
        } else if (type === "error") {
            fb.style.background = "#881337";
            fb.style.color = "#ffe4e6";
            fb.style.border = "1px solid #be123c";
        } else {
            fb.style.background = "#1e3a8a";
            fb.style.color = "#dbeafe";
            fb.style.border = "1px solid #3b82f6";
        }
    }

    function lookupLoanForReturn(code) {
        code = (code || "").trim();
        if (!code) {
            showReturnFeedback("Masukkan atau scan kode unit aset terlebih dahulu.", "error");
            return;
        }

        showReturnFeedback("Memeriksa status peminjaman unit: " + code + "...", "info");
        var url = "index.php?route=api_lookup_asset_for_loan&code=" + encodeURIComponent(code);

        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(resp) {
                if (!resp || !resp.ok || !resp.found) {
                    showReturnFeedback(resp && resp.message ? resp.message : "Unit tidak ditemukan.", "error");
                    return;
                }
                var data = resp.data;
                if (data.status === "borrowed" && data.loan_info && data.loan_info.loan_id) {
                    showReturnFeedback("✓ Ditemukan! Unit " + data.asset_code + " sedang dipinjam oleh " + (data.loan_info.borrower_name || "karyawan") + ". Membuka form pengembalian...", "success");
                    setTimeout(function() {
                        window.location.href = "index.php?route=mobile_asset_loan_return&id=" + data.loan_info.loan_id;
                    }, 800);
                } else {
                    showReturnFeedback("ℹ Unit " + data.asset_code + " tidak sedang dalam status dipinjam (Status: " + data.status + ").", "error");
                }
            })
            .catch(function(err) {
                showReturnFeedback("Gagal memeriksa unit: " + err.message, "error");
            });
    }

    function stopLiveReturnScanner() {
        if (html5QrCode) {
            try {
                html5QrCode.stop().then(function() {
                    html5QrCode.clear();
                }).catch(function() {});
            } catch(e) {}
        }
    }

    function startLiveReturnScanner() {
        var camStatus = document.getElementById("camStatus");
        camStatus.textContent = "Kamera scanner live QR aktif";
        html5QrCode = new Html5Qrcode("reader");
        var config = { fps: 10, qrbox: { width: 220, height: 220 } };
        html5QrCode.start({ facingMode: "environment" }, config, function(decodedText) {
            camStatus.textContent = "QR Terbaca: " + decodedText;
            stopLiveReturnScanner(); // Otomatis keluar dari foto scan QR setelah berhasil membaca kode
            lookupLoanForReturn(decodedText);
        }).catch(function(err) {
            camStatus.textContent = "Kamera tidak aktif (" + err + "). Silakan gunakan input kode manual di bawah.";
        });
    }

    startLiveReturnScanner();
    </script>
    <?php
    render_mobile_footer('return');
}

/**
 * 4. Detail Peminjaman Mobile
 */
/**
 * Modul Mobile Loan: Detail Peminjaman
 * Proses: Menampilkan rincian lengkap sebuah transaksi peminjaman pada layar mobile.
 */
function handle_route_mobile_asset_loan_detail(PDO $pdo): void
{
    $user = require_mobile_loan_user();
    $id = (int)($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT al.*, u1.name AS officer_name, u2.name AS return_officer_name
        FROM asset_loans al
        LEFT JOIN users u1 ON u1.id = al.officer_user_id
        LEFT JOIN users u2 ON u2.id = al.return_officer_user_id
        WHERE al.id = ?");
    $stmt->execute([$id]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan) {
        flash('Data peminjaman tidak ditemukan.', 'err');
        redirect_to('mobile_asset_loans');
    }

    $itemStmt = $pdo->prepare("SELECT ali.*, ai.asset_code, ai.asset_name, ai.brand, ai.model, g.group_name, COALESCE(t.type_name, ai.asset_type, '-') AS type_name
        FROM asset_loan_items ali
        JOIN asset_items ai ON ai.id = ali.asset_item_id
        LEFT JOIN asset_groups g ON g.id = ai.asset_group_id
        LEFT JOIN asset_types t ON t.id = ai.asset_type_id
        WHERE ali.loan_id = ?
        ORDER BY ali.id ASC");
    $itemStmt->execute([$id]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    $isOverdue = ($loan['status'] === 'active' && !empty($loan['expected_return_date']) && $loan['expected_return_date'] < date('Y-m-d'));

    render_mobile_header('Detail Pinjaman', $user);
    ?>
    <div class="panel">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
                <div style="font-family:monospace;font-size:17px;font-weight:800;color:#0284c7;">
                    <?= e($loan['loan_code']) ?>
                </div>
                <div style="font-size:16px;font-weight:700;;margin-top:2px;">
                    <?= e($loan['borrower_name']) ?>
                </div>
                <div style="font-size:12px;color:#64748b;">
                    <?= e($loan['borrower_department'] ?: '-') ?> &bull; NIK: <?= e($loan['borrower_nik'] ?: '-') ?>
                </div>
            </div>
            <div>
                <?php if ($loan['status'] === 'returned'): ?>
                    <span class="badge badge-returned">Sudah Kembali</span>
                <?php elseif ($isOverdue): ?>
                    <span class="badge badge-overdue">Terlambat</span>
                <?php else: ?>
                    <span class="badge badge-active">Sedang Dipinjam</span>
                <?php endif; ?>
            </div>
        </div>

        <div style="margin-top:12px;padding:10px;background:#f1f5f9;border-radius:8px;font-size:12px;display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <div>
                <span style="color:#64748b;">Tanggal Pinjam:</span><br>
                <strong style="color:#64748b;"><?= date('d/m/Y', strtotime($loan['loan_date'])) ?></strong>
            </div>
            <div>
                <span style="color:#64748b;">Batas Kembali:</span><br>
                <strong style="color:<?= $isOverdue ? '#f87171' : '#cbd5e1' ?>;"><?= !empty($loan['expected_return_date']) ? date('d/m/Y', strtotime($loan['expected_return_date'])) : '-' ?></strong>
            </div>
            <div>
                <span style="color:#64748b;">Petugas Serah:</span><br>
                <strong style="color:#64748b;"><?= e($loan['officer_name'] ?: '-') ?></strong>
            </div>
            <div>
                <span style="color:#64748b;">Petugas Terima:</span><br>
                <strong style="color:#64748b;"><?= e($loan['return_officer_name'] ?: '-') ?></strong>
            </div>
        </div>

        <?php if (!empty($loan['purpose'])): ?>
            <div style="margin-top:10px;font-size:12px;color:#64748b;background:#f1f5f9;padding:8px 10px;border-radius:6px;">
                <span style="color:#64748b;font-weight:700;">Keperluan:</span> <?= nl2br(e($loan['purpose'])) ?>
            </div>
        <?php endif; ?>

        <!-- List Items -->
        <div style="margin-top:14px;">
            <div style="font-size:12px;font-weight:700;color:#0284c7;text-transform:uppercase;margin-bottom:8px;">
                Daftar Unit Aset Fisik (<?= count($items) ?>):
            </div>
            <?php foreach ($items as $it): ?>
                <div style="background:#f1f5f9;;border-radius:8px;padding:10px;margin-bottom:8px;font-size:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                        <div>
                            <div style="font-family:monospace;font-weight:800;color:#0284c7;"><?= e($it['asset_code']) ?></div>
                            <div style="font-weight:700;;font-size:13px;"><?= e($it['asset_name']) ?></div>
                            <div style="color:#64748b;font-size:11px;"><?= e($it['brand']) ?> <?= e($it['model']) ?></div>
                        </div>
                        <div>
                            <?php if ($it['status'] === 'returned'): ?>
                                <span class="badge badge-returned">Kembali</span>
                            <?php elseif ($it['status'] === 'damaged'): ?>
                                <span class="badge" style="background:#ea580c;color:#fff;">Rusak</span>
                            <?php elseif ($it['status'] === 'lost'): ?>
                                <span class="badge" style="background:#dc2626;color:#fff;">Hilang</span>
                            <?php else: ?>
                                <span class="badge badge-active">Dipinjam</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="margin-top:6px;font-size:11px;color:#64748b;">
                        Awal: <em><?= e($it['condition_out'] ?: 'Normal') ?></em>
                        <?php if (!empty($it['condition_in'])): ?>
                            &bull; Kembali: <em style=";"><?= e($it['condition_in']) ?></em>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($it['photo_out']) || !empty($it['photo_in'])): ?>
                        <div style="margin-top:8px;padding-top:8px;border-top:1px dashed #334155;display:flex;gap:12px;flex-wrap:wrap;">
                            <?php if (!empty($it['photo_out'])): ?>
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <a href="<?= e($it['photo_out']) ?>" target="_blank">
                                        <img src="<?= e($it['photo_out']) ?>" alt="Foto Pinjam" style="width:50px;height:50px;object-fit:cover;border-radius:6px;border:1px solid #475569;">
                                    </a>
                                    <div style="font-size:10px;line-height:1.3;">
                                        <span style="color:#0284c7;font-weight:600;">Foto Pinjam</span><br>
                                        <a href="<?= e($it['photo_out']) ?>" target="_blank" style="color:#64748b;">Lihat ↗</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($it['photo_in'])): ?>
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <a href="<?= e($it['photo_in']) ?>" target="_blank">
                                        <img src="<?= e($it['photo_in']) ?>" alt="Foto Kembali" style="width:50px;height:50px;object-fit:cover;border-radius:6px;border:1px solid #475569;">
                                    </a>
                                    <div style="font-size:10px;line-height:1.3;">
                                        <span style="color:#4ade80;font-weight:600;">Foto Kembali</span><br>
                                        <a href="<?= e($it['photo_in']) ?>" target="_blank" style="color:#64748b;">Lihat ↗</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top:14px;display:flex;gap:6px;">
            <?php if ($loan['status'] === 'active'): ?>
                <a href="<?= route_url('mobile_asset_loan_return', ['id' => $loan['id']]) ?>" class="btn btn-success" style="flex:1;margin:0;padding:10px;font-size:13px;">
                    <span>🔄</span> Proses Pengembalian
                </a>
            <?php endif; ?>
            <a href="<?= route_url('asset_loan_detail', ['id' => $loan['id']]) ?>" target="_blank" class="btn btn-secondary" style="flex:1;margin:0;padding:10px;font-size:13px;">
                <span>🖨️</span> Cetak Surat
            </a>
        </div>
        <a href="<?= route_url('mobile_asset_loans') ?>" class="btn btn-secondary" style="margin-top:8px;">
            ⬅️ Kembali ke Daftar
        </a>
    </div>
    <?php
    render_mobile_footer('dashboard');
}

