<?php

declare(strict_types=1);

function get_user_linked_assets(PDO $pdo, string $nik, string $name): array
{
    $nik = trim($nik);
    $name = trim($name);
    $res = ['pcs' => [], 'assets' => [], 'printers' => []];

    if ($nik === '' && $name === '') {
        return $res;
    }

    if (db_table_exists($pdo, 'pcs')) {
        $where = [];
        $p = [];
        if ($nik !== '') {
            $where[] = 'p.employee_nik = ?';
            $p[] = $nik;
        }
        if ($name !== '') {
            $where[] = 'LOWER(p.owner_name) = LOWER(?)';
            $p[] = $name;
        }
        $sql = "SELECT p.pc_id, p.computer_name, p.owner_name, p.location_label, ai.asset_code 
                FROM pcs p 
                LEFT JOIN asset_items ai ON ai.id = p.asset_item_id 
                WHERE " . implode(' OR ', $where) . " 
                ORDER BY p.pc_id ASC";
        $s = $pdo->prepare($sql);
        $s->execute($p);
        $res['pcs'] = $s->fetchAll(PDO::FETCH_ASSOC);
    }

    if (db_table_exists($pdo, 'asset_items')) {
        $where = [];
        $p = [];
        if ($nik !== '') {
            $where[] = 'ai.custodian_nik = ?';
            $p[] = $nik;
        }
        if ($name !== '') {
            $where[] = 'LOWER(ai.custodian_name) = LOWER(?)';
            $p[] = $name;
        }
        $sql = "SELECT ai.id, ai.asset_code, ai.asset_name, ai.asset_type, ai.brand, ai.model, ai.status, ai.location_label, c.company_name 
                FROM asset_items ai 
                LEFT JOIN asset_companies c ON c.id = ai.company_id 
                WHERE (" . implode(' OR ', $where) . ") AND LOWER(ai.status) <> 'deleted' 
                ORDER BY ai.asset_code ASC";
        $s = $pdo->prepare($sql);
        $s->execute($p);
        $res['assets'] = $s->fetchAll(PDO::FETCH_ASSOC);
    }

    if (db_table_exists($pdo, 'printers')) {
        $hasOwner = db_column_exists($pdo, 'printers', 'owner_name');
        $hasNik = db_column_exists($pdo, 'printers', 'employee_nik');
        if ($hasOwner || $hasNik) {
            $where = [];
            $p = [];
            if ($hasNik && $nik !== '') {
                $where[] = 'pr.employee_nik = ?';
                $p[] = $nik;
            }
            if ($hasOwner && $name !== '') {
                $where[] = 'LOWER(pr.owner_name) = LOWER(?)';
                $p[] = $name;
            }
            if ($where) {
                $sql = "SELECT pr.prn_id, pr.printer_name, pr.location, pr.model_printer, pr.ip_printer 
                        FROM printers pr 
                        WHERE " . implode(' OR ', $where) . " 
                        ORDER BY pr.prn_id ASC";
                $s = $pdo->prepare($sql);
                $s->execute($p);
                $res['printers'] = $s->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }

    return $res;
}

if (!function_exists('employee_source_defaults')) {
    function employee_source_defaults(): array
    {
        return [
            'enabled' => '1',
            'host' => '',
            'port' => '3306',
            'database' => '',
            'username' => '',
            'password' => '',
            'table_name' => 'employees',
            'nik_field' => 'NIK',
            'name_field' => 'Nama',
            'department_field' => '',
            'limit_rows' => '5000',
        ];
    }
}

if (!function_exists('employee_source_config')) {
    function employee_source_config(PDO $pdo): array
    {
        $config = employee_source_defaults();
        try {
            if (!db_table_exists($pdo, 'employee_source_config')) {
                return $config;
            }
            foreach ($pdo->query('SELECT config_key, config_value FROM employee_source_config') as $row) {
                $key = (string)$row['config_key'];
                if (array_key_exists($key, $config)) {
                    $config[$key] = (string)($row['config_value'] ?? '');
                }
            }
        } catch (Throwable $ignored) {
        }
        return $config;
    }
}

if (!function_exists('save_employee_source_config')) {
    function save_employee_source_config(PDO $pdo, array $config): void
    {
        ensure_employee_source_schema($pdo);
        $allowed = employee_source_defaults();
        $stmt = $pdo->prepare('REPLACE INTO employee_source_config (config_key, config_value) VALUES (?, ?)');
        foreach ($allowed as $key => $default) {
            $stmt->execute([$key, (string)($config[$key] ?? $default)]);
        }
    }
}

if (!function_exists('mysql_identifier')) {
    function mysql_identifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if (!preg_match('/^[A-Za-z0-9_ -]+(\.[A-Za-z0-9_ -]+)?$/', $identifier)) {
            throw new RuntimeException('Nama table/field MariaDB tidak valid: ' . $identifier);
        }
        $parts = explode('.', $identifier);
        return implode('.', array_map(static fn(string $part): string => '`' . str_replace('`', '``', $part) . '`', $parts));
    }
}

if (!function_exists('employee_portal_connection_from_config')) {
    function employee_portal_connection_from_config(array $config): PDO
    {
        $host = trim((string)($config['host'] ?? ''));
        $database = trim((string)($config['database'] ?? ''));
        if ($host === '' || $database === '') {
            throw new RuntimeException('Host/IP dan database MariaDB portal wajib diisi.');
        }
        $port = (int)($config['port'] ?? 3306);
        $dsn = 'mysql:host=' . $host . ';port=' . ($port > 0 ? $port : 3306) . ';dbname=' . $database . ';charset=utf8mb4';
        return new PDO($dsn, (string)($config['username'] ?? ''), (string)($config['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 8,
        ]);
    }
}

if (!function_exists('normalize_employee_rows')) {
    function normalize_employee_rows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $nik = trim((string)($row['nik'] ?? $row['NIK'] ?? ''));
            $name = trim((string)($row['name'] ?? $row['Nama'] ?? $row['employee_name'] ?? ''));
            if ($nik === '' || $name === '') {
                continue;
            }
            $normalized[] = [
                'nik' => $nik,
                'name' => $name,
                'department' => trim((string)($row['department'] ?? $row['Department'] ?? '')),
            ];
        }
        return $normalized;
    }
}

if (!function_exists('fetch_employee_portal_rows')) {
    function fetch_employee_portal_rows(array $config, int $limit): array
    {
        $remote = employee_portal_connection_from_config($config);
        $limit = max(1, min(50000, $limit));
        $table = mysql_identifier((string)($config['table_name'] ?: 'employees'));
        $nikField = mysql_identifier((string)($config['nik_field'] ?: 'NIK'));
        $nameField = mysql_identifier((string)($config['name_field'] ?: 'Nama'));
        $departmentFieldRaw = trim((string)($config['department_field'] ?? ''));
        $departmentSelect = $departmentFieldRaw !== '' ? ', CAST(' . mysql_identifier($departmentFieldRaw) . ' AS CHAR) AS department' : ", CAST('' AS CHAR) AS department";
        $sql = 'SELECT CAST(' . $nikField . ' AS CHAR) AS nik, CAST(' . $nameField . ' AS CHAR) AS name' . $departmentSelect . ' FROM ' . $table . ' WHERE ' . $nikField . ' IS NOT NULL AND ' . $nameField . ' IS NOT NULL ORDER BY ' . $nameField . ' LIMIT ' . (int)$limit;
        return normalize_employee_rows($remote->query($sql)->fetchAll());
    }
}

if (!function_exists('import_employee_directory')) {
    function import_employee_directory(PDO $appPdo): int
    {
        ensure_employee_source_schema($appPdo);
        $config = employee_source_config($appPdo);
        $limit = max(1, min(50000, (int)($config['limit_rows'] ?? 5000)));
        $rows = fetch_employee_portal_rows($config, $limit);
        if (!$rows) {
            return 0;
        }
        $appPdo->beginTransaction();
        try {
            $hasName = db_column_exists($appPdo, 'employee_directory', 'name');
            $hasEmpName = db_column_exists($appPdo, 'employee_directory', 'employee_name');

            if ($hasName && $hasEmpName) {
                $stmt = $appPdo->prepare('REPLACE INTO employee_directory (nik, name, employee_name, department, is_active, synced_at) VALUES (?, ?, ?, ?, 1, NOW())');
                foreach ($rows as $row) {
                    $stmt->execute([$row['nik'], $row['name'], $row['name'], $row['department']]);
                }
            } elseif ($hasEmpName) {
                $stmt = $appPdo->prepare('REPLACE INTO employee_directory (nik, employee_name, department, is_active, synced_at) VALUES (?, ?, ?, 1, NOW())');
                foreach ($rows as $row) {
                    $stmt->execute([$row['nik'], $row['name'], $row['department']]);
                }
            } else {
                $stmt = $appPdo->prepare('REPLACE INTO employee_directory (nik, name, department, is_active, synced_at) VALUES (?, ?, ?, 1, NOW())');
                foreach ($rows as $row) {
                    $stmt->execute([$row['nik'], $row['name'], $row['department']]);
                }
            }
            $appPdo->commit();
        } catch (Throwable $e) {
            if ($appPdo->inTransaction()) {
                $appPdo->rollBack();
            }
            throw $e;
        }
        return count($rows);
    }
}

if (!function_exists('sync_employee_directory')) {
    function sync_employee_directory(PDO $appPdo): int
    {
        return import_employee_directory($appPdo);
    }
}


function handle_route_master_pengguna(PDO $pdo): void
{
    $user = require_regulation('master_pengguna', 'view');

    if (!db_table_exists($pdo, 'employee_directory')) {
        ensure_app_schema($pdo);
    }
    ensure_employee_source_schema($pdo);

    $activeTab = (string)($_GET['tab'] ?? 'list');
    if (!in_array($activeTab, ['list', 'setting'], true)) {
        $activeTab = 'list';
    }

    // Pastikan kolom owner_name dan employee_nik ada di printers
    if (db_table_exists($pdo, 'printers')) {
        if (!db_column_exists($pdo, 'printers', 'owner_name')) {
            try { $pdo->exec("ALTER TABLE printers ADD COLUMN owner_name VARCHAR(160) NULL AFTER location"); } catch (Throwable $e) {}
        }
        if (!db_column_exists($pdo, 'printers', 'employee_nik')) {
            try { $pdo->exec("ALTER TABLE printers ADD COLUMN employee_nik VARCHAR(80) NULL AFTER owner_name"); } catch (Throwable $e) {}
        }
    }

    $nameCol = db_column_exists($pdo, 'employee_directory', 'name') ? 'name' : 'employee_name';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string)($_POST['action'] ?? '');

        // 1. Simpan Konfigurasi Koneksi & Field MariaDB Portal
        if ($action === 'save_import_config') {
            require_regulation('master_pengguna', 'edit');
            $config = [
                'enabled' => '1',
                'host' => trim((string)($_POST['host'] ?? '')),
                'port' => trim((string)($_POST['port'] ?? '3306')),
                'database' => trim((string)($_POST['database'] ?? '')),
                'username' => trim((string)($_POST['username'] ?? '')),
                'password' => (string)($_POST['password'] ?? ''),
                'table_name' => trim((string)($_POST['table_name'] ?? 'employees')),
                'nik_field' => trim((string)($_POST['nik_field'] ?? 'NIK')),
                'name_field' => trim((string)($_POST['name_field'] ?? 'Nama')),
                'department_field' => trim((string)($_POST['department_field'] ?? '')),
                'limit_rows' => trim((string)($_POST['limit_rows'] ?? '5000')),
            ];
            if ($config['password'] === '') {
                $oldConfig = employee_source_config($pdo);
                $config['password'] = (string)($oldConfig['password'] ?? '');
            }
            save_employee_source_config($pdo, $config);
            flash('Konfigurasi koneksi MariaDB portal dan pemetaan field berhasil disimpan.');
            redirect_to('master_pengguna', ['tab' => 'setting']);
        }

        // 2. Test Koneksi MariaDB Portal
        if ($action === 'test_import_config') {
            require_regulation('master_pengguna', 'view');
            $config = [
                'enabled' => '1',
                'host' => trim((string)($_POST['host'] ?? '')),
                'port' => trim((string)($_POST['port'] ?? '3306')),
                'database' => trim((string)($_POST['database'] ?? '')),
                'username' => trim((string)($_POST['username'] ?? '')),
                'password' => (string)($_POST['password'] ?? ''),
                'table_name' => trim((string)($_POST['table_name'] ?? 'employees')),
                'nik_field' => trim((string)($_POST['nik_field'] ?? 'NIK')),
                'name_field' => trim((string)($_POST['name_field'] ?? 'Nama')),
                'department_field' => trim((string)($_POST['department_field'] ?? '')),
                'limit_rows' => trim((string)($_POST['limit_rows'] ?? '5000')),
            ];
            if ($config['password'] === '') {
                $oldConfig = employee_source_config($pdo);
                $config['password'] = (string)($oldConfig['password'] ?? '');
            }
            try {
                $remoteRows = fetch_employee_portal_rows($config, 5);
                flash('Koneksi MariaDB portal berhasil! Sample terbaca ' . count($remoteRows) . ' karyawan (misal: ' . (!empty($remoteRows[0]['name']) ? $remoteRows[0]['name'] : 'OK') . ').');
            } catch (Throwable $e) {
                flash('Test koneksi MariaDB portal gagal: ' . $e->getMessage(), 'err');
            }
            redirect_to('master_pengguna', ['tab' => 'setting']);
        }

        // 3. Import / Sinkronisasi Data Karyawan dari MariaDB Portal
        if ($action === 'import_portal_employees' || $action === 'sync') {
            require_regulation('master_pengguna', 'create');
            if (!empty($_POST['host'])) {
                $config = [
                    'enabled' => '1',
                    'host' => trim((string)($_POST['host'] ?? '')),
                    'port' => trim((string)($_POST['port'] ?? '3306')),
                    'database' => trim((string)($_POST['database'] ?? '')),
                    'username' => trim((string)($_POST['username'] ?? '')),
                    'password' => (string)($_POST['password'] ?? ''),
                    'table_name' => trim((string)($_POST['table_name'] ?? 'employees')),
                    'nik_field' => trim((string)($_POST['nik_field'] ?? 'NIK')),
                    'name_field' => trim((string)($_POST['name_field'] ?? 'Nama')),
                    'department_field' => trim((string)($_POST['department_field'] ?? '')),
                    'limit_rows' => trim((string)($_POST['limit_rows'] ?? '5000')),
                ];
                if ($config['password'] === '') {
                    $oldConfig = employee_source_config($pdo);
                    $config['password'] = (string)($oldConfig['password'] ?? '');
                }
                save_employee_source_config($pdo, $config);
            }
            try {
                $cnt = import_employee_directory($pdo);
                flash("Berhasil mengimpor/sinkronisasi {$cnt} data karyawan dari MariaDB Portal ke database lokal.");
            } catch (Throwable $e) {
                flash('Gagal impor/sinkronisasi: ' . $e->getMessage(), 'err');
            }
            redirect_to('master_pengguna', $action === 'import_portal_employees' ? ['tab' => 'setting'] : []);
        }

        // 4. Kosongkan Database Lokal
        if ($action === 'truncate_local_employees') {
            require_regulation('master_pengguna', 'delete');
            try {
                $pdo->exec('DELETE FROM employee_directory');
                flash('Seluruh data karyawan di database lokal berhasil dikosongkan.');
            } catch (Throwable $e) {
                flash('Gagal mengosongkan data karyawan: ' . $e->getMessage(), 'err');
            }
            redirect_to('master_pengguna', ['tab' => 'setting']);
        }

        if ($action === 'add') {
            require_regulation('master_pengguna', 'create');
            $nik = strtoupper(trim((string)($_POST['nik'] ?? '')));
            $name = trim((string)($_POST['name'] ?? ''));
            $dept = trim((string)($_POST['department'] ?? ''));
            $createLogin = !empty($_POST['create_login']);
            $username = trim((string)($_POST['username'] ?? ''));
            $role = (string)($_POST['role'] ?? 'technician');
            $password = (string)($_POST['password'] ?? '');

            if ($nik === '' || $name === '') {
                flash('NIK dan Nama Pengguna wajib diisi.', 'err');
                redirect_to('master_pengguna');
            }

            try {
                $hasBoth = db_column_exists($pdo, 'employee_directory', 'name') && db_column_exists($pdo, 'employee_directory', 'employee_name');
                if ($hasBoth) {
                    $stmt = $pdo->prepare("INSERT INTO employee_directory (nik, name, employee_name, department, is_active, synced_at) VALUES (?, ?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name), employee_name=VALUES(employee_name), department=VALUES(department), is_active=1");
                    $stmt->execute([$nik, $name, $name, $dept]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO employee_directory (nik, $nameCol, department, is_active, synced_at) VALUES (?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE $nameCol=VALUES($nameCol), department=VALUES(department), is_active=1");
                    $stmt->execute([$nik, $name, $dept]);
                }

                if ($createLogin) {
                    require_regulation('users', 'create');
                    $uName = $username !== '' ? $username : strtolower($nik);
                    if ($password === '' || strlen($password) < 6) {
                        flash('User disimpan, namun Akun Login butuh password minimal 6 karakter.', 'err');
                    } else {
                        if (!in_array($role, ['admin', 'maintenance_admin', 'technician', 'corrective_maintenance', 'loan_officer'], true)) {
                            $role = 'technician';
                        }
                        $stmtU = $pdo->prepare("INSERT INTO users (name, username, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE name=VALUES(name), role=VALUES(role), password_hash=VALUES(password_hash), is_active=1");
                        $stmtU->execute([$name, $uName, password_hash($password, PASSWORD_DEFAULT), $role]);
                    }
                }

                flash("Pengguna {$name} ({$nik}) berhasil disimpan.");
            } catch (Throwable $e) {
                flash('Gagal menyimpan pengguna: ' . $e->getMessage(), 'err');
            }
            redirect_to('master_pengguna');
        }

        if ($action === 'edit') {
            require_regulation('master_pengguna', 'edit');
            $nik = strtoupper(trim((string)($_POST['nik'] ?? '')));
            $name = trim((string)($_POST['name'] ?? ''));
            $dept = trim((string)($_POST['department'] ?? ''));
            $isActive = !empty($_POST['is_active']) ? 1 : 0;

            if ($nik === '' || $name === '') {
                flash('NIK dan Nama Pengguna wajib diisi.', 'err');
                redirect_to('master_pengguna');
            }

            try {
                $hasBoth = db_column_exists($pdo, 'employee_directory', 'name') && db_column_exists($pdo, 'employee_directory', 'employee_name');
                if ($hasBoth) {
                    $stmt = $pdo->prepare("UPDATE employee_directory SET name = ?, employee_name = ?, department = ?, is_active = ? WHERE nik = ?");
                    $stmt->execute([$name, $name, $dept, $isActive, $nik]);
                } else {
                    $stmt = $pdo->prepare("UPDATE employee_directory SET $nameCol = ?, department = ?, is_active = ? WHERE nik = ?");
                    $stmt->execute([$name, $dept, $isActive, $nik]);
                }
                flash("Data pengguna {$name} berhasil diperbarui.");
            } catch (Throwable $e) {
                flash('Gagal memperbarui pengguna: ' . $e->getMessage(), 'err');
            }
            redirect_to('master_pengguna');
        }

        if ($action === 'toggle') {
            require_regulation('master_pengguna', 'edit');
            $nik = trim((string)($_POST['nik'] ?? ''));
            if ($nik !== '') {
                try {
                    $pdo->prepare("UPDATE employee_directory SET is_active = 1 - is_active WHERE nik = ?")->execute([$nik]);
                    flash('Status aktif pengguna berhasil diubah.');
                } catch (Throwable $e) {
                    flash('Gagal mengubah status: ' . $e->getMessage(), 'err');
                }
            }
            redirect_to('master_pengguna');
        }

        if ($action === 'create_login_for') {
            require_regulation('users', 'create');
            $nik = strtoupper(trim((string)($_POST['nik'] ?? '')));
            $name = trim((string)($_POST['name'] ?? ''));
            $username = trim((string)($_POST['username'] ?? '')) ?: strtolower($nik);
            $role = (string)($_POST['role'] ?? 'technician');
            $password = (string)($_POST['password'] ?? '');

            if ($password === '' || strlen($password) < 6) {
                flash('Password baru minimal 6 karakter.', 'err');
                redirect_to('master_pengguna');
            }
            if (!in_array($role, ['admin', 'maintenance_admin', 'technician', 'corrective_maintenance', 'loan_officer'], true)) {
                $role = 'technician';
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO users (name, username, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE name=VALUES(name), password_hash=VALUES(password_hash), role=VALUES(role), is_active=1");
                $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role]);
                flash("Akun login sistem untuk {$name} ({$username}) berhasil dibuat / diperbarui.");
            } catch (Throwable $e) {
                flash('Gagal membuat akun login: ' . $e->getMessage(), 'err');
            }
            redirect_to('master_pengguna');
        }

        if ($action === 'delete') {
            require_regulation('master_pengguna', 'delete');
            $nik = trim((string)($_POST['nik'] ?? ''));
            if ($nik !== '') {
                try {
                    $pdo->prepare("DELETE FROM employee_directory WHERE nik = ?")->execute([$nik]);
                    flash('Data pengguna berhasil dihapus dari master lokal.');
                } catch (Throwable $e) {
                    flash('Gagal menghapus: ' . $e->getMessage(), 'err');
                }
            }
            redirect_to('master_pengguna');
        }
    }

    $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM employee_directory")->fetchColumn();
    $activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM employee_directory WHERE is_active=1")->fetchColumn();
    $lastSync = (string)$pdo->query("SELECT MAX(synced_at) FROM employee_directory")->fetchColumn();
    $config = employee_source_config($pdo);

    render_header('Master Pengguna', $user);

    echo '<section class="panel">';
    echo '<div class="split"><div><h1 style="margin-bottom:4px;">Master Pengguna (Karyawan & Pemakai Aset)</h1><p class="muted">Master terpusat untuk semua pengguna dan pemakai aset pada seluruh form (PC, Printer, Unit Aset, Maintenance, Tiket Corrective).</p></div>';
    echo '<div class="actions" style="display:flex;gap:8px;align-items:center;">';
    if (has_regulation('master_pengguna', 'create')) {
        if ($activeTab === 'setting') {
            echo '<a class="btn" href="' . route_url('master_pengguna', ['tab' => 'list']) . '">← Kembali ke Daftar Pengguna</a>';
        } else {
            echo '<a class="btn" href="' . route_url('master_pengguna', ['tab' => 'setting']) . '" style="background:#0f766e;color:#fff;border-color:#0f766e;font-weight:600;">⚙️ Setting Koneksi Import Pengguna</a>';
        }
        echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'Sinkronisasi ulang seluruh data karyawan dari MariaDB portal?\')"><input type="hidden" name="csrf" value="' . csrf_token() . '"><input type="hidden" name="action" value="sync"><button class="btn" style="background:#0284c7;color:#fff;border-color:#0284c7;">🔄 Sinkronisasi dari Portal</button></form>';
        echo '<button type="button" class="btn primary" onclick="openTambahPenggunaModal()" style="display:inline-flex;align-items:center;gap:6px;font-weight:600;"><span style="font-size:15px;font-weight:700;">+</span> Tambah Pengguna</button>';
    }
    echo '</div></div>';

    // Tab Navigation Switcher
    echo '<div style="display:flex;gap:10px;margin-top:16px;border-bottom:2px solid #e2e8f0;padding-bottom:1px;">';
    echo '<a href="' . route_url('master_pengguna', ['tab' => 'list']) . '" class="btn ' . ($activeTab === 'list' ? 'primary' : '') . '" style="padding:8px 18px;font-weight:700;font-size:13px;border-radius:8px 8px 0 0;margin-bottom:-2px;border-bottom:none;">👥 Daftar Master Pengguna</a>';
    echo '<a href="' . route_url('master_pengguna', ['tab' => 'setting']) . '" class="btn ' . ($activeTab === 'setting' ? 'primary' : '') . '" style="padding:8px 18px;font-weight:700;font-size:13px;border-radius:8px 8px 0 0;margin-bottom:-2px;border-bottom:none;">⚙️ Setting Koneksi Import Pengguna</a>';
    echo '</div>';
    echo '</section>';

    // =========================================================================
    // TAB 2: SETTING KONEKSI IMPORT PENGGUNA
    // =========================================================================
    if ($activeTab === 'setting') {
        $portalTarget = (!empty($config['host']) && !empty($config['database']))
            ? (e($config['host']) . ' / ' . e($config['database']))
            : 'Belum diatur';

        echo '<section class="panel">';
        echo '<h2>Ringkasan Integrasi MariaDB Portal</h2>';
        echo '<p class="muted">Konfigurasi ini digunakan untuk mengimpor seluruh master data karyawan dari server MariaDB portal ke dalam database lokal PcConnect.</p>';
        echo '<div class="grid three" style="margin-top:14px;">';
        echo '<div class="stat"><strong style="color:#0284c7;">' . e((string)$totalUsers) . '</strong><span>Total Karyawan di Database Lokal</span></div>';
        echo '<div class="stat"><strong style="color:#166534;">' . e((string)$activeUsers) . '</strong><span>Karyawan Aktif</span></div>';
        echo '<div class="stat"><strong style="font-size:15px;padding-top:8px;">' . $portalTarget . '</strong><span>Target Host / Database Portal</span></div>';
        echo '</div>';
        echo '</section>';

        echo '<section class="panel">';
        echo '<form method="post">';
        echo '<input type="hidden" name="csrf" value="' . csrf_token() . '">';

        echo '<h2 style="margin-top:0;">1. Konfigurasi Koneksi MariaDB Portal</h2>';
        echo '<div class="grid three">';
        echo '<label>IP / Host MariaDB Portal<input name="host" value="' . e($config['host']) . '" placeholder="Contoh: 192.168.1.10 atau portal.domain.com" required></label>';
        echo '<label>Port<input name="port" value="' . e($config['port'] ?: '3306') . '" placeholder="3306"></label>';
        echo '<label>Database<input name="database" value="' . e($config['database']) . '" placeholder="Contoh: portal_hrd" required></label>';
        echo '<label>Username<input name="username" value="' . e($config['username']) . '" placeholder="Contoh: user_mariadb"></label>';
        echo '<label>Password<input type="password" name="password" placeholder="Kosongkan jika tidak diubah"></label>';
        echo '<label>Maksimal Baris Import<input type="number" min="1" max="50000" name="limit_rows" value="' . e($config['limit_rows'] ?: '5000') . '"></label>';
        echo '</div>';

        echo '<h2 style="margin-top:20px;">2. Pemetaan Field Tabel MariaDB</h2>';
        echo '<div class="grid four">';
        echo '<label>Table / View Karyawan<input name="table_name" value="' . e($config['table_name'] ?: 'employees') . '" placeholder="Contoh: employees" required></label>';
        echo '<label>Field NIK<input name="nik_field" value="' . e($config['nik_field'] ?: 'NIK') . '" placeholder="Contoh: NIK" required></label>';
        echo '<label>Field Nama Karyawan<input name="name_field" value="' . e($config['name_field'] ?: 'Nama') . '" placeholder="Contoh: Nama" required></label>';
        echo '<label>Field Departemen<input name="department_field" value="' . e($config['department_field'] ?? '') . '" placeholder="Contoh: Department"></label>';
        echo '</div>';

        echo '<div class="split" style="margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0;align-items:center;">';
        echo '<div style="display:flex;gap:8px;flex-wrap:wrap;">';
        echo '<button class="btn ok" style="font-weight:700;padding:10px 20px;font-size:14px;" name="action" value="import_portal_employees">📥 Import Data Karyawan ke Database Lokal</button>';
        echo '<button class="btn" name="action" value="test_import_config">🔌 Test Koneksi MariaDB</button>';
        echo '<button class="btn primary" name="action" value="save_import_config">💾 Simpan Konfigurasi</button>';
        echo '<a class="btn" href="' . route_url('master_pengguna', ['tab' => 'list']) . '">Batal / Kembali</a>';
        echo '</div>';
        if ($totalUsers > 0) {
            echo '<div>';
            echo '<button class="btn danger" name="action" value="truncate_local_employees" onclick="return confirm(\'Yakin ingin mengosongkan seluruh data karyawan di database lokal?\');">🗑️ Kosongkan Database Lokal</button>';
            echo '</div>';
        }
        echo '</div>';
        echo '</form>';
        echo '</section>';

        // Render modal tambah pengguna & create login tetap ada agar tombol header selalu bekerja
        render_user_modals();
        render_footer();
        return;
    }

    // =========================================================================
    // TAB 1: DAFTAR MASTER PENGGUNA
    // =========================================================================
    // Summary stats
    echo '<section class="panel">';
    echo '<div class="grid three" style="margin:4px 0 0 0;">';
    echo '<div class="stat"><strong style="color:#0284c7;">' . e((string)$totalUsers) . '</strong><span>Total Terdaftar di Master</span></div>';
    echo '<div class="stat"><strong style="color:#166534;">' . e((string)$activeUsers) . '</strong><span>Pengguna Aktif</span></div>';
    echo '<div class="stat"><strong style="font-size:20px;padding-top:8px;">' . ($lastSync ? e(date('d M Y H:i', strtotime($lastSync))) : 'Belum pernah') . '</strong><span>Terakhir Sinkronisasi Portal</span></div>';
    echo '</div>';
    echo '</section>';

    $q = trim((string)($_GET['q'] ?? ''));
    $statusFilter = (string)($_GET['status'] ?? 'all');
    $viewNik = trim((string)($_GET['view_nik'] ?? ''));
    $editNik = trim((string)($_GET['edit_nik'] ?? ''));

    // Edit user modal/form data
    $editUser = null;
    if ($editNik !== '') {
        $stmtEdit = $pdo->prepare("SELECT nik, $nameCol AS name, department, is_active FROM employee_directory WHERE nik = ? LIMIT 1");
        $stmtEdit->execute([$editNik]);
        $editUser = $stmtEdit->fetch(PDO::FETCH_ASSOC);
    }

    // View assets modal data
    $viewUser = null;
    $viewAssets = null;
    if ($viewNik !== '') {
        $stmtView = $pdo->prepare("SELECT nik, $nameCol AS name, department FROM employee_directory WHERE nik = ? LIMIT 1");
        $stmtView->execute([$viewNik]);
        $viewUser = $stmtView->fetch(PDO::FETCH_ASSOC);
        if ($viewUser) {
            $viewAssets = get_user_linked_assets($pdo, $viewUser['nik'], $viewUser['name']);
        }
    }

    // Modal / View Linked Assets
    if ($viewUser && $viewAssets) {
        $totPcs = count($viewAssets['pcs']);
        $totAssets = count($viewAssets['assets']);
        $totPrinters = count($viewAssets['printers']);
        echo '<section class="panel" style="border:2px solid #0284c7;background:#f8fafc;">';
        echo '<div class="split"><h2 style="color:#0369a1;margin:0;">📦 Aset yang Dipegang oleh: ' . e($viewUser['name']) . ' (' . e($viewUser['nik']) . ') - ' . e($viewUser['department'] ?: 'Umum') . '</h2><a class="btn" href="' . route_url('master_pengguna') . '">Tutup Rincian Aset ✕</a></div>';
        echo '<div style="margin-top:14px;display:flex;gap:12px;flex-wrap:wrap;">';
        echo '<span class="badge ok" style="font-size:13px;padding:6px 12px;">' . $totPcs . ' Komputer / Laptop Terdaftar</span>';
        echo '<span class="badge" style="font-size:13px;padding:6px 12px;background:#e0f2fe;color:#0369a1;">' . $totAssets . ' Unit Aset (Hardware/Fasilitas)</span>';
        echo '<span class="badge" style="font-size:13px;padding:6px 12px;background:#fef3c7;color:#92400e;">' . $totPrinters . ' Printer Terhubung</span>';
        echo '</div>';

        if ($totPcs > 0) {
            echo '<h3 style="margin:16px 0 6px 0;">💻 Daftar PC / Laptop</h3><table><tr><th>PC ID</th><th>Nama Komputer</th><th>Aset ID</th><th>Lokasi</th><th>Aksi</th></tr>';
            foreach ($viewAssets['pcs'] as $vp) {
                echo '<tr><td><strong>' . e($vp['pc_id']) . '</strong></td><td>' . e($vp['computer_name']) . '</td><td>' . e($vp['asset_code'] ?: '-') . '</td><td>' . e($vp['location_label'] ?: '-') . '</td><td><a class="btn" href="' . route_url('pc_detail', ['id' => $vp['pc_id']]) . '">Lihat PC</a></td></tr>';
            }
            echo '</table>';
        }

        if ($totAssets > 0) {
            echo '<h3 style="margin:16px 0 6px 0;">🏷️ Daftar Unit Aset</h3><table><tr><th>Kode Aset</th><th>Nama Aset</th><th>Kategori / Tipe</th><th>Brand & Model</th><th>Lokasi</th><th>Status</th><th>Aksi</th></tr>';
            foreach ($viewAssets['assets'] as $va) {
                echo '<tr><td><strong>' . e($va['asset_code']) . '</strong></td><td>' . e($va['asset_name']) . '</td><td>' . e($va['asset_type']) . '</td><td>' . e(trim(($va['brand'] ?? '') . ' ' . ($va['model'] ?? ''))) . '</td><td>' . e($va['location_label'] ?: '-') . '</td><td>' . (function_exists('format_asset_status_badge') ? format_asset_status_badge($va['status'] ?? '') : ('<span class="badge">' . e($va['status']) . '</span>')) . '</td><td><a class="btn" href="' . route_url('asset_item_form', ['id' => $va['id']]) . '">Buka Aset</a></td></tr>';
            }
            echo '</table>';
        }

        if ($totPrinters > 0) {
            echo '<h3 style="margin:16px 0 6px 0;">🖨️ Daftar Printer</h3><table><tr><th>PrnID</th><th>Nama Printer</th><th>Model</th><th>IP Address</th><th>Lokasi</th><th>Aksi</th></tr>';
            foreach ($viewAssets['printers'] as $vpr) {
                echo '<tr><td><strong>' . e($vpr['prn_id']) . '</strong></td><td>' . e($vpr['printer_name']) . '</td><td>' . e($vpr['model_printer'] ?: '-') . '</td><td>' . e($vpr['ip_printer'] ?: '-') . '</td><td>' . e($vpr['location'] ?: '-') . '</td><td><a class="btn" href="' . route_url('printer_detail', ['id' => $vpr['prn_id']]) . '">Lihat Printer</a></td></tr>';
            }
            echo '</table>';
        }

        if ($totPcs === 0 && $totAssets === 0 && $totPrinters === 0) {
            echo '<p class="muted" style="margin-top:12px;">Pengguna ini saat ini belum terhubung dengan PC, Unit Aset, atau Printer mana pun.</p>';
        }

        echo '</section>';
    }

    // Form Edit Pengguna (jika parameter edit_nik terisi)
    if ($editUser) {
        echo '<section class="panel" style="border:2px solid #f59e0b;background:#fffbeb;">';
        echo '<div class="split"><h2 style="color:#b45309;margin:0;">✏️ Edit Data Pengguna: ' . e($editUser['name']) . ' (' . e($editUser['nik']) . ')</h2><a class="btn" href="' . route_url('master_pengguna') . '">Batal Edit</a></div>';
        echo '<form method="post" style="margin-top:14px;"><input type="hidden" name="csrf" value="' . csrf_token() . '"><input type="hidden" name="action" value="edit"><input type="hidden" name="nik" value="' . e($editUser['nik']) . '">';
        echo '<div class="grid three">';
        echo '<label>NIK (ID Unik)<input value="' . e($editUser['nik']) . '" disabled style="background:#f1f5f9;cursor:not-allowed;"></label>';
        echo '<label>Nama Pengguna / Karyawan *<input name="name" value="' . e($editUser['name']) . '" required></label>';
        echo '<label>Departemen / Divisi<input name="department" value="' . e($editUser['department']) . '"></label>';
        echo '</div>';
        echo '<div class="grid two" style="align-items:center;margin-top:6px;">';
        echo '<label><input type="checkbox" name="is_active" value="1" ' . (!empty($editUser['is_active']) ? 'checked' : '') . ' style="width:auto;"> Status Aktif (Tampil di form pilihan aset dan PC)</label>';
        echo '<div class="actions" style="justify-content:flex-end;"><button class="btn primary">Simpan Perubahan</button><a class="btn" href="' . route_url('master_pengguna') . '">Batal</a></div>';
        echo '</div></form></section>';
    }

    // Query Pengguna List with linked user account & asset counts
    $where = ["1=1"];
    $params = [];
    if ($q !== '') {
        $where[] = "(ed.nik LIKE ? OR ed.$nameCol LIKE ? OR ed.department LIKE ?)";
        $params[] = "%{$q}%";
        $params[] = "%{$q}%";
        $params[] = "%{$q}%";
    }
    if ($statusFilter === 'active') {
        $where[] = "ed.is_active = 1";
    } elseif ($statusFilter === 'inactive') {
        $where[] = "ed.is_active = 0";
    }

    $usersMap = [];
    if (db_table_exists($pdo, 'users')) {
        try {
            $uRows = $pdo->query("SELECT id, name, username, role, is_active FROM users")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($uRows as $ur) {
                $un = strtolower(trim((string)$ur['username']));
                $nm = strtolower(trim((string)$ur['name']));
                if ($un !== '') {
                    $usersMap['u_' . $un] = $ur;
                }
                if ($nm !== '') {
                    $usersMap['n_' . $nm] = $ur;
                }
            }
        } catch (Throwable $ignored) {
        }
    }

    $sql = "SELECT ed.nik, ed.$nameCol AS name, ed.department, ed.is_active, ed.synced_at
            FROM employee_directory ed
            WHERE " . implode(" AND ", $where) . "
            ORDER BY ed.is_active DESC, ed.$nameCol ASC
            LIMIT 300";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $nikKey = 'u_' . strtolower(trim((string)$r['nik']));
        $nameKey = 'n_' . strtolower(trim((string)$r['name']));
        $matchedUser = $usersMap[$nikKey] ?? $usersMap[$nameKey] ?? null;
        $r['user_account_id'] = $matchedUser['id'] ?? null;
        $r['username'] = $matchedUser['username'] ?? null;
        $r['user_role'] = $matchedUser['role'] ?? null;
        $r['user_account_active'] = $matchedUser['is_active'] ?? null;
    }
    unset($r);

    $pcCounts = [];
    $assetCounts = [];
    $printerCounts = [];
    if ($rows) {
        if (db_table_exists($pdo, 'pcs')) {
            $qPcs = $pdo->query("SELECT p.employee_nik, p.owner_name, COUNT(*) as cnt FROM pcs p GROUP BY p.employee_nik, p.owner_name");
            while ($r = $qPcs->fetch(PDO::FETCH_ASSOC)) {
                $en = trim((string)($r['employee_nik'] ?? ''));
                $on = strtolower(trim((string)($r['owner_name'] ?? '')));
                $cnt = (int)$r['cnt'];
                if ($en !== '') $pcCounts['nik_' . $en] = ($pcCounts['nik_' . $en] ?? 0) + $cnt;
                if ($on !== '') $pcCounts['name_' . $on] = ($pcCounts['name_' . $on] ?? 0) + $cnt;
            }
        }

        if (db_table_exists($pdo, 'asset_items')) {
            $qAi = $pdo->query("SELECT ai.custodian_nik, ai.custodian_name, COUNT(*) as cnt FROM asset_items ai GROUP BY ai.custodian_nik, ai.custodian_name");
            while ($r = $qAi->fetch(PDO::FETCH_ASSOC)) {
                $cn = trim((string)($r['custodian_nik'] ?? ''));
                $cname = strtolower(trim((string)($r['custodian_name'] ?? '')));
                $cnt = (int)$r['cnt'];
                if ($cn !== '') $assetCounts['nik_' . $cn] = ($assetCounts['nik_' . $cn] ?? 0) + $cnt;
                if ($cname !== '') $assetCounts['name_' . $cname] = ($assetCounts['name_' . $cname] ?? 0) + $cnt;
            }
        }

        if (db_table_exists($pdo, 'printers') && (db_column_exists($pdo, 'printers', 'employee_nik') || db_column_exists($pdo, 'printers', 'owner_name'))) {
            $hasNik = db_column_exists($pdo, 'printers', 'employee_nik');
            $hasOwner = db_column_exists($pdo, 'printers', 'owner_name');
            $nikPart = $hasNik ? 'pr.employee_nik' : '"" AS employee_nik';
            $ownerPart = $hasOwner ? 'pr.owner_name' : '"" AS owner_name';
            $qPr = $pdo->query("SELECT $nikPart, $ownerPart, COUNT(*) as cnt FROM printers pr GROUP BY pr.employee_nik, pr.owner_name");
            while ($r = $qPr->fetch(PDO::FETCH_ASSOC)) {
                $en = trim((string)($r['employee_nik'] ?? ''));
                $on = strtolower(trim((string)($r['owner_name'] ?? '')));
                $cnt = (int)$r['cnt'];
                if ($en !== '') $printerCounts['nik_' . $en] = ($printerCounts['nik_' . $en] ?? 0) + $cnt;
                if ($on !== '') $printerCounts['name_' . $on] = ($printerCounts['name_' . $on] ?? 0) + $cnt;
            }
        }
    }

    // Filter & Table Master Pengguna
    echo '<section class="panel">';
    echo '<div class="split" style="margin-bottom:16px;">';
    echo '<form method="get" class="actions" style="margin:0;">';
    echo '<input type="hidden" name="route" value="master_pengguna">';
    echo '<input type="hidden" name="tab" value="list">';
    echo '<input name="q" value="' . e($q) . '" placeholder="Cari NIK, Nama, atau Departemen..." style="width:260px;">';
    echo '<select name="status" onchange="this.form.submit()">';
    echo '<option value="all"' . ($statusFilter === 'all' ? ' selected' : '') . '>Semua Status</option>';
    echo '<option value="active"' . ($statusFilter === 'active' ? ' selected' : '') . '>Hanya Aktif</option>';
    echo '<option value="inactive"' . ($statusFilter === 'inactive' ? ' selected' : '') . '>Hanya Non-aktif</option>';
    echo '</select>';
    echo '<button class="btn">Cari</button>';
    if ($q !== '' || $statusFilter !== 'all') {
        echo '<a class="btn" href="' . route_url('master_pengguna') . '">Reset</a>';
    }
    echo '</form>';
    if (has_regulation('master_pengguna', 'create')) {
        echo '<button type="button" class="btn primary" onclick="openTambahPenggunaModal()" style="display:inline-flex;align-items:center;gap:6px;font-weight:600;"><span style="font-size:15px;font-weight:700;">+</span> Tambah Pengguna</button>';
    }
    echo '</div>';

    if (!$rows) {
        echo '<p class="muted">Tidak ada pengguna yang cocok dengan kriteria pencarian.</p>';
    } else {
        echo '<table><thead><tr>';
        echo '<th>NIK</th><th>Nama Pengguna</th><th>Departemen</th><th>Status</th><th>Akun Login Sistem</th><th>Aset yang Digunakan</th><th>Aksi</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $nik = (string)$r['nik'];
            $name = (string)$r['name'];
            $statusBadge = !empty($r['is_active']) ? '<span class="badge ok">Aktif</span>' : '<span class="badge danger">Non-aktif</span>';

            $accountBadge = '<span class="muted" style="font-size:12px;">Belum Ada</span>';
            if (!empty($r['user_account_id'])) {
                $roleBadge = match((string)$r['user_role']) {
                    'admin' => '<span class="badge danger">Admin</span>',
                    'maintenance_admin' => '<span class="badge ok">Admin Maintenance</span>',
                    'corrective_maintenance' => '<span class="badge" style="background:#fef3c7;color:#92400e;">Corrective</span>',
                    default => '<span class="badge">Teknisi</span>'
                };
                $accountBadge = '<strong>' . e($r['username']) . '</strong><br>' . $roleBadge;
            }

            $pCnt = ($pcCounts['nik_' . $nik] ?? 0) + ($pcCounts['name_' . strtolower($name)] ?? 0);
            $aCnt = ($assetCounts['nik_' . $nik] ?? 0) + ($assetCounts['name_' . strtolower($name)] ?? 0);
            $prCnt = ($printerCounts['nik_' . $nik] ?? 0) + ($printerCounts['name_' . strtolower($name)] ?? 0);
            $totalAssigned = $pCnt + $aCnt + $prCnt;

            $assetSummary = '<span class="muted" style="font-size:12px;">Tidak ada aset</span>';
            if ($totalAssigned > 0) {
                $badges = [];
                if ($pCnt > 0) $badges[] = "<span class='badge ok' style='font-size:11px;'>{$pCnt} PC</span>";
                if ($aCnt > 0) $badges[] = "<span class='badge' style='background:#e0f2fe;color:#0369a1;font-size:11px;'>{$aCnt} Unit Aset</span>";
                if ($prCnt > 0) $badges[] = "<span class='badge' style='background:#fef3c7;color:#92400e;font-size:11px;'>{$prCnt} Printer</span>";
                $assetSummary = implode(' ', $badges) . '<br><a href="' . route_url('master_pengguna', ['view_nik' => $nik, 'q' => $q, 'status' => $statusFilter]) . '" style="font-size:11px;color:#0284c7;font-weight:600;text-decoration:underline;">Lihat Rincian (' . $totalAssigned . ')</a>';
            }

            echo '<tr>';
            echo '<td><strong>' . e($nik) . '</strong></td>';
            echo '<td>' . e($name) . '</td>';
            echo '<td>' . e($r['department'] ?: '-') . '</td>';
            echo '<td>' . $statusBadge . '</td>';
            echo '<td>' . $accountBadge . '</td>';
            echo '<td>' . $assetSummary . '</td>';
            echo '<td><div class="actions" style="display:flex;gap:4px;flex-wrap:nowrap;align-items:center;">';
            if (has_regulation('master_pengguna', 'edit')) {
                echo btn_icon_edit(route_url('master_pengguna', ['edit_nik' => $nik, 'q' => $q, 'status' => $statusFilter]), 'Edit Pengguna');
                echo '<form method="post" style="display:inline-block;margin:0;"><input type="hidden" name="csrf" value="' . csrf_token() . '"><input type="hidden" name="action" value="toggle"><input type="hidden" name="nik" value="' . e($nik) . '"><button type="submit" class="btn-icon toggle" title="' . (!empty($r['is_active']) ? 'Nonaktifkan Pengguna' : 'Aktifkan Pengguna') . '"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path><line x1="12" y1="2" x2="12" y2="12"></line></svg></button></form>';
            }
            
            if (empty($r['user_account_id']) && has_regulation('users', 'create')) {
                echo '<button type="button" class="btn-icon add" title="Buat Akun Login Sistem" onclick="openCreateLoginModal(' . htmlspecialchars(json_encode(['nik' => $nik, 'name' => $name]), ENT_QUOTES) . ')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></button>';
            }
            
            if (has_regulation('master_pengguna', 'delete')) {
                echo btn_icon_delete(route_url('master_pengguna'), $nik, 'Hapus pengguna ' . addslashes($name) . ' dari master lokal?', 'Hapus Pengguna', 'nik');
            }
            echo '</div></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    echo '</section>';

    render_user_modals();
    render_footer();
}

function render_user_modals(): void
{
    // Modal Window Tambah Pengguna Baru
    if (has_regulation('master_pengguna', 'create')) {
        echo '<div id="modalTambahPengguna" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);backdrop-filter:blur(2px);z-index:99999;align-items:center;justify-content:center;padding:20px;">';
        echo '<div style="background:#fff;border-radius:12px;width:100%;max-width:760px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);overflow:hidden;">';
        echo '  <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;">';
        echo '    <div style="display:flex;align-items:center;gap:10px;">';
        echo '      <span style="font-size:20px;">👤</span>';
        echo '      <h3 style="margin:0;font-size:16px;font-weight:700;color:#1e293b;">+ Tambah Pengguna Baru (Manual)</h3>';
        echo '    </div>';
        echo '    <button type="button" onclick="closeTambahPenggunaModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;line-height:1;">✕</button>';
        echo '  </div>';
        echo '  <div style="padding:20px;overflow-y:auto;flex:1;">';
        echo '    <p class="muted" style="margin-top:0;margin-bottom:14px;font-size:13px;">Jika ada karyawan baru atau PIC yang belum tersinkronisasi dari portal MariaDB, tambahkan secara manual di bawah ini.</p>';
        echo '    <form method="post" id="formPenggunaBaru"><input type="hidden" name="csrf" value="' . csrf_token() . '"><input type="hidden" name="action" value="add">';
        echo '    <div class="grid three">';
        echo '      <label>NIK (Nomor Induk Karyawan) *<input name="nik" id="modalInputNik" required placeholder="Contoh: 2024001"></label>';
        echo '      <label>Nama Pengguna Lengkap *<input name="name" required placeholder="Nama Karyawan"></label>';
        echo '      <label>Departemen / Divisi<input name="department" placeholder="Contoh: Finance / Logistik"></label>';
        echo '    </div>';
        
        echo '    <div style="margin:14px 0 16px 0;padding:12px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc;">';
        echo '      <label style="margin-top:0;cursor:pointer;"><input type="checkbox" name="create_login" value="1" id="chkCreateLogin" onchange="toggleLoginInputs()" style="width:auto;"> Sekaligus buatkan Akun Login Sistem (Aplikasi PcConnect)</label>';
        echo '      <div id="loginInputs" style="display:none;margin-top:10px;" class="grid three">';
        echo '        <label>Username Login<input name="username" placeholder="Default sama dengan NIK"></label>';
        echo '        <label>Role Akun<select name="role"><option value="technician">Teknisi</option><option value="corrective_maintenance">Corrective Maintenance</option><option value="maintenance_admin">Admin Maintenance</option><option value="admin">Administrator</option></select></label>';
        echo '        <label>Password Awal (Min 6 Karakter)<input type="password" name="password" placeholder="Password"></label>';
        echo '      </div>';
        echo '    </div>';

        echo '    <div class="split" style="margin-top:14px;align-items:center;">';
        echo '      <div></div>';
        echo '      <div class="actions" style="display:flex;gap:8px;">';
        echo '        <button type="button" class="btn" onclick="closeTambahPenggunaModal()">Batal</button>';
        echo '        <button class="btn primary">Simpan Pengguna Baru</button>';
        echo '      </div>';
        echo '    </div>';
        echo '    </form>';
        echo '  </div>';
        echo '</div>';
        echo '</div>';
    }

    // Quick Modal Script for Create Login
    echo '<div id="modalLogin" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;">';
    echo '<div style="background:#fff;border-radius:8px;max-width:440px;width:90%;padding:24px;box-shadow:0 20px 40px rgba(0,0,0,0.3);">';
    echo '<h3 id="modalLoginTitle" style="margin-top:0;">Buat Akun Login Sistem</h3>';
    echo '<form method="post"><input type="hidden" name="csrf" value="' . csrf_token() . '"><input type="hidden" name="action" value="create_login_for"><input type="hidden" id="modalNik" name="nik"><input type="hidden" id="modalName" name="name">';
    echo '<label>Username<input id="modalUsername" name="username" required></label>';
    echo '<label>Role<select name="role"><option value="technician">Teknisi</option><option value="corrective_maintenance">Corrective Maintenance</option><option value="maintenance_admin">Admin Maintenance</option><option value="admin">Administrator</option></select></label>';
    echo '<label>Password (Min 6 Karakter)<input type="password" name="password" required minlength="6"></label>';
    echo '<div class="actions" style="margin-top:16px;justify-content:flex-end;"><button type="button" class="btn" onclick="closeCreateLoginModal()">Batal</button><button class="btn primary">Buat Akun</button></div>';
    echo '</form></div></div>';

    echo '<script>
    function openTambahPenggunaModal(){
        var m = document.getElementById("modalTambahPengguna");
        if(m){
            m.style.display = "flex";
            var nikInp = document.getElementById("modalInputNik");
            if(nikInp) nikInp.focus();
        }
    }
    function closeTambahPenggunaModal(){
        var m = document.getElementById("modalTambahPengguna");
        if(m) m.style.display = "none";
    }
    function toggleLoginInputs(){
        var chk = document.getElementById("chkCreateLogin");
        var div = document.getElementById("loginInputs");
        if(chk && div){ div.style.display = chk.checked ? "grid" : "none"; }
    }
    function openCreateLoginModal(data){
        document.getElementById("modalNik").value = data.nik;
        document.getElementById("modalName").value = data.name;
        document.getElementById("modalUsername").value = data.nik.toLowerCase();
        document.getElementById("modalLoginTitle").textContent = "Buat Akun Login: " + data.name + " (" + data.nik + ")";
        document.getElementById("modalLogin").style.display = "flex";
    }
    function closeCreateLoginModal(){
        document.getElementById("modalLogin").style.display = "none";
    }
    </script>';
}
