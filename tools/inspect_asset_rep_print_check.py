# Let's inspect each report print check:
# 1. report_asset_items in asset.php
with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx1 = c.find('function handle_route_report_asset_items')
idx2 = c.find('render_header(', idx1)
print("Asset report_asset_items:")
print(c[idx2-200:idx2+400].encode('ascii', errors='backslashreplace').decode('ascii'))

