with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx = c.find('overflow-x:auto')
while idx != -1:
    print("Found overflow at:", idx)
    print(c[idx-50:idx+200])
    idx = c.find('overflow-x:auto', idx+1)

