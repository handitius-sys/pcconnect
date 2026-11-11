with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('function handle_route_ticket_detail(')
print(text[idx+1000:idx+4500])
