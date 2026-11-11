with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('function handle_route_ticket_form(')
print(text[idx:idx+2500])
