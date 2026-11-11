with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

import re
matches = [m.start() for m in re.finditer(r'<td style="text-align:center;" class="no-print">', text)]
print('Matches:', len(matches))
for pos in matches:
    print(text[pos:pos+400])
    print('='*40)
