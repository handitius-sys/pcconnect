with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx1 = c.find('function handle_route_asset_movements')
idx2 = c.find('render_header(', idx1)
print("Asset asset_movements:")
print(c[idx2-100:idx2+400].encode('ascii', errors='backslashreplace').decode('ascii'))

