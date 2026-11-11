with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i in range(7550, 7750):
    if i < len(lines):
        line = repr(lines[i]).encode('ascii', errors='backslashreplace').decode('ascii')
        print(f'{i+1}: {line}')
