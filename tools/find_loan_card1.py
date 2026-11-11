with open('app/modules/asset_loan.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('Kartu Ringkasan (Rounded, Warna-warni, & Transparancy Icon)')
print(text[idx:idx+800])
