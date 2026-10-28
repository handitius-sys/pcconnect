<?php

declare(strict_types=1);

/**
 * Modul Setup Regulasi Hak Akses Per Role & Pengaturan Urutan Menu Side Slider (`?route=setup_regulations`).
 *
 * [PROSES & FITUR TERUPDATE]:
 * 1. Mengontrol izin granular 4-Aksi: Lihat (View / Read-Only), Tambah (Create), Ubah (Edit), dan Hapus/Batal (Delete/Cancel)
 *    untuk seluruh modul sistem AsetConnect:
 *    - Transaksi Aset: Unit Aset, Import Aset Legacy (Data Lama), Peminjaman Aset, Mutasi Aset.
 *    - Maintenance Aset: Preventive Maintenance, Aset Corrective, Mobile Field Service (Scan & Direct).
 *    - Reports: Report PM, Report Corrective, Report Mutasi, Report Peminjaman, Report Unit Aset.
 *    - Data Master: Pendataan PC, Komoditas, Bengkel/Workshop, Kategori, Jobdesk PM & Corrective, Brand, Master Barang, Identifier, Spesifikasi, Lokasi, Company, Pengguna.
 *    - Pengaturan & Setup: QR Label Unit, Akses Users.
 * 2. Mengontrol waktu Auto Sign-Out (Idle Timeout) per role melalui tabel `role_settings`.
 * 3. Mengontrol urutan tampilan kelompok (accordion) dan sub-menu pada side slider (`system_menu_orders`).
 * 4. Mendukung Reset Regulasi ke Default (`action=reset_default`) yang menyinkronkan kembali izin ke nilai bawaan via `get_default_role_regulation()`.
 * 5. Integrasi penuh dengan fitur pencarian Kode Baru & Kode Lama (Legacy Code / Alias) dan Mode Combobox interaktif.
 */
function handle_route_setup_regulations(PDO $pdo): void
{
    $user = require_role(['admin']);
    ensure_role_regulations_schema($pdo);
    ensure_menu_orders_schema($pdo);

    $currentTab = (string)($_GET['tab'] ?? 'matrix');
    if (!in_array($currentTab, ['matrix', 'menu_order'], true)) {
        $currentTab = 'matrix';
    }

    $roles = [
        'admin' => 'Administrator Full',
        'maintenance_admin' => 'Admin Maintenance',
        'technician' => 'Teknisi Preventive',
        'corrective_maintenance' => 'Corrective Maintenance',
        'loan_officer' => 'Petugas Peminjaman Aset',
    ];

    $selectedRole = (string)($_GET['role'] ?? $_POST['role'] ?? 'maintenance_admin');
    if (!isset($roles[$selectedRole])) {
        $selectedRole = 'maintenance_admin';
    }

    $menus = get_regulated_menus();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string)($_POST['action'] ?? 'save');

        if ($action === 'save_menu_orders') {
            $orders = (array)($_POST['menu_orders'] ?? []);
            save_sidebar_menu_orders($pdo, $orders);
            flash('Pengaturan urutan menu side slider (sidebar navigasi) berhasil disimpan.');
            redirect_to('setup_regulations', ['tab' => 'menu_order']);
        }

        if ($action === 'reset_menu_orders') {
            reset_sidebar_menu_orders($pdo);
            flash('Urutan menu side slider berhasil dikembalikan ke urutan default sistem.');
            redirect_to('setup_regulations', ['tab' => 'menu_order']);
        }

        if ($action === 'reset_default') {
            try {
                $pdo->prepare('DELETE FROM role_regulations WHERE role = ?')->execute([$selectedRole]);
                $pdo->prepare('INSERT INTO role_settings (role, idle_timeout_minutes, updated_at) VALUES (?, 60, NOW()) ON DUPLICATE KEY UPDATE idle_timeout_minutes = 60, updated_at = NOW()')->execute([$selectedRole]);
                if (function_exists('get_default_role_regulation')) {
                    $stmtReIns = $pdo->prepare('INSERT IGNORE INTO role_regulations (role, menu_key, can_view, can_create, can_edit, can_delete, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
                    foreach ($menus as $grp => $grpItems) {
                        foreach ($grpItems as $mKey => $mLbl) {
                            $def = get_default_role_regulation($selectedRole, $mKey);
                            $stmtReIns->execute([
                                $selectedRole,
                                $mKey,
                                (int)($def['view'] ?? 0),
                                (int)($def['create'] ?? 0),
                                (int)($def['edit'] ?? 0),
                                (int)($def['delete'] ?? 0),
                            ]);
                        }
                    }
                } else {
                    ensure_role_regulations_schema($pdo);
                }
                clear_role_regulations_cache();
                flash('Regulasi hak akses dan waktu auto sign-out untuk role ' . e($roles[$selectedRole]) . ' berhasil direset ke pengaturan bawaan terbaru (60 menit).');
            } catch (Throwable $e) {
                flash('Gagal mereset regulasi: ' . $e->getMessage(), 'err');
            }
            redirect_to('setup_regulations', ['tab' => 'matrix', 'role' => $selectedRole]);
        }

        if ($action === 'save') {
            $submitted = (array)($_POST['reg'] ?? []);
            $timeoutMinutes = max(1, min(10080, (int)($_POST['idle_timeout_minutes'] ?? 60)));

            $pdo->beginTransaction();
            try {
                $stmtSet = $pdo->prepare('INSERT INTO role_settings (role, idle_timeout_minutes, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE idle_timeout_minutes = VALUES(idle_timeout_minutes), updated_at = NOW()');
                $stmtSet->execute([$selectedRole, $timeoutMinutes]);

                if ($selectedRole !== 'admin') {
                    $stmtDel = $pdo->prepare('DELETE FROM role_regulations WHERE role = ?');
                    $stmtDel->execute([$selectedRole]);

                    $stmtIns = $pdo->prepare('INSERT INTO role_regulations (role, menu_key, can_view, can_create, can_edit, can_delete, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');

                    foreach ($menus as $group => $items) {
                        foreach ($items as $mKey => $mLabel) {
                            $v = !empty($submitted[$mKey]['view']) ? 1 : 0;
                            $c = !empty($submitted[$mKey]['create']) ? 1 : 0;
                            $e = !empty($submitted[$mKey]['edit']) ? 1 : 0;
                            $d = !empty($submitted[$mKey]['delete']) ? 1 : 0;

                            if ($c || $e || $d) {
                                $v = 1;
                            }

                            $stmtIns->execute([$selectedRole, $mKey, $v, $c, $e, $d]);
                        }
                    }
                }
                $pdo->commit();
                clear_role_regulations_cache();
                flash('Pengaturan role ' . e($roles[$selectedRole]) . ' (hak akses & auto sign-out: ' . $timeoutMinutes . ' menit) berhasil disimpan dan langsung aktif.');
            } catch (Throwable $e) {
                $pdo->rollBack();
                flash('Gagal menyimpan regulasi: ' . $e->getMessage(), 'err');
            }
            redirect_to('setup_regulations', ['tab' => 'matrix', 'role' => $selectedRole]);
        }
    }

    // Load current regulations for selected role
    $stmtCur = $pdo->prepare('SELECT menu_key, can_view, can_create, can_edit, can_delete FROM role_regulations WHERE role = ?');
    $stmtCur->execute([$selectedRole]);
    $curData = [];
    while ($r = $stmtCur->fetch(PDO::FETCH_ASSOC)) {
        $curData[$r['menu_key']] = $r;
    }
    $curTimeoutMinutes = function_exists('get_role_idle_timeout_minutes') ? get_role_idle_timeout_minutes($selectedRole, $pdo) : 60;

    render_header('Regulasi Hak Akses', $user);
    ?>

    <section class="panel">
        <div class="split" style="align-items:flex-start;">
            <div>
                <h1 style="display:flex;align-items:center;gap:10px;margin:0 0 6px 0;">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>
                    Regulasi Hak Akses & Urutan Menu
                </h1>
                <p class="muted" style="margin:0;">Kelola izin akses pengguna (View, Create, Edit, Delete) dan atur urutan tampilan menu pada side slider (sidebar navigasi).</p>
            </div>
            <div class="actions" style="display:flex;gap:8px;">
                <a class="btn" href="<?= route_url('users') ?>">Kelola Pengguna</a>
                <a class="btn" href="<?= route_url('dashboard') ?>">← Batal / Kembali</a>
            </div>
        </div>

        <!-- Tab Utama: Matriks vs Urutan Menu Sidebar -->
        <div style="display:flex;gap:10px;margin-top:20px;border-bottom:2px solid #e2e8f0;padding-bottom:1px;">
            <a href="<?= route_url('setup_regulations', ['tab' => 'matrix', 'role' => $selectedRole]) ?>" class="btn <?= $currentTab === 'matrix' ? 'primary' : '' ?>" style="padding:10px 20px;font-weight:700;font-size:14px;border-radius:8px 8px 0 0;margin-bottom:-2px;border-bottom:none;">
                🛡️ Matriks Izin Role (Hak Akses)
            </a>
            <a href="<?= route_url('setup_regulations', ['tab' => 'menu_order']) ?>" class="btn <?= $currentTab === 'menu_order' ? 'primary' : '' ?>" style="padding:10px 20px;font-weight:700;font-size:14px;border-radius:8px 8px 0 0;margin-bottom:-2px;border-bottom:none;">
                ↕️ Pengaturan Urutan Menu Side Slider
            </a>
        </div>
    </section>

    <?php if ($currentTab === 'menu_order'): ?>
        <?php $menuStructure = get_sidebar_menu_orders($pdo); ?>
        <form method="post" id="formMenuOrders">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="save_menu_orders" id="actionMenuOrder">

            <section class="panel">
                <div class="split" style="align-items:center;margin-bottom:16px;">
                    <div>
                        <h2 style="margin:0;">Urutan Menu & Accordion Side Slider</h2>
                        <p class="muted" style="margin:4px 0 0 0;">Ubah nomor urut menu di bawah ini. Angka yang lebih kecil akan tampil lebih atas pada sidebar navigasi. Anda juga dapat menggunakan tombol panah ▲ dan ▼ untuk menggeser urutan secara visual.</p>
                    </div>
                    <div class="actions" style="display:flex;gap:8px;">
                        <button type="submit" class="btn primary" style="padding:10px 20px;font-weight:700;">💾 Simpan Urutan Menu</button>
                        <button type="button" class="btn danger" onclick="confirmResetMenuOrders()">↺ Kembalikan ke Bawaan</button>
                    </div>
                </div>

                <div style="display:flex;flex-direction:column;gap:20px;">
                    <?php foreach ($menuStructure as $gKey => $group): ?>
                        <div style="border:1.5px solid #cbd5e1;border-radius:10px;overflow:hidden;background:#fff;box-shadow:0 2px 4px rgba(0,0,0,0.03);">
                            <!-- Header Kelompok / Accordion -->
                            <div style="background:#f1f5f9;padding:12px 18px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #cbd5e1;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span style="font-size:18px;">📁</span>
                                    <strong style="font-size:15px;color:#0f172a;"><?= e($group['label']) ?> (Accordion Kelompok)</strong>
                                    <span class="badge" style="background:#e2e8f0;color:#334155;font-size:11px;">Key: <?= e($gKey) ?></span>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span style="font-size:12px;font-weight:600;color:#64748b;">Urutan Kelompok:</span>
                                    <input type="number" name="menu_orders[<?= e($gKey) ?>]" value="<?= (int)($group['order'] ?? 10) ?>" min="1" max="999" style="width:70px;padding:4px 8px;font-weight:700;text-align:center;border:1px solid #94a3b8;border-radius:6px;">
                                </div>
                            </div>

                            <!-- Tabel Item Sub-menu -->
                            <div style="padding:0;">
                                <table style="width:100%;margin:0;border:none;">
                                    <thead>
                                        <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                                            <th style="width:50px;text-align:center;">No. Urut</th>
                                            <th style="width:100px;text-align:center;">Geser</th>
                                            <th>Nama Sub-menu Navigasi</th>
                                            <th style="width:180px;">Rute / Key</th>
                                            <th style="width:160px;">Izin Hak Akses</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-<?= e($gKey) ?>">
                                        <?php foreach ($group['items'] as $itemKey => $item): ?>
                                            <tr style="border-bottom:1px solid #f1f5f9;">
                                                <td style="text-align:center;">
                                                    <input type="number" name="menu_orders[<?= e($itemKey) ?>]" value="<?= (int)($item['order'] ?? 10) ?>" min="1" max="999" class="item-order-input" style="width:65px;padding:6px;font-weight:700;text-align:center;border:1.5px solid #0284c7;border-radius:6px;color:#0369a1;">
                                                </td>
                                                <td style="text-align:center;">
                                                    <div style="display:inline-flex;gap:4px;">
                                                        <button type="button" class="btn" onclick="moveRowOrder(this, -5)" title="Pindahkan ke atas" style="padding:4px 8px;font-size:12px;line-height:1;">▲</button>
                                                        <button type="button" class="btn" onclick="moveRowOrder(this, 5)" title="Pindahkan ke bawah" style="padding:4px 8px;font-size:12px;line-height:1;">▼</button>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong><?= e($item['label']) ?></strong>
                                                </td>
                                                <td>
                                                    <code style="font-size:12px;background:#f1f5f9;padding:2px 6px;border-radius:4px;color:#475569;"><?= e($itemKey) ?></code>
                                                </td>
                                                <td>
                                                    <?php if (($item['reg'] ?? '') === 'admin_only'): ?>
                                                        <span class="badge danger" style="font-size:11px;">Admin Saja</span>
                                                    <?php else: ?>
                                                        <span class="badge ok" style="font-size:11px;">Regulasi: <?= e($item['reg'] ?? '-') ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="split" style="margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0;align-items:center;">
                    <div style="display:flex;gap:12px;align-items:center;">
                        <button type="submit" class="btn primary" style="padding:10px 24px;font-size:14px;font-weight:700;">💾 Simpan Seluruh Urutan Menu</button>
                        <a href="<?= route_url('setup_regulations', ['tab' => 'menu_order']) ?>" class="btn">Batal</a>
                    </div>
                    <div>
                        <button type="button" class="btn danger" onclick="confirmResetMenuOrders()" style="font-size:12px;">↺ Reset Seluruh Urutan ke Bawaan</button>
                    </div>
                </div>
            </section>
        </form>

        <script>
        function moveRowOrder(btn, delta) {
            var tr = btn.closest('tr');
            if (!tr) return;
            var inp = tr.querySelector('.item-order-input');
            if (!inp) return;
            var curVal = parseInt(inp.value, 10) || 10;
            var newVal = Math.max(1, curVal + delta);
            inp.value = newVal;

            // Highlight baris yang diubah
            tr.style.backgroundColor = '#ecfdf5';
            setTimeout(function() { tr.style.backgroundColor = ''; }, 1500);
        }

        function confirmResetMenuOrders() {
            if (confirm('Kembalikan seluruh urutan kelompok dan sub-menu side slider ke urutan bawaan sistem?')) {
                document.getElementById('actionMenuOrder').value = 'reset_menu_orders';
                document.getElementById('formMenuOrders').submit();
            }
        }
        </script>

    <?php else: ?>
        <!-- Tab Matriks Izin Role -->
        <section class="panel">
            <!-- Tab Role Selector -->
            <div style="display:flex;gap:8px;flex-wrap:wrap;border-bottom:1px solid #e2e8f0;padding-bottom:12px;">
                <?php foreach ($roles as $rKey => $rLabel): ?>
                    <?php $isSelected = $rKey === $selectedRole; ?>
                    <a href="<?= route_url('setup_regulations', ['tab' => 'matrix', 'role' => $rKey]) ?>" class="btn <?= $isSelected ? 'primary' : '' ?>" style="padding:8px 16px;font-weight:600;font-size:13px;border-radius:8px;">
                        <?= e($rLabel) ?>
                        <?php if ($rKey === 'admin'): ?>
                            <span style="font-size:10px;background:rgba(255,255,255,.25);padding:1px 6px;border-radius:999px;margin-left:4px;">Super</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <form method="post" id="formRegulasi">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="role" value="<?= e($selectedRole) ?>">
            <input type="hidden" name="action" value="save" id="formAction">

            <section class="panel">
                <!-- Pengaturan Waktu Auto Sign-Out Per Role -->
                <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:16px 20px; margin-bottom:24px; box-shadow:0 1px 2px rgba(0,0,0,0.03);">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                        <div style="max-width:550px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="font-size:18px;">⏱️</span>
                                <strong style="font-size:15px; color:#0f172a;">Waktu Auto Sign Out (Idle Timeout)</strong>
                            </div>
                            <p class="muted" style="margin:4px 0 0 0; font-size:13px; line-height:1.4;">
                                Sesi pengguna dengan role <strong><?= e($roles[$selectedRole]) ?></strong> akan otomatis sign out jika tidak ada aktivitas selama durasi ini.
                            </p>
                        </div>
                        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <input type="number" id="idleTimeoutInput" name="idle_timeout_minutes" min="1" max="10080" value="<?= (int)$curTimeoutMinutes ?>" style="width:90px; padding:8px 10px; font-size:16px; font-weight:700; border:1px solid #94a3b8; border-radius:8px; text-align:center;" required>
                                <span style="font-weight:600; font-size:14px; color:#334155;">Menit</span>
                                <span id="timeoutHumanDisplay" style="font-size:12px; font-weight:700; color:#1d4ed8; background:#eff6ff; border:1px solid #bfdbfe; padding:4px 10px; border-radius:20px; min-width:80px; text-align:center;"></span>
                            </div>
                            <div style="display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end;">
                                <button type="button" class="btn" style="padding:2px 8px; font-size:11px;" onclick="setTimeoutPreset(15)">15 mnt</button>
                                <button type="button" class="btn" style="padding:2px 8px; font-size:11px;" onclick="setTimeoutPreset(30)">30 mnt</button>
                                <button type="button" class="btn" style="padding:2px 8px; font-size:11px;" onclick="setTimeoutPreset(60)">1 Jam</button>
                                <button type="button" class="btn" style="padding:2px 8px; font-size:11px;" onclick="setTimeoutPreset(120)">2 Jam</button>
                                <button type="button" class="btn" style="padding:2px 8px; font-size:11px;" onclick="setTimeoutPreset(240)">4 Jam</button>
                                <button type="button" class="btn" style="padding:2px 8px; font-size:11px;" onclick="setTimeoutPreset(480)">8 Jam</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="split" style="margin-bottom:16px;">
                    <div>
                        <h2 style="margin:0;">Matriks Izin Role: <span style="color:#2563eb;"><?= e($roles[$selectedRole]) ?></span></h2>
                        <?php if ($selectedRole === 'admin'): ?>
                            <p class="muted" style="margin:4px 0 0 0;">Role Administrator memiliki hak akses penuh otomatis ke seluruh menu dan fungsi.</p>
                        <?php else: ?>
                            <p class="muted" style="margin:4px 0 0 0;">Centang fitur yang diizinkan untuk diakses, ditambah, diubah, atau dihapus/dibatalkan oleh role ini.</p>
                        <?php endif; ?>
                    </div>
                    <?php if ($selectedRole !== 'admin'): ?>
                        <div class="actions" style="display:flex;gap:6px;flex-wrap:wrap;">
                            <button type="button" class="btn" onclick="checkAll(true)">Pilih Semua</button>
                            <button type="button" class="btn" onclick="checkAll(false)">Batalkan Semua</button>
                            <button type="button" class="btn" onclick="checkColumn('create', true)">+ Centang Semua Tambah</button>
                            <button type="button" class="btn" onclick="checkColumn('edit', true)">✏️ Centang Semua Edit</button>
                            <button type="button" class="btn" onclick="checkColumn('delete', true)">🗑️ Centang Semua Hapus / Batal</button>
                            <a href="<?= route_url('setup_regulations', ['tab' => 'matrix', 'role' => $selectedRole]) ?>" class="btn">❌ Batal Edit</a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php $menuDescriptions = function_exists('get_regulated_menu_descriptions') ? get_regulated_menu_descriptions() : []; ?>
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 18px;margin-bottom:20px;font-size:12.5px;color:#1e3a8a;line-height:1.55;">
                    <div style="font-weight:700;color:#1e40af;margin-bottom:6px;font-size:13px;">📌 Panduan Cakupan Regulasi Hak Akses (Sistem AsetConnect Terupdate):</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:8px 18px;">
                        <div>• <strong>Lihat (View):</strong> Membuka menu/tabel, pencarian autocomplete / combobox (Kode Baru & Kode Lama), pengurutan kolom (sort), export Excel & PDF, serta membuka <strong>Mode View Detail (Read-Only)</strong> pada Unit Aset tanpa merubah data.</div>
                        <div>• <strong>Tambah (Create):</strong> Menambah unit aset baru (termasuk fitur Import Data Aset Lama via tombol di Unit Aset), buat Schedule PM, catat tiket perbaikan Corrective, buat transaksi Peminjaman & Mutasi, tambah data master, dan buat akun user.</div>
                        <div>• <strong>Ubah (Edit):</strong> Mengubah data aset & spesifikasi, mengeksekusi pengerjaan Preventive/Corrective (termasuk pengerjaan via Mobile Field Service & upload foto progres %), kelola relasi bundle, verifikasi/edit baris import data lama, dan proses pengembalian pinjaman.</div>
                        <div>• <strong>Hapus / Batal (Delete):</strong> Menghapus/restore unit aset, membatalkan jadwal Preventive Maintenance, membatalkan tiket Corrective, membatalkan transaksi mutasi/peminjaman, atau menghapus item master.</div>
                    </div>
                </div>

                <?php foreach ($menus as $groupName => $groupMenus): ?>
                    <div style="margin-top:24px;margin-bottom:12px;display:flex;align-items:center;gap:10px;">
                        <span style="font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#475569;background:#f1f5f9;padding:4px 12px;border-radius:6px;">
                            <?= e($groupName) ?>
                        </span>
                        <span class="muted" style="font-size:12px;">(<?= count($groupMenus) ?> Menu)</span>
                    </div>

                    <div style="overflow-x:auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:40%;">Nama Menu / Modul</th>
                                    <th style="width:15%;text-align:center;">Lihat (View)</th>
                                    <th style="width:15%;text-align:center;">Tambah (Create)</th>
                                    <th style="width:15%;text-align:center;">Ubah (Edit)</th>
                                    <th style="width:15%;text-align:center;">Hapus / Batal (Delete / Cancel)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupMenus as $mKey => $mLabel): ?>
                                    <?php
                                    $rRow = $curData[$mKey] ?? null;
                                    $defReg = function_exists('get_default_role_regulation')
                                        ? get_default_role_regulation($selectedRole, $mKey)
                                        : ['view' => 0, 'create' => 0, 'edit' => 0, 'delete' => 0];
                                    $canView = $selectedRole === 'admin' ? 1 : ($rRow !== null ? (!empty($rRow['can_view']) ? 1 : 0) : (int)($defReg['view'] ?? 0));
                                    $canCreate = $selectedRole === 'admin' ? 1 : ($rRow !== null ? (!empty($rRow['can_create']) ? 1 : 0) : (int)($defReg['create'] ?? 0));
                                    $canEdit = $selectedRole === 'admin' ? 1 : ($rRow !== null ? (!empty($rRow['can_edit']) ? 1 : 0) : (int)($defReg['edit'] ?? 0));
                                    $canDelete = $selectedRole === 'admin' ? 1 : ($rRow !== null ? (!empty($rRow['can_delete']) ? 1 : 0) : (int)($defReg['delete'] ?? 0));
                                    $isDisabled = $selectedRole === 'admin' ? 'disabled' : '';
                                    $mDesc = $menuDescriptions[$mKey] ?? '';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($mLabel) ?></strong>
                                            <?php if ($mDesc !== ''): ?>
                                                <div style="font-size:12px;color:#475569;margin-top:2px;line-height:1.35;"><?= e($mDesc) ?></div>
                                            <?php endif; ?>
                                            <div style="font-size:11px;color:#94a3b8;font-family:Consolas,monospace;margin-top:2px;">key: <?= e($mKey) ?></div>
                                        </td>
                                        <td style="text-align:center;">
                                            <input type="checkbox" name="reg[<?= e($mKey) ?>][view]" value="1" <?= $canView ? 'checked' : '' ?> <?= $isDisabled ?> class="chk-col-view chk-row-<?= e($mKey) ?>">
                                        </td>
                                        <td style="text-align:center;">
                                            <input type="checkbox" name="reg[<?= e($mKey) ?>][create]" value="1" <?= $canCreate ? 'checked' : '' ?> <?= $isDisabled ?> class="chk-col-create chk-row-<?= e($mKey) ?>" onchange="onActionTick('<?= e($mKey) ?>')">
                                        </td>
                                        <td style="text-align:center;">
                                            <input type="checkbox" name="reg[<?= e($mKey) ?>][edit]" value="1" <?= $canEdit ? 'checked' : '' ?> <?= $isDisabled ?> class="chk-col-edit chk-row-<?= e($mKey) ?>" onchange="onActionTick('<?= e($mKey) ?>')">
                                        </td>
                                        <td style="text-align:center;">
                                            <input type="checkbox" name="reg[<?= e($mKey) ?>][delete]" value="1" <?= $canDelete ? 'checked' : '' ?> <?= $isDisabled ?> class="chk-col-delete chk-row-<?= e($mKey) ?>" onchange="onActionTick('<?= e($mKey) ?>')">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>

                <div class="split" style="margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0;align-items:center;">
                    <div style="display:flex;gap:12px;align-items:center;">
                        <button type="submit" class="btn primary" style="padding:10px 24px;font-size:14px;">💾 Simpan Pengaturan Role <?= e($roles[$selectedRole]) ?></button>
                        <a href="<?= route_url('setup_regulations', ['tab' => 'matrix', 'role' => $selectedRole]) ?>" class="btn" style="padding:10px 20px;font-size:14px;">❌ Batal</a>
                    </div>
                    <div>
                        <?php if ($selectedRole !== 'admin'): ?>
                            <button type="button" class="btn danger" onclick="confirmReset()" style="font-size:12px;">↺ Reset Regulasi ke Default</button>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </form>

        <script>
        function updateTimeoutHuman(val) {
            val = parseInt(val, 10) || 0;
            var disp = document.getElementById('timeoutHumanDisplay');
            if (!disp) return;
            if (val <= 0) { disp.textContent = '-'; return; }
            if (val < 60) {
                disp.textContent = val + ' menit';
            } else if (val % 60 === 0) {
                disp.textContent = (val / 60) + ' jam';
            } else {
                var h = Math.floor(val / 60);
                var m = val % 60;
                disp.textContent = h + ' jam ' + m + ' mnt';
            }
        }

        function setTimeoutPreset(val) {
            var inp = document.getElementById('idleTimeoutInput');
            if (inp) {
                inp.value = val;
                updateTimeoutHuman(val);
            }
        }

        var timeoutInp = document.getElementById('idleTimeoutInput');
        if (timeoutInp) {
            timeoutInp.addEventListener('input', function() { updateTimeoutHuman(this.value); });
            updateTimeoutHuman(timeoutInp.value);
        }

        function checkAll(status) {
            document.querySelectorAll('#formRegulasi input[type=checkbox]').forEach(function(cb) {
                if (!cb.disabled) cb.checked = status;
            });
        }

        function checkColumn(col, status) {
            document.querySelectorAll('.chk-col-' + col).forEach(function(cb) {
                if (!cb.disabled) {
                    cb.checked = status;
                    if (status) {
                        var mKey = cb.className.match(/chk-row-([^\s]+)/);
                        if (mKey && mKey[1]) {
                            var vBox = document.querySelector('.chk-col-view.chk-row-' + mKey[1]);
                            if (vBox && !vBox.disabled) vBox.checked = true;
                        }
                    }
                }
            });
        }

        function onActionTick(mKey) {
            var vBox = document.querySelector('.chk-col-view.chk-row-' + mKey);
            var cBox = document.querySelector('.chk-col-create.chk-row-' + mKey);
            var eBox = document.querySelector('.chk-col-edit.chk-row-' + mKey);
            var dBox = document.querySelector('.chk-col-delete.chk-row-' + mKey);
            if (vBox && ((cBox && cBox.checked) || (eBox && eBox.checked) || (dBox && dBox.checked))) {
                vBox.checked = true;
            }
        }

        function confirmReset() {
            if (confirm('Yakin ingin mereset seluruh regulasi untuk role ini kembali ke pengaturan awal bawaan sistem?')) {
                document.getElementById('formAction').value = 'reset_default';
                document.getElementById('formRegulasi').submit();
            }
        }
        </script>
    <?php endif; ?>

    <?php
    render_footer();
}
