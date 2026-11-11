with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx = c.find('overflow')
while idx != -1:
    print("Found overflow at:", idx)
    print(c[idx-50:idx+150])
    idx = c.find('overflow', idx+1)

