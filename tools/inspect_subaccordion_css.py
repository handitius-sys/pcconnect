with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx1 = text.find('.nav-subaccordion')
if idx1 != -1:
    print(text[idx1:idx1+1000].encode('ascii', errors='backslashreplace').decode('ascii'))

