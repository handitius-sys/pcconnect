with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

import re
m = re.search(r'function handle_route_tickets\(', text)
start = m.start()
m2 = re.search(r'function handle_route_ticket_form\(', text)
end = m2.start()
ticket_fn = text[start:end]

lines = ticket_fn.splitlines()
for i, line in enumerate(lines):
    if '<table' in line or '<thead>' in line or '$assetLabel' in line or '$t[\'subject\']' in line or 'Detail Tiket' in line:
        print(f"{i+1}: {line}")
