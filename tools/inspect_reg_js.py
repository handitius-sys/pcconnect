with open('app/modules/regulations.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx1 = text.find('function moveRowOrder(')
if idx1 != -1:
    print(text[idx1:idx1+1500].encode('ascii', errors='backslashreplace').decode('ascii'))
else:
    print("moveRowOrder not found")

