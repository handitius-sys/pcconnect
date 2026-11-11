with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('<aside class="sidebar"')
if idx != -1:
    print(text[idx:idx+3500].encode('ascii', errors='backslashreplace').decode('ascii'))
else:
    print("Not found")

