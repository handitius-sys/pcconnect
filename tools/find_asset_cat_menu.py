with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i, l in enumerate(lines):
    if "asset_categories" in l:
        print(f'{i+1}: {l.strip()[:100]}')
