with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('handle_route_asset_loans')
if idx != -1:
    print(text[idx:idx+2500])
else:
    print("handle_route_asset_loans not found in asset.php")
