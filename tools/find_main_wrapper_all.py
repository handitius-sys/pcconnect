with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx = c.find('main-wrapper')
while idx != -1:
    print(c[idx-30:idx+150])
    print("-" * 40)
    idx = c.find('main-wrapper', idx+1)

