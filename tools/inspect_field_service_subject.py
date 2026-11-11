with open('app/modules/field_service.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i in range(670, 750):
    if i < len(lines):
        print(f'{i+1}: {lines[i]}', end='')
