with open('app/modules/regulations.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx1 = text.find('<?php if ($currentTab === \'menu_order\'): ?>')
idx2 = text.find('</form>', idx1)
print(text[idx1:idx2+10].encode('ascii', errors='backslashreplace').decode('ascii'))

