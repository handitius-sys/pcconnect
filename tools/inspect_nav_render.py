with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx1 = text.find('$menuStructure = get_sidebar_menu_orders($pdo ?? null);')
idx2 = text.find('echo \'</nav>\'; // End sidebar-nav')
print(text[idx1:idx2].encode('ascii', errors='backslashreplace').decode('ascii'))

