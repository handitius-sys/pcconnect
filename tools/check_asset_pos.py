# -*- coding: utf-8 -*-
with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    text = f.read()

pos = text.find("    $addBtn = has_regulation('asset_items', 'create', $user)")
if pos != -1:
    print("Found addBtn at", pos)
    print(text[pos:pos+1200])
else:
    print("Not found")

