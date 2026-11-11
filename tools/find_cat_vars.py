with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i, l in enumerate(lines):
    if "displayCategory" in l or "categoryName" in l:
        print(f'{i+1}: {l.strip()[:100]}')
