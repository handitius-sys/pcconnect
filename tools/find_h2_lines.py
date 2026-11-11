with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

import re
m = re.search(r'function handle_route_ticket_form\(', text)
start = m.start()
m2 = re.search(r'function handle_route_ticket_detail\(', text)
end = m2.start()
form_fn = text[start:end]

lines = form_fn.splitlines()

for i in range(170, min(len(lines), 410)):
    l = lines[i]
    if '<h2>' in l or 'actions" style="margin-top:20px;' in l:
        print(f'Line {i+1}: {l}')
