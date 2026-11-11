with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

import re
m = re.search(r'function handle_route_tickets\(', text)
start = m.start()
m2 = re.search(r'function handle_route_ticket_form\(', text)
end = m2.start()
ticket_fn = text[start:end]

idx = ticket_fn.find('title="Detail Tiket & Penanganan"')
print(ticket_fn[idx-200:idx+800])
