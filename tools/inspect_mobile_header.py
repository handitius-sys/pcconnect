with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('function render_mobile_header')
if idx != -1:
    print(text[idx:idx+2500].encode('ascii', errors='backslashreplace').decode('ascii'))

