with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

import re
matches = [m.start() for m in re.finditer(r'Perbaikan', text)]
for m in matches:
    print(text[m-50:m+200])
    print('='*50)
