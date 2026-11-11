with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx = c.find('function handle_route_report_asset_items')
print("handle_route_report_asset_items Table rendering:")
print(c[idx+6500:idx+9000].encode('ascii', errors='backslashreplace').decode('ascii'))

