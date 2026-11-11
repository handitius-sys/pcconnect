with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx1 = c.find('.sidebar{')
idx2 = c.find('.sidebar-nav')
print(c[idx1:idx2])

