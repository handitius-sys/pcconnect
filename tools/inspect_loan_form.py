with open('app/modules/asset_loan.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i in range(740, 792):
    val = repr(lines[i]).encode('ascii', errors='backslashreplace').decode('ascii')
    print(f'{i+1}: {val}')
