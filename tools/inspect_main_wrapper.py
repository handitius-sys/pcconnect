with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx = c.find('.main-wrapper{')
print(c[idx:idx+400])

