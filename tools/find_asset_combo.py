# -*- coding: utf-8 -*-
with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect where $comboAssets is computed in handle_route_asset_items
pos = text.find("$comboAssets = [];")
print("Combo assets at:", pos)
print(text[pos-100:pos+100])

