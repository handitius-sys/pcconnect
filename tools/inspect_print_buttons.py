# Inspect handle_route_reports, handle_route_asset_movements, handle_route_report_asset_loans, handle_route_corrective_repairs

import glob, re

for fpath in ['app/modules/asset.php', 'app/modules/asset_loan.php', 'app/modules/maintenance.php', 'app/modules/corrective.php']:
    with open(fpath, 'r', encoding='utf-8') as f:
        c = f.read()
    
    matches = [m.start() for m in re.finditer(r'openPrintSettings\(\)', c)]
    print(fpath, "count:", len(matches))
    for m in matches:
        print(c[m-120:m+120])
        print("-" * 50)

