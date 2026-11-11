with open('app/modules/asset_loan.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx1 = c.find('function handle_route_report_asset_loans')
idx2 = c.find('render_header(', idx1)
print("Loan report_asset_loans:")
print(c[idx2-200:idx2+400].encode('ascii', errors='backslashreplace').decode('ascii'))

