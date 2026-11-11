with open('app/modules/regulations.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('tab === \'menu_order\'')
if idx == -1:
    idx = text.find('menu_order')
if idx != -1:
    # search where menu_order tab HTML is rendered
    idx2 = text.find('$currentTab === \'menu_order\'', idx)
    if idx2 != -1:
        print(text[idx2:idx2+3000].encode('ascii', errors='backslashreplace').decode('ascii'))
    else:
        print("Tab render check")
        print(text[idx:idx+2000].encode('ascii', errors='backslashreplace').decode('ascii'))

