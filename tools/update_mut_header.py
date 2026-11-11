# -*- coding: utf-8 -*-
with open('app/modules/asset_mutations.php', 'r', encoding='utf-8') as f:
    text = f.read()

pos_header = text.find("    echo '<section class=\"panel\">';")
end_header = text.find("    // Form Filter: Wajib Pilih Komoditas & Kategori", pos_header)

stats_calc = """    // Hitung statistik ringkasan mutasi aset
    $stats = [
        'total' => 0,
        'this_month' => 0,
        'company' => 0,
        'custodian' => 0,
    ];
    try {
        $stats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM asset_movements")->fetchColumn();
        $stats['this_month'] = (int)$pdo->query("SELECT COUNT(*) FROM asset_movements WHERE movement_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
        $stats['company'] = (int)$pdo->query("SELECT COUNT(*) FROM asset_movements WHERE from_company_id IS NOT NULL OR to_company_id IS NOT NULL OR movement_type LIKE '%Company%'")->fetchColumn();
        $stats['custodian'] = (int)$pdo->query("SELECT COUNT(*) FROM asset_movements WHERE from_custodian_name IS NOT NULL OR to_custodian_name IS NOT NULL OR movement_type LIKE '%Pengguna%'")->fetchColumn();
    } catch (Throwable $e) {}

    echo '<section class="panel">'
        . '<div class="split" style="align-items:center;margin-bottom:16px;">'
        . '  <div>'
        . '    <h1 style="margin:0;display:flex;align-items:center;gap:8px;">Mutasi Aset</h1>'
        . '  </div>'
        . '  <div class="actions" style="display:flex;gap:8px;flex-wrap:wrap;">'
        . '    <a class="btn" href="' . route_url('asset_movements') . '" style="display:inline-flex;align-items:center;gap:6px;background:#f0fdf4;border-color:#86efac;color:#166534;font-weight:600;"><span>📜</span> Riwayat Mutasi</a>'
        . '    <a class="btn primary" href="' . route_url('export_excel', ['type' => 'asset_mutations']) . '" style="display:inline-flex;align-items:center;gap:6px;font-weight:bold;"><span>📥</span> Export Excel Mutasi</a>'
        . '  </div>'
        . '</div>'

        // Kartu Ringkasan (Rounded, Warna-warni, & Transparancy Icon - Sama persis seperti Peminjaman Aset)
        . '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:20px;">'
        // 1. Total Mutasi (Biru / Sky)
        . '  <div style="background:linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);border:1px solid #bae6fd;border-left:4px solid #0284c7;border-radius:14px;padding:12px 16px;position:relative;overflow:hidden;box-shadow:0 1px 3px rgba(2,132,199,0.06);">'
        . '    <div style="position:absolute;right:-10px;top:-10px;width:75px;height:75px;opacity:0.16;transform:rotate(-10deg);pointer-events:none;">'
        . '      <svg viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg>'
        . '    </div>'
        . '    <div style="position:relative;z-index:1;">'
        . '      <div style="font-size:24px;font-weight:800;color:#0284c7;margin-top:3px;line-height:1.2;">' . $stats['total'] . ' <span style="font-size:12.5px;font-weight:600;color:#0284c7;opacity:0.75;">transaksi</span></div>'
        . '    </div>'
        . '  </div>'
        // 2. Mutasi Bulan Ini (Merah / Rose)
        . '  <div style="background:' . ($stats['this_month'] > 0 ? 'linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%)' : '#f8fafc') . ';border:1px solid ' . ($stats['this_month'] > 0 ? '#fecdd3' : '#e2e8f0') . ';border-left:4px solid ' . ($stats['this_month'] > 0 ? '#e11d48' : '#94a3b8') . ';border-radius:14px;padding:12px 16px;position:relative;overflow:hidden;box-shadow:0 1px 3px rgba(225,29,72,0.06);">'
        . '    <div style="position:absolute;right:-10px;top:-10px;width:75px;height:75px;opacity:' . ($stats['this_month'] > 0 ? '0.16' : '0.08') . ';transform:rotate(-10deg);pointer-events:none;">'
        . '      <svg viewBox="0 0 24 24" fill="none" stroke="' . ($stats['this_month'] > 0 ? '#e11d48' : '#64748b') . '" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>'
        . '    </div>'
        . '    <div style="position:relative;z-index:1;">'
        . '      <div style="font-size:24px;font-weight:800;color:' . ($stats['this_month'] > 0 ? '#e11d48' : '#64748b') . ';margin-top:3px;line-height:1.2;">' . $stats['this_month'] . ' <span style="font-size:12.5px;font-weight:600;color:' . ($stats['this_month'] > 0 ? '#e11d48' : '#64748b') . ';opacity:0.75;">bulan ini</span></div>'
        . '    </div>'
        . '  </div>'
        // 3. Mutasi Pengguna (Ungu / Fuchsia)
        . '  <div style="background:linear-gradient(135deg, #fdf4ff 0%, #fae8ff 100%);border:1px solid #f5d0fe;border-left:4px solid #a21caf;border-radius:14px;padding:12px 16px;position:relative;overflow:hidden;box-shadow:0 1px 3px rgba(162,28,175,0.06);">'
        . '    <div style="position:absolute;right:-10px;top:-10px;width:75px;height:75px;opacity:0.16;transform:rotate(-10deg);pointer-events:none;">'
        . '      <svg viewBox="0 0 24 24" fill="none" stroke="#a21caf" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>'
        . '    </div>'
        . '    <div style="position:relative;z-index:1;">'
        . '      <div style="font-size:24px;font-weight:800;color:#a21caf;margin-top:3px;line-height:1.2;">' . $stats['custodian'] . ' <span style="font-size:12.5px;font-weight:600;color:#a21caf;opacity:0.75;">pengguna</span></div>'
        . '    </div>'
        . '  </div>'
        // 4. Mutasi Antar Company (Hijau / Emerald)
        . '  <div style="background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);border:1px solid #bbf7d0;border-left:4px solid #16a34a;border-radius:14px;padding:12px 16px;position:relative;overflow:hidden;box-shadow:0 1px 3px rgba(22,163,74,0.06);">'
        . '    <div style="position:absolute;right:-10px;top:-10px;width:75px;height:75px;opacity:0.16;transform:rotate(-10deg);pointer-events:none;">'
        . '      <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>'
        . '    </div>'
        . '    <div style="position:relative;z-index:1;">'
        . '      <div style="font-size:24px;font-weight:800;color:#16a34a;margin-top:3px;line-height:1.2;">' . $stats['company'] . ' <span style="font-size:12.5px;font-weight:600;color:#16a34a;opacity:0.75;">company</span></div>'
        . '    </div>'
        . '  </div>'
        . '</div>;' + "\\n\\n"
"""

new_text = text[:pos_header] + stats_calc + text[end_header:]
with open('app/modules/asset_mutations.php', 'w', encoding='utf-8') as f:
    f.write(new_text)

print("Updated asset_mutations.php successfully!")

