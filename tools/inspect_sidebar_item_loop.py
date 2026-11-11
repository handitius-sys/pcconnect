with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('foreach ($visibleItems as $itemKey => $item) {')
if idx != -1:
    print(text[idx:idx+1500].encode('ascii', errors='backslashreplace').decode('ascii'))

