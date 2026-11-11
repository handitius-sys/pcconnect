# -*- coding: utf-8 -*-
with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    text = f.read()

target = "    $html = '<div style=\"overflow-x:auto;\"><table class=\"sortable-table\">'\n        . '<thead><tr>'"
replacement = "    $html = '<div style=\"overflow-x:auto;\"><table class=\"report-table-plain report-target-table sortable-table\">'\n        . '<thead><tr>'"

if target in text:
    text = text.replace(target, replacement, 1)
    with open('app/modules/asset.php', 'w', encoding='utf-8') as f:
        f.write(text)
    print('Updated asset_movements_table successfully')
else:
    print('Target not found in asset.php')

