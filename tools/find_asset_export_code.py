with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i, l in enumerate(lines):
    if "$type === 'asset_items'" in l and i > 3440:
        for j in range(i, min(i+100, len(lines))):
            print(f'{j+1}: {repr(lines[j])}')
        break
else:
    print("Not found after line 3440, let's search entire file for asset_items in export")
    for i, l in enumerate(lines):
        if "asset_items" in l and ("$type" in l or "export" in l.lower()):
            print(f'{i+1}: {repr(l)}')
