# -*- coding: utf-8 -*-
with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's locate the region to replace in handle_route_asset_items
pos_header = text.find("    $addBtn = has_regulation('asset_items', 'create', $user)")
end_header = text.find("    // Form Filter: Wajib Pilih Komoditas & Kategori", pos_header)

old_snippet = text[pos_header:end_header]

stats_calc = """    // Statistik Ringkasan Unit Aset
    $stats = [
        'active' => 0,
        'inactive' => 0,
        'maintenance' => 0,
        'total' => 0,
    ];
    try {
        $stats['active'] = (int)$pdo->query("SELECT COUNT(*) FROM asset_items WHERE LOWER(status) IN ('active', 'aktif', 'available', 'tersedia')")->fetchColumn();
        $stats['inactive'] = (int)$pdo->query("SELECT COUNT(*) FROM asset_items WHERE LOWER(status) IN ('inactive', 'tidak aktif', 'rusak', 'scrapped', 'disposed')")->fetchColumn();
        $stats['maintenance'] = (int)$pdo->query("SELECT COUNT(*) FROM asset_items WHERE LOWER(status) IN ('maintenance', 'perbaikan', 'repair', 'servis')")->fetchColumn();
        $stats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM asset_items WHERE LOWER(status) <> 'deleted'")->fetchColumn();
    } catch (Throwable $e) {}

    $addBtn = has_regulation('asset_items', 'create', $user) ? '<a class="btn primary" href="' . route_url('asset_item_form', $masterItemId ? ['master_item_id' => $masterItemId] : []) . '" style="display:inline-flex;align-items:center;gap:6px;font-weight:bold;"><span>+</span> Tambah Unit Aset</a>' : '';
    $importBtn = has_regulation('asset_items', 'create', $user) ? '<a class="btn" href="' . route_url('asset_legacy_import') . '" style="display:inline-flex;align-items:center;gap:6px;background:#f0fdf4;border-color:#86efac;color:#166534;font-weight:600;"><span>Excel</span> Import Aset Lama</a>' : '';
    $exportBtn = '<a class="btn" href="' . route_url('export_excel', array_merge(['type' => 'asset_items'], $showBackdoor ? ['status' => 'deleted'] : [])) . '" style="display:inline-flex;align-items:center;gap:6px;">Export Excel Unit Aset</a>';

    echo '<section class="panel">';
    
    if ($showBackdoor) {
        echo '<div style="background:#fee2e2;border:1px solid #fecaca;padding:12px 18px;border-radius:8px;margin-bottom:16px;color:#991b1b;display:flex;align-items:center;justify-content:space-between;gap:12px;">'
            . '<div><strong>Backdoor History Mode (Unit Aset Deleted):</strong> Menampilkan unit aset yang telah berstatus <code>deleted</code>. Unit-unit ini disembunyikan dari seluruh daftar operasional.</div>'
            . '<a href="' . route_url('asset_items') . '" class="btn" style="white-space:nowrap;font-size:12px;">Kembali ke List Normal</a>'
            . '</div>';
    }

    echo '<div class="split" style="align-items:center;margin-bottom:16px;">'
        . '  <div>'
        . '    <h1 style="margin:0;display:flex;align-items:center;gap:8px;">Unit Aset</h1>'
        . '  </div>'
        . '  <div class="actions" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">'
        . $importBtn . $exportBtn . $addBtn
        . '  </div>'
        . '</div>'

        // Kartu Ringkasan (Rounded, Warna-warni, & Transparancy Icon - Sama persis seperti Peminjaman Aset)
        . '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:20px;">'
        // 1. Aset Aktif (Biru / Sky)
        . '  <div style="background:linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);border:1px solid #bae6fd;border-left:4px solid #0284c7;border-radius:14px;padding:12px 16px;position:relative;overflow:hidden;box-shadow:0 1px 3px rgba(2,132,199,0.06);">'
        . '    <div style="position:absolute;right:-10px;top:-10px;width:75px;height:75px;opacity:0.16;transform:rotate(-10deg);pointer-events:none;">'
        . '      <svg viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>'
        . '    </div>'
        . '    <div style="position:relative;z-index:1;">'
        . '      <div style="font-size:24px;font-weight:800;color:#0284c7;margin-top:3px;line-height:1.2;">' . $stats['active'] . ' <span style="font-size:12.5px;font-weight:600;color:#0284c7;opacity:0.75;">unit aktif</span></div>'
        . '    </div>'
        . '  </div>'
        // 2. Dalam Maintenance (Merah / Rose)
        . '  <div style="background:' . ($stats['maintenance'] > 0 ? 'linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%)' : '#f8fafc') . ';border:1px solid ' . ($stats['maintenance'] > 0 ? '#fecdd3' : '#e2e8f0') . ';border-left:4px solid ' . ($stats['maintenance'] > 0 ? '#e11d48' : '#94a3b8') . ';border-radius:14px;padding:12px 16px;position:relative;overflow:hidden;box-shadow:0 1px 3px rgba(225,29,72,0.06);">'
        . '    <div style="position:absolute;right:-10px;top:-10px;width:75px;height:75px;opacity:' . ($stats['maintenance'] > 0 ? '0.16' : '0.08') . ';transform:rotate(-10deg);pointer-events:none;">'
        . '      <svg viewBox="0 0 24 24" fill="none" stroke="' . ($stats['maintenance'] > 0 ? '#e11d48' : '#64748b') . '" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>'
        . '    </div>'
        . '    <div style="position:relative;z-index:1;">'
        . '      <div style="font-size:24px;font-weight:800;color:' . ($stats['maintenance'] > 0 ? '#e11d48' : '#64748b') . ';margin-top:3px;line-height:1.2;">' . $stats['maintenance'] . ' <span style="font-size:12.5px;font-weight:600;color:' . ($stats['maintenance'] > 0 ? '#e11d48' : '#64748b') . ';opacity:0.75;">unit servis</span></div>'
        . '    </div>'
        . '  </div>'
        // 3. Tidak Aktif / Rusak (Ungu / Fuchsia)
        . '  <div style="background:linear-gradient(135deg, #fdf4ff 0%, #fae8ff 100%);border:1px solid #f5d0fe;border-left:4px solid #a21caf;border-radius:14px;padding:12px 16px;position:relative;overflow:hidden;box-shadow:0 1px 3px rgba(162,28,175,0.06);">'
        . '    <div style="position:absolute;right:-10px;top:-10px;width:75px;height:75px;opacity:0.16;transform:rotate(-10deg);pointer-events:none;">'
        . '      <svg viewBox="0 0 24 24" fill="none" stroke="#a21caf" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>'
        . '    </div>'
        . '    <div style="position:relative;z-index:1;">'
        . '      <div style="font-size:24px;font-weight:800;color:#a21caf;margin-top:3px;line-height:1.2;">' . $stats['inactive'] . ' <span style="font-size:12.5px;font-weight:600;color:#a21caf;opacity:0.75;">tidak aktif</span></div>'
        . '    </div>'
        . '  </div>'
        // 4. Total Keseluruhan (Hijau / Emerald)
        . '  <div style="background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);border:1px solid #bbf7d0;border-left:4px solid #16a34a;border-radius:14px;padding:12px 16px;position:relative;overflow:hidden;box-shadow:0 1px 3px rgba(22,163,74,0.06);">'
        . '    <div style="position:absolute;right:-10px;top:-10px;width:75px;height:75px;opacity:0.16;transform:rotate(-10deg);pointer-events:none;">'
        . '      <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>'
        . '    </div>'
        . '    <div style="position:relative;z-index:1;">'
        . '      <div style="font-size:24px;font-weight:800;color:#16a34a;margin-top:3px;line-height:1.2;">' . $stats['total'] . ' <span style="font-size:12.5px;font-weight:600;color:#16a34a;opacity:0.75;">total unit</span></div>'
        . '    </div>'
        . '  </div>'
        . '</div>\n\n'
"""

new_text = text[:pos_header] + stats_calc + text[end_header:]
with open('app/modules/asset.php', 'w', encoding='utf-8') as f:
    f.write(new_text)

print("Updated asset.php header successfully!")

