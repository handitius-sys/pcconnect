with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('function handle_route_ticket_detail(')
idx2 = text.find('function handle_route_ticket_action(')
fn = text[idx:idx2]

lines = fn.splitlines()
for i, l in enumerate(lines):
    if 'action=' in l or '<form' in l or 'button' in l:
        print(f"{i+1}: {l}")
