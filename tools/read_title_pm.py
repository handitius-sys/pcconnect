with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i in range(3085, 3095):
    print(f"{i+1}: {lines[i]}", end='')
