with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

import re
matches = [m.start() for m in re.finditer(r'input[^>]*name=["\']subject["\']', text, re.IGNORECASE)]
for pos in matches:
    print(text[max(0, pos-150):min(len(text), pos+200)])
    print('='*50)
