with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx1 = text.find('.nav-subaccordion')
idx2 = text.find('.sidebar-user')
print(text[idx1:idx2].encode('ascii', errors='backslashreplace').decode('ascii'))

