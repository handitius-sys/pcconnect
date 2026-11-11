with open('app/modules/asset_loan.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx = c.find('function handle_route_report_asset_loans')
print("handle_route_report_asset_loans at:", idx)
print(c[idx:idx+2500].encode('ascii', errors='backslashreplace').decode('ascii'))

