with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i in range(10, 45):
    if i < len(lines):
        print(f'{i+1}: {repr(lines[i])}')
