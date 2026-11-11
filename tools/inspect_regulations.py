with open('app/modules/regulations.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('menu_order')
if idx != -1:
    print(text[idx:idx+2500].encode('ascii', errors='backslashreplace').decode('ascii'))
else:
    print("menu_order not found in regulations.php")

