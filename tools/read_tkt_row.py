with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

import re
m = re.search(r'function handle_route_tickets\(', text)
start = m.start()
m2 = re.search(r'function handle_route_ticket_form\(', text)
end = m2.start()
tkt_fn = text[start:end]

idx = tkt_fn.find('<?= e($t[\'ticket_code\']) ?>')
print('Index:', idx)
if idx != -1:
    print(tkt_fn[idx:idx+1500])
