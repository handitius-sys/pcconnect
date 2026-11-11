with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i, l in enumerate(lines):
    if 'function handle_route_export' in l or 'export_excel' in l or "type'] === 'asset_items'" in l or "type'] === 'report_asset" in l:
        print(f'{i+1}: {l.strip()[:100]}')
