with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    c = f.read()

import re
matches = re.findall(r'@media print\s*\{[^}]+\}', c)
for m in matches:
    print(m)

idx = c.find('@media print')
if idx != -1:
    print("Print CSS block:")
    print(c[idx:idx+1500])

