with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx = c.find('function handle_route_report_asset_items')
print("handle_route_report_asset_items from asset.php:")
print(c[idx+3500:idx+6500].encode('ascii', errors='backslashreplace').decode('ascii'))

