with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    c = f.read()

target = "echo '<form method=\"get\" class=\"no-print\"><input type=\"hidden\" name=\"route\" value=\"reports\"><div class=\"grid\" style=\"display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;\"><label style=\"margin:0;\">Rentang Tanggal<div style=\"display:inline-flex;align-items:center;gap:6px;margin-top:4px;\"><input type=\"date\" name=\"start_date\" value=\"' . e($valStart) . '\" style=\"height:38px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;\"><span style=\"font-weight:600;color:#64748b;\">s/d</span><input type=\"date\" name=\"end_date\" value=\"' . e($valEnd) . '\" style=\"height:38px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;\"></div></label><label style=\"margin:0;min-width:180px;\">Per PC / Printer\';<select name=\"pc_id\"><option value=\"\">Semua Aset</option>\';"

rep = "echo '<form method=\"get\" class=\"no-print\"><input type=\"hidden\" name=\"route\" value=\"reports\"><div class=\"grid\" style=\"display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;\"><label style=\"margin:0;\">Rentang Tanggal<div style=\"display:inline-flex;align-items:center;gap:6px;margin-top:4px;\"><input type=\"date\" name=\"start_date\" value=\"' . e($valStart) . '\" style=\"height:38px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;\"><span style=\"font-weight:600;color:#64748b;\">s/d</span><input type=\"date\" name=\"end_date\" value=\"' . e($valEnd) . '\" style=\"height:38px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;\"></div></label><label style=\"margin:0;min-width:180px;\">Per PC / Printer<select name=\"pc_id\"><option value=\"\">Semua Aset</option>\';"

assert target in c, 'target not found'
c = c.replace(target, rep, 1)

with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
    f.write(c)

print('Quote typo in maintenance.php fixed')
