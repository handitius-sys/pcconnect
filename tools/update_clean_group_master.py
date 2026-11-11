# -*- coding: utf-8 -*-
with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    text = f.read()

target = "$pdo->exec(\"DELETE FROM system_menu_orders WHERE item_key = 'asset_legacy_import'\");"
replacement = """$pdo->exec("DELETE FROM system_menu_orders WHERE item_key = 'asset_legacy_import'");
        // Hapus key lama group_master agar ter-refresh menjadi 4 kelompok baru
        $pdo->exec("DELETE FROM system_menu_orders WHERE item_key = 'group_master'");"""

if target in text:
    text = text.replace(target, replacement, 1)
    with open('app/lib/bootstrap.php', 'w', encoding='utf-8') as f:
        f.write(text)
    print("Added migration delete for group_master in bootstrap.php")
else:
    print("Target not found")

