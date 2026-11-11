with open('app/modules/asset_loan.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('handle_route_asset_loans')
print(text[idx+100:idx+2500])
