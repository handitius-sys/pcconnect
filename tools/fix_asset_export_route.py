# -*- coding: utf-8 -*-
with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    text = f.read()

target = "    $exportParams = array_merge(['type' => 'asset_items'], $_GET);"
replacement = "    $cleanFilters = $_GET;\n    unset($cleanFilters['route']);\n    $exportParams = array_merge(['type' => 'asset_items'], $cleanFilters);"

if target in text:
    text = text.replace(target, replacement, 1)
    with open('app/modules/asset.php', 'w', encoding='utf-8') as f:
        f.write(text)
    print('Updated asset.php successfully')
else:
    print('Target not found in asset.php')

