<?php
declare(strict_types=1);

function handle_route_asset_mutations(PDO $pdo): void
{
    $user = require_login();
    if (!is_full_admin($user) && !has_regulation('asset_mutations', 'view', $user)) {
        http_response_code(403);
        exit('Akses ditolak.');
    }

    $action = (string)($_POST['action'] ?? '');
    
    // Check if handling form submit for mutation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'mutate') {
        verify_csrf();
        require_regulation('asset_mutations', 'create');
        
        $assetItemId = (int)($_POST['asset_item_id'] ?? 0);
        $newLocation = trim((string)($_POST['new_location'] ?? ''));
        $newCompanyId = (int)($_POST['new_company_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        $pic = trim((string)($_POST['pic'] ?? ($user['name'] ?? 'Admin')));
        $date = normalize_date_input((string)($_POST['movement_date'] ?? date('Y-m-d')));

        $item = asset_item_row($pdo, $assetItemId);
        if (!$item) {
            flash('Asset item tidak ditemukan.', 'err');
            redirect_to('asset_mutations', $_GET);
        }

        if ($newLocation === '' && $newCompanyId <= 0) {
            flash('Tidak ada data mutasi yang diisi (Lokasi atau Company wajib dipilih).', 'err');
            redirect_to('asset_mutations', $_GET);
        }

        try {
            $pdo->beginTransaction();
            
            // Gather all asset items to update (parent + children if bundle)
            $itemsToUpdate = [$item];
            
            if (in_array($item['asset_mode'], ['standalone', 'parent'], true)) {
                $stmt = $pdo->prepare('SELECT child_asset_item_id FROM asset_item_members WHERE parent_asset_item_id = ? AND detached_at IS NULL');
                $stmt->execute([$assetItemId]);
                $childrenIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($childrenIds as $cid) {
                    $cItem = asset_item_row($pdo, (int)$cid);
                    if ($cItem) $itemsToUpdate[] = $cItem;
                }
            }

            foreach ($itemsToUpdate as $target) {
                $updFields = [];
                $updParams = [];
                $oldLoc = $target['location_label'] ?? null;
                $oldCompany = $target['company_id'] ?? null;
                
                $doUpdate = false;

                if ($newLocation !== '' && $newLocation !== $oldLoc) {
                    $updFields[] = 'location_label=?';
                    $updParams[] = $newLocation;
                    $doUpdate = true;
                }
                
                if ($newCompanyId > 0 && (int)$oldCompany !== $newCompanyId) {
                    $updFields[] = 'company_id=?';
                    $updParams[] = $newCompanyId;
                    $doUpdate = true;
                }
                
                if ($doUpdate) {
                    $updParams[] = $target['id'];
                    $pdo->prepare('UPDATE asset_items SET ' . implode(', ', $updFields) . ' WHERE id=?')->execute($updParams);
                    
                    $realReason = $reason ?: 'Mutasi Aset';
                    if ($target['id'] !== $item['id']) {
                        $realReason .= ' (Otomatis mengikuti parent/bundle)';
                    }
                    
                    $stmtMove = $pdo->prepare('INSERT INTO asset_movements (asset_item_id, from_company_id, to_company_id, from_location_label, to_location_label, movement_date, reason, pic) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmtMove->execute([
                        $target['id'],
                        $oldCompany,
                        ($newCompanyId > 0 ? $newCompanyId : $oldCompany),
                        $oldLoc,
                        ($newLocation !== '' ? $newLocation : $oldLoc),
                        $date,
                        $realReason,
                        $pic
                    ]);
                }
            }
            
            $pdo->commit();
            flash('Mutasi aset berhasil disimpan.');
        } catch (Exception $e) {
            $pdo->rollBack();
            flash('Gagal mutasi: ' . $e->getMessage(), 'err');
        }
        
        redirect_to('asset_mutations', $_GET);
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
        verify_csrf();
        require_regulation('asset_mutations', 'delete');
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId > 0) {
            $pdo->prepare('DELETE FROM asset_movements WHERE id=?')->execute([$delId]);
            flash('Riwayat mutasi berhasil dihapus.');
        }
        redirect_to('asset_mutations', $_GET);
    }

    $filterGroup = (int)($_GET['group_id'] ?? 0);
    $filterType = (int)($_GET['type_id'] ?? 0);
    $q = trim((string)($_GET['q'] ?? ''));

    render_header('Mutasi Aset', $user);
    echo asset_nav_html();

    echo '<section class="panel">';
    echo '<h2>Filter Mutasi Aset</h2>';
    echo '<form method="get" class="actions" style="margin-bottom: 16px;">';
    echo '<input type="hidden" name="route" value="asset_mutations">';
    echo '<label>Komoditas<select name="group_id" onchange="this.form.submit()">' . asset_group_options($pdo, $filterGroup, true, 'Pilih Komoditas') . '</select></label>';
    
    if ($filterGroup > 0) {
        echo '<label>Kategori<select name="type_id" onchange="this.form.submit()">' . asset_type_options($pdo, $filterGroup, $filterType, true, 'Semua Kategori') . '</select></label>';
    }
    echo '<label>Cari Aset<input type="text" name="q" value="' . e($q) . '" placeholder="Kode / Nama Aset"></label>';
    
    echo '<button class="btn primary">Tampilkan</button>';
    if ($filterGroup > 0 || $filterType > 0 || $q !== '') {
        echo '<a href="' . route_url('asset_mutations') . '" class="btn">Reset</a>';
    }
    echo '</form>';
    
    if ($filterGroup <= 0 && $filterType <= 0) {
        echo '<p class="muted">Pilih Komoditas dan Kategori terlebih dahulu untuk menampilkan aset.</p>';
    } else {
        // Query assets
        $sql = "SELECT a.*, mi.item_name, g.group_name, t.type_name, c.company_name, m.maintenance_asset_code
                FROM asset_items a
                LEFT JOIN asset_master_items mi ON a.master_item_id = mi.id
                LEFT JOIN asset_groups g ON mi.group_id = g.id
                LEFT JOIN asset_types t ON mi.type_id = t.id
                LEFT JOIN asset_companies c ON a.company_id = c.id
                LEFT JOIN asset_bundle_members bm ON a.id = bm.asset_item_id AND bm.detached_at IS NULL
                LEFT JOIN asset_bundles m ON bm.bundle_id = m.id
                WHERE 1=1";
        $params = [];
        
        if ($filterGroup > 0) {
            $sql .= " AND mi.group_id = ?";
            $params[] = $filterGroup;
        }
        if ($filterType > 0) {
            $sql .= " AND mi.type_id = ?";
            $params[] = $filterType;
        }
        if ($q !== '') {
            $sql .= " AND (a.asset_code LIKE ? OR mi.item_name LIKE ?)";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }
        
        $sql .= " ORDER BY a.asset_code ASC LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!$assets) {
            echo '<p class="muted">Tidak ada unit aset yang sesuai filter.</p>';
        } else {
            echo '<table>';
            echo '<thead><tr>
                <th>Aset</th>
                <th>Company & Lokasi Saat Ini</th>
                <th>Mode Aset</th>
                <th>Aksi Mutasi</th>
            </tr></thead><tbody>';
            
            $canCreate = has_regulation('asset_mutations', 'create', $user);
            
            foreach ($assets as $row) {
                echo '<tr>';
                echo '<td><strong>' . e($row['asset_code']) . '</strong><br><span class="muted">' . e($row['item_name']) . '</span></td>';
                echo '<td>' . e($row['company_name'] ?: '-') . '<br><span class="muted">' . e($row['location_label'] ?: '-') . '</span></td>';
                echo '<td>' . e($row['asset_mode']) . '</td>';
                
                echo '<td>';
                if ($canCreate) {
                    echo '<button type="button" class="btn small primary" onclick="openMutationForm(' . $row['id'] . ', \'' . e($row['asset_code']) . '\', ' . (int)$row['company_id'] . ', \'' . e($row['location_label'] ?? '') . '\')"><span style="display:flex;align-items:center;gap:4px;">' . svg_icon_move(14) . ' Mutasi</span></button>';
                }
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            
            // Render modal for mutation
            echo '
            <div id="mutationModal" class="modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
                <div class="panel" style="width:100%;max-width:500px;margin:20px;">
                    <h3>Mutasi Aset <span id="mutAssetCode"></span></h3>
                    <form method="post">
                        <input type="hidden" name="csrf" value="' . csrf_token() . '">
                        <input type="hidden" name="action" value="mutate">
                        <input type="hidden" name="asset_item_id" id="mutAssetId" value="">
                        
                        <label>Company Tujuan
                            <select name="new_company_id" id="mutCompanyId">
                                <option value="0">-- Tetap (Tidak Berubah) --</option>
                                ' . asset_company_options($pdo) . '
                            </select>
                        </label>
                        
                        <label>Lokasi Tujuan
                            <input type="text" name="new_location" id="mutLocation" placeholder="-- Tetap (Tidak Berubah) --" value="">
                        </label>
                        
                        <label>Tanggal Mutasi
                            <input type="date" name="movement_date" value="' . date('Y-m-d') . '" required>
                        </label>
                        
                        <label>Alasan / Keterangan Mutasi
                            <input type="text" name="reason" placeholder="Contoh: Pindah departemen, Relokasi pabrik" required>
                        </label>
                        
                        <label>PIC (Penanggung Jawab)
                            <input type="text" name="pic" value="' . e($user['name'] ?? 'Admin') . '" required>
                        </label>
                        
                        <div class="actions" style="margin-top:16px;">
                            <button type="button" class="btn" onclick="document.getElementById(\'mutationModal\').style.display=\'none\'">Batal</button>
                            <button type="submit" class="btn primary">Simpan Mutasi</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
            function openMutationForm(id, code, compId, loc) {
                document.getElementById("mutAssetId").value = id;
                document.getElementById("mutAssetCode").innerText = code;
                document.getElementById("mutCompanyId").value = compId > 0 ? compId : 0;
                document.getElementById("mutLocation").value = loc;
                document.getElementById("mutationModal").style.display = "flex";
            }
            </script>
            ';
            
            // Helper for svg_icon_move if not already defined globally
            if (!function_exists('svg_icon_move')) {
                function svg_icon_move(int $size = 16): string {
                    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 9-3 3 3 3"/><path d="M2 12h20"/><path d="m19 9 3 3-3 3"/></svg>';
                }
            }
        }
    }
    
    echo '</section>';
    
    render_footer();
}

