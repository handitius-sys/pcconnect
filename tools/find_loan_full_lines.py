with open('app/modules/asset_loan.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i in range(265, 345):
    if i < len(lines):
        print(f'{i+1}: {lines[i]}', end='')
