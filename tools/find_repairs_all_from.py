with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

import re
matches = [m.start() for m in re.finditer(r'FROM corrective_repairs', text)]
print('Matches:', len(matches))
for i, m in enumerate(matches):
    print(f'Match {i}:', text[m-100:m+200])
    print('='*50)
