# Update app/modules/regulations.php to handle subgroups in menu_order tab

with open('app/modules/regulations.php', 'r', encoding='utf-8') as f:
    code = f.read()

target_loop_start = """                <div style="display:flex;flex-direction:column;gap:20px;">
                    <?php foreach ($menuStructure as $gKey => $group): ?>"""

target_loop_end = """                    <?php endforeach; ?>
                </div>

                <div class="split" style="margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0;align-items:center;">"""

idx_start = code.find(target_loop_start)
idx_end = code.find(target_loop_end)

assert idx_start != -1, "target_loop_start not found"
assert idx_end != -1, "target_loop_end not found"

new_loop_block = """                <div style="display:flex;flex-direction:column;gap:20px;">
                    <?php foreach ($menuStructure as $gKey => $group): ?>
                        <div style="border:1.5px solid #cbd5e1;border-radius:10px;overflow:hidden;background:#fff;box-shadow:0 2px 4px rgba(0,0,0,0.03);">
                            <!-- Header Kelompok / Accordion Utama -->
                            <div style="background:#f1f5f9;padding:12px 18px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #cbd5e1;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span style="font-size:18px;">📁</span>
                                    <strong style="font-size:15px;color:#0f172a;"><?= e($group['label']) ?> (Accordion Kelompok Utama)</strong>
                                    <span class="badge" style="background:#e2e8f0;color:#334155;font-size:11px;">Key: <?= e($gKey) ?></span>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span style="font-size:12px;font-weight:600;color:#64748b;">Urutan Accordion Utama:</span>
                                    <input type="number" name="menu_orders[<?= e($gKey) ?>]" value="<?= (int)($group['order'] ?? 10) ?>" min="1" max="999" style="width:70px;padding:4px 8px;font-weight:700;text-align:center;border:1px solid #94a3b8;border-radius:6px;">
                                </div>
                            </div>

                            <!-- Tabel Item Langsung di bawah Accordion Utama (jika ada, contoh: Pendataan PC) -->
                            <?php if (!empty($group['items'])): ?>
                            <div style="padding:0;">
                                <table style="width:100%;margin:0;border:none;">
                                    <thead>
                                        <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                                            <th style="width:50px;text-align:center;">No. Urut</th>
                                            <th style="width:100px;text-align:center;">Geser</th>
                                            <th>Nama Menu Langsung</th>
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
                            <?php endif; ?>

                            <!-- Sub-Accordion Bertingkat di dalam Accordion Utama (jika ada, e.g. Master Aset, Lokasi & Pengguna, Pemeliharaan) -->
                            <?php if (!empty($group['subgroups'])): ?>
                                <div style="background:#f8fafc;padding:12px 14px;border-top:1px solid #cbd5e1;display:flex;flex-direction:column;gap:12px;">
                                    <?php foreach ($group['subgroups'] as $sgKey => $subgroup): ?>
                                        <div style="border:1px solid #94a3b8;border-radius:8px;overflow:hidden;background:#fff;">
                                            <!-- Header Sub-Accordion -->
                                            <div style="background:#e0f2fe;padding:10px 14px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #bae6fd;">
                                                <div style="display:flex;align-items:center;gap:8px;">
                                                    <span style="font-size:15px;color:#0284c7;">📁</span>
                                                    <strong style="font-size:14px;color:#0369a1;"><?= e($subgroup['label']) ?> (Sub-Accordion)</strong>
                                                    <span class="badge" style="background:#bae6fd;color:#0369a1;font-size:10px;">Subgroup: <?= e($sgKey) ?></span>
                                                </div>
                                                <div style="display:flex;align-items:center;gap:6px;">
                                                    <span style="font-size:11px;font-weight:600;color:#0369a1;">Urutan Sub-Accordion:</span>
                                                    <input type="number" name="menu_orders[<?= e($sgKey) ?>]" value="<?= (int)($subgroup['order'] ?? 10) ?>" min="1" max="999" style="width:65px;padding:3px 6px;font-weight:700;text-align:center;border:1px solid #0284c7;border-radius:5px;background:#fff;color:#0369a1;">
                                                </div>
                                            </div>

                                            <!-- Tabel Item di dalam Sub-Accordion -->
                                            <div style="padding:0;">
                                                <table style="width:100%;margin:0;border:none;">
                                                    <thead>
                                                        <tr style="background:#fff;border-bottom:1px solid #f1f5f9;">
                                                            <th style="width:50px;text-align:center;font-size:11px;">No. Urut</th>
                                                            <th style="width:100px;text-align:center;font-size:11px;">Geser</th>
                                                            <th style="font-size:11px;">Nama Menu di dalam Sub-Accordion</th>
                                                            <th style="width:180px;font-size:11px;">Rute / Key</th>
                                                            <th style="width:160px;font-size:11px;">Izin Hak Akses</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="tbody-<?= e($sgKey) ?>">
                                                        <?php foreach ($subgroup['items'] as $sgiKey => $sgi): ?>
                                                            <tr style="border-bottom:1px solid #f8fafc;">
                                                                <td style="text-align:center;">
                                                                    <input type="number" name="menu_orders[<?= e($sgiKey) ?>]" value="<?= (int)($sgi['order'] ?? 10) ?>" min="1" max="999" class="item-order-input" style="width:65px;padding:5px;font-weight:700;text-align:center;border:1.5px solid #0284c7;border-radius:6px;color:#0369a1;">
                                                                </td>
                                                                <td style="text-align:center;">
                                                                    <div style="display:inline-flex;gap:4px;">
                                                                        <button type="button" class="btn" onclick="moveRowOrder(this, -5)" title="Pindahkan ke atas" style="padding:3px 7px;font-size:11px;line-height:1;">▲</button>
                                                                        <button type="button" class="btn" onclick="moveRowOrder(this, 5)" title="Pindahkan ke bawah" style="padding:3px 7px;font-size:11px;line-height:1;">▼</button>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <span style="font-weight:600;color:#1e293b;"><?= e($sgi['label']) ?></span>
                                                                </td>
                                                                <td>
                                                                    <code style="font-size:12px;background:#f1f5f9;padding:2px 6px;border-radius:4px;color:#475569;"><?= e($sgiKey) ?></code>
                                                                </td>
                                                                <td>
                                                                    <?php if (($sgi['reg'] ?? '') === 'admin_only'): ?>
                                                                        <span class="badge danger" style="font-size:11px;">Admin Saja</span>
                                                                    <?php else: ?>
                                                                        <span class="badge ok" style="font-size:11px;">Regulasi: <?= e($sgi['reg'] ?? '-') ?></span>
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
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="split" style="margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0;align-items:center;">"""

code = code[:idx_start] + new_loop_block + code[idx_end + len(target_loop_end):]

with open('app/modules/regulations.php', 'w', encoding='utf-8') as f:
    f.write(code)

print("Updated app/modules/regulations.php successfully")

