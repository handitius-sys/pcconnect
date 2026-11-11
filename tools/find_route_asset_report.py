with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i, l in enumerate(lines):
    if "route_url('report_asset_items'" in l or 'report_asset_items' in l:
        print(f'{i+1}: {l.strip()[:100]}')
