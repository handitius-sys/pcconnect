with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx = c.find('function handle_route_asset_movements')
print("handle_route_asset_movements at:", idx)
print(c[idx:idx+2500].encode('ascii', errors='backslashreplace').decode('ascii'))

