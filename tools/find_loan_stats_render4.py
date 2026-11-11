with open('app/modules/asset_loan.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('function handle_route_asset_loans')
print(text[idx+4000:idx+6500])
