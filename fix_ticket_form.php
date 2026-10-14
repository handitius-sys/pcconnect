<?php
// Update corrective.php
$corrFile = 'app/modules/corrective.php';
$corr = file_get_contents($corrFile);

// 1. Update POST insert logic in handle_route_ticket_form
$postOld = <<<EOT
        \$stmt = \$pdo->prepare("INSERT INTO corrective_tickets (
            ticket_code, asset_category, asset_item_id, maintenance_asset_id, pc_id, company_id, 
            location_label, vehicle_odometer_km, reporter_name, reporter_nik, reporter_contact, 
            issue_category, priority, subject, description, photo_before, status, assigned_technician_name
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?)");

        \$stmt->execute([
            \$code, \$assetCat, \$assetItemId, \$maintAssetId, \$pcId, \$companyId,
            \$location, \$odometer, \$reporterName, \$reporterNik, \$reporterContact,
            \$issueCat, \$priority, \$subject, \$desc, \$photoBefore,
            (\$user['role'] === 'technician' ? \$user['name'] : null)
        ]);
        \$ticketId = (int)\$pdo->lastInsertId();
EOT;

$postNew = <<<EOT
        \$handledByType = trim((string)(\$_POST['handled_by_type'] ?? 'internal'));
        \$handledByUserId = (int)(\$_POST['handled_by_user_id'] ?? 0) ?: null;
        \$handledByWorkshopId = (int)(\$_POST['handled_by_workshop_id'] ?? 0) ?: null;
        if (\$handledByType !== 'workshop') { \$handledByWorkshopId = null; }
        if (\$handledByType !== 'internal') { \$handledByUserId = null; }

        \$stmt = \$pdo->prepare("INSERT INTO corrective_tickets (
            ticket_code, asset_category, asset_item_id, maintenance_asset_id, pc_id, company_id, 
            location_label, vehicle_odometer_km, reporter_name, reporter_nik, reporter_contact, 
            issue_category, priority, subject, description, photo_before, status, assigned_technician_name,
            handled_by_type, handled_by_user_id, handled_by_workshop_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, ?, ?, ?)");

        \$stmt->execute([
            \$code, \$assetCat, \$assetItemId, \$maintAssetId, \$pcId, \$companyId,
            \$location, \$odometer, \$reporterName, \$reporterNik, \$reporterContact,
            \$issueCat, \$priority, \$subject, \$desc, \$photoBefore,
            (\$user['role'] === 'technician' ? \$user['name'] : null),
            \$handledByType, \$handledByUserId, \$handledByWorkshopId
        ]);
        \$ticketId = (int)\$pdo->lastInsertId();

        // Save requested actions
        if (!empty(\$_POST['requested_actions']) && is_array(\$_POST['requested_actions'])) {
            \$actStmt = \$pdo->prepare("INSERT INTO corrective_ticket_actions (ticket_id, job_action_id, action_title) VALUES (?, ?, ?)");
            foreach (\$_POST['requested_actions'] as \$actId) {
                \$aRow = \$pdo->query("SELECT action_title FROM corrective_action_types WHERE id = " . (int)\$actId)->fetch();
                if (\$aRow) {
                    \$actStmt->execute([\$ticketId, (int)\$actId, \$aRow['action_title']]);
                }
            }
        }
EOT;

if (str_contains($corr, $postOld)) {
    $corr = str_replace($postOld, $postNew, $corr);
    echo "Replaced POST insert logic successfully.\n";
} else {
    echo "POST insert search string not matched directly, using preg_replace...\n";
    $pattern = '/\$stmt = \$pdo->prepare\("INSERT INTO corrective_tickets.*?\);/s';
    $corr = preg_replace($pattern, \$postNew, $corr, 1);
}

// 2. Inject Penanganan Oleh section into form
$actionsDivOld = <<<EOT
            <div class="actions" style="margin-top:20px;">
                <a class="btn" href="<?= route_url('tickets') ?>">Batal</a>
                <button class="btn primary">Simpan & Terbitkan Tiket</button>
            </div>
EOT;

$handledBySection = <<<EOT
            <?php
            // List mekanik internal & workshop
            \$mechanics = \$pdo->query("SELECT id, name FROM users WHERE role='corrective_maintenance' AND is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            \$workshops = \$pdo->query("SELECT id, workshop_name FROM corrective_workshops ORDER BY workshop_name")->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <h2>4. Penanganan Oleh</h2>
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
                            <?php foreach (\$mechanics as \$mec): ?>
                                <option value="<?= \$mec['id'] ?>"><?= e(\$mec['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div id="handledWorkshopWrap" style="display:none;">
                    <label>Pilih Bengkel / Workshop
                        <select name="handled_by_workshop_id" style="width:100%;">
                            <option value="">-- Pilih Bengkel --</option>
                            <?php foreach (\$workshops as \$wks): ?>
                                <option value="<?= \$wks['id'] ?>"><?= e(\$wks['workshop_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </div>

            <div class="actions" style="margin-top:20px;">
                <a class="btn" href="<?= route_url('tickets') ?>">Batal</a>
                <button class="btn primary">Simpan & Terbitkan Tiket</button>
            </div>
EOT;

if (str_contains($corr, $actionsDivOld)) {
    $corr = str_replace($actionsDivOld, $handledBySection, $corr);
    echo "Injected Penanganan Oleh section successfully.\n";
} else {
    echo "actionsDivOld not matched.\n";
}

// 3. Update JavaScript block in ticket form
$scriptOld = <<<EOT
    <script>
    function onMaintAssetChange() {
        var sel = document.getElementById('maintAssetSelect');
        var opt = sel.options[sel.selectedIndex];
        
        var loc = opt ? opt.getAttribute('data-loc') : '';
        var kmWrap = document.getElementById('vehicleKmWrapper');
        var locInput = document.getElementById('locInput');

        if (loc && !locInput.value) {
            locInput.value = loc;
        }
        if (isVehicle) {
            kmWrap.style.display = 'block';
        } else {
            kmWrap.style.display = 'none';
        }
    }
    onMaintAssetChange();
    </script>
EOT;

$scriptNew = <<<EOT
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

    async function onDynGroupChange() {
        var gid = document.getElementById('dynGroupId').value;
        var tSel = document.getElementById('dynTypeId');
        var aSel = document.getElementById('maintAssetSelect');
        var actWrap = document.getElementById('actionListWrapper');
        
        tSel.innerHTML = '<option value="">-- Loading Kategori... --</option>';
        aSel.innerHTML = '<option value="">-- Pilih Kategori Dulu --</option>';
        if (actWrap) actWrap.style.display = 'none';

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
        var actWrap = document.getElementById('actionListWrapper');
        var jdSpan = document.getElementById('jdNameSpan');
        var actCheckboxes = document.getElementById('actionCheckboxes');

        aSel.innerHTML = '<option value="">-- Loading Unit Aset... --</option>';
        if (actWrap) actWrap.style.display = 'none';

        if (!gid || !tid) {
            aSel.innerHTML = '<option value="">-- Pilih Kategori Dulu --</option>';
            return;
        }

        try {
            // Load Assets
            let res = await fetch('index.php?route=api_corrective_data&action=get_assets&group_id=' + gid + '&type_id=' + tid);
            let assets = await res.json();

            aSel.innerHTML = '<option value="">-- Pilih Unit Aset --</option>';
            if (Array.isArray(assets) && assets.length > 0) {
                assets.forEach(function(m) {
                    let badge = (m.asset_mode === 'group') ? '[📦 INDUK BUNDLE] ' : ((m.asset_mode === 'child') ? '[🔗 ANGGOTA] ' : '');
                    let desc = badge + m.asset_code + ' - ' + m.asset_name + (m.custodian_name ? ' (' + m.custodian_name + ')' : '');
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
            
            if (actionsData && actionsData.desk_name && actionsData.actions && actionsData.actions.length > 0) {
                if (jdSpan) jdSpan.textContent = actionsData.desk_name;
                if (actCheckboxes) {
                    actCheckboxes.innerHTML = '';
                    actionsData.actions.forEach(function(a) {
                        let lbl = document.createElement('label');
                        lbl.style.display = 'flex';
                        lbl.style.alignItems = 'flex-start';
                        lbl.style.gap = '8px';
                        lbl.style.fontWeight = 'normal';
                        lbl.style.cursor = 'pointer';
                        
                        let chk = document.createElement('input');
                        chk.type = 'checkbox';
                        chk.name = 'requested_actions[]';
                        chk.value = a.id;
                        chk.style.marginTop = '4px';

                        let sp = document.createElement('span');
                        sp.textContent = a.action_title;

                        lbl.appendChild(chk);
                        lbl.appendChild(sp);
                        actCheckboxes.appendChild(lbl);
                    });
                }
                if (actWrap) actWrap.style.display = 'block';
            }
        } catch (e) {
            console.error(e);
        }
    }

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
    </script>
EOT;

if (str_contains($corr, $scriptOld)) {
    $corr = str_replace($scriptOld, $scriptNew, $corr);
    echo "Replaced script block successfully.\n";
} else {
    echo "scriptOld not matched.\n";
}

file_put_contents($corrFile, $corr);
echo "Updated corrective.php.\n";
