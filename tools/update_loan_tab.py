# -*- coding: utf-8 -*-
with open('app/modules/asset_loan.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

new_lines = lines[:309]
new_lines.append("        // Search Form (Tab filter Sedang Dipinjam, Terlambat, dll ditiadakan)\n")
new_lines.append("        . '<div style=\"display:flex;justify-content:flex-end;align-items:center;flex-wrap:wrap;gap:12px;border-bottom:1px solid #e2e8f0;padding-bottom:12px;margin-bottom:16px;\">' . \"\\n\"\n")
new_lines.append("        . '  <form method=\"get\" style=\"margin:0;display:flex;gap:6px;\">' . \"\\n\"\n")
new_lines.append("        . '    <input type=\"hidden\" name=\"route\" value=\"asset_loans\">' . \"\\n\"\n")
new_lines.append("        . '    <input name=\"q\" value=\"' . e($q) . '\" placeholder=\"Cari no pinjam / peminjam / alat...\" style=\"padding:6px 12px;font-size:13px;width:260px;\">' . \"\\n\"\n")
new_lines.append("        . '    <button class=\"btn\" style=\"padding:6px 12px;\">Cari</button>' . \"\\n\"\n")
new_lines.append("        . ($q !== '' ? '    <a class=\"btn\" href=\"' . route_url('asset_loans') . '\" style=\"padding:6px 12px;\">Reset</a>' : '') . \"\\n\"\n")
new_lines.append("        . '  </form>' . \"\\n\"\n")
new_lines.append("        . '</div>';\n")
new_lines.extend(lines[325:])

with open('app/modules/asset_loan.php', 'w', encoding='utf-8') as f:
    f.writelines(new_lines)

print('Updated asset_loan.php successfully')

