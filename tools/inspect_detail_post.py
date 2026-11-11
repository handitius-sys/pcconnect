with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect where handle_route_ticket_detail handles POST or if it only handles GET
idx = text.find('function handle_route_ticket_detail(')
print(text[idx:idx+1000])
