with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx = c.find('html,body{width:100%!important;margin:0!important')
if idx != -1:
    print(c[idx-50:idx+2500])

