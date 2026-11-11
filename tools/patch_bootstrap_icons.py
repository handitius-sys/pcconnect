with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Add CSS for .btn-icon.excel and .btn-icon.print
target_css = ".btn-icon.add{color:#16a34a;border-color:#bbf7d0;background:#f0fdf4}.btn-icon.add:hover{background:#dcfce7;color:#15803d;border-color:#86efac}';"
new_css = target_css + "\n    echo '.btn-icon.excel{color:#15803d;border-color:#bbf7d0;background:#f0fdf4;width:32px;height:32px}.btn-icon.excel:hover{background:#dcfce7;color:#166534;border-color:#86efac;transform:translateY(-1px);box-shadow:0 2px 6px rgba(22,101,52,0.18)}';" + "\n    echo '.btn-icon.print{color:#2563eb;border-color:#bfdbfe;background:#eff6ff;width:32px;height:32px}.btn-icon.print:hover{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd;transform:translateY(-1px);box-shadow:0 2px 6px rgba(37,99,235,0.18)}';"

# Add helper functions btn_icon_excel and btn_icon_print
target_fn = "function btn_icon_add_mini(string $url, string $title = 'Tambah Baru'): string\n{\n    return '<a class=\"btn-icon add\" href=\"' . e($url) . '\" title=\"' . e($title) . '\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><line x1=\"12\" y1=\"5\" x2=\"12\" y2=\"19\"></line><line x1=\"5\" y1=\"12\" x2=\"19\" y2=\"12\"></line></svg></a>';\n}"
new_fn = target_fn + """

function btn_icon_excel(string $url, string $title = 'Export to Excel'): string
{
    return '<a class="btn-icon excel" href="' . e($url) . '" title="' . e($title) . '" aria-label="' . e($title) . '"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="8" y1="13" x2="16" y2="13"></line><line x1="8" y1="17" x2="16" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg></a>';
}

function btn_icon_print(string $url, string $title = 'Print Laporan'): string
{
    return '<a class="btn-icon print" href="' . e($url) . '" target="_blank" title="' . e($title) . '" aria-label="' . e($title) . '"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg></a>';
}"""

print("Found target_css:", target_css in content)
print("Found target_fn:", target_fn in content)

if target_css in content and target_fn in content:
    content = content.replace(target_css, new_css, 1)
    content = content.replace(target_fn, new_fn, 1)
    with open('app/lib/bootstrap.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print("SUCCESS: bootstrap.php updated!")
else:
    print("FAILED to update bootstrap.php")
