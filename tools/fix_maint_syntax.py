with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    c = f.read()

target = """echo '<section class="panel"><div class="split"><h1>Report Pengerjaan Maintenance</h1><a class="btn" href="' . route_url('export_excel', $exportParams) . '">Export Excel</a> <a class="btn primary" href="' . e(route_url('reports', array_merge($_GET, ['print' => 1]))) . '" target="_blank" style="padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;text-decoration:none;">PRINT</a></div><?php
    $todayStr = date('Y-m-d');
    $valStart = $startDate !== '' ? $startDate : $todayStr;
    $valEnd = $endDate !== '' ? $endDate : $todayStr;
    ?>
    <form method="get" class="no-print"><input type="hidden" name="route" value="reports"><div class="grid" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;"><label style="margin:0;">Rentang Tanggal<div style="display:inline-flex;align-items:center;gap:6px;margin-top:4px;"><input type="date" name="start_date" value="<?= e($valStart) ?>" style="height:38px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;"><span style="font-weight:600;color:#64748b;">s/d</span><input type="date" name="end_date" value="<?= e($valEnd) ?>" style="height:38px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;"></div></label><label style="margin:0;min-width:180px;">Per PC / Printer"""

rep = """    $todayStr = date('Y-m-d');
    $valStart = $startDate !== '' ? $startDate : $todayStr;
    $valEnd = $endDate !== '' ? $endDate : $todayStr;

    echo '<section class="panel"><div class="split"><h1>Report Pengerjaan Maintenance</h1><a class="btn" href="' . route_url('export_excel', $exportParams) . '">Export Excel</a> <a class="btn primary" href="' . e(route_url('reports', array_merge($_GET, ['print' => 1]))) . '" target="_blank" style="padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;text-decoration:none;">PRINT</a></div>';
    echo '<form method="get" class="no-print"><input type="hidden" name="route" value="reports"><div class="grid" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;"><label style="margin:0;">Rentang Tanggal<div style="display:inline-flex;align-items:center;gap:6px;margin-top:4px;"><input type="date" name="start_date" value="' . e($valStart) . '" style="height:38px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;"><span style="font-weight:600;color:#64748b;">s/d</span><input type="date" name="end_date" value="' . e($valEnd) . '" style="height:38px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;"></div></label><label style="margin:0;min-width:180px;">Per PC / Printer';"""

assert target in c, 'target not found'
c = c.replace(target, rep, 1)

target_btn = """<label style="margin:0;">&nbsp;<button class="btn primary" style="height:38px;">Filter Report</button></label><label style="margin:0;">&nbsp;<a class="btn" href="<?= route_url('reports') ?>" style="height:38px;line-height:24px;">Reset</a></label></div></form></section>"""
rep_btn = """<label style="margin:0;">&nbsp;<button class="btn primary" style="height:38px;">Filter Report</button></label><label style="margin:0;">&nbsp;<a class="btn" href="' . route_url('reports') . '" style="height:38px;line-height:24px;">Reset</a></label></div></form></section>"""
assert target_btn in c, 'target_btn not found'
c = c.replace(target_btn, rep_btn, 1)

with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
    f.write(c)

print('maintenance.php fixed cleanly')
