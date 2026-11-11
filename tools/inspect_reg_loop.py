with open('app/modules/regulations.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('foreach ($menuStructure as $gKey => $group):')
if idx != -1:
    print(text[idx:idx+2500].encode('ascii', errors='backslashreplace').decode('ascii'))

