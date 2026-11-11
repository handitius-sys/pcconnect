with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i, l in enumerate(lines):
    if "categoryName =" in l or "displayCategory =" in l:
        print(f'{i+1}: {repr(l)}')
