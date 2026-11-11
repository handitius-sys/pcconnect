with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    c_lines = f.readlines()

for i in range(1280, 1370):
    if i < len(c_lines):
        print(f'{i+1}: {c_lines[i]}', end='')
