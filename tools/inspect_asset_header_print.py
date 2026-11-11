with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx = c.find('function handle_route_report_asset_items')
print("handle_route_report_asset_items start at:", idx)
print(c[idx+5000:idx+7000].encode('ascii', errors='backslashreplace').decode('ascii'))

