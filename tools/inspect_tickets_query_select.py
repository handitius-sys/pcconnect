with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect handle_route_tickets query
idx = text.find('function handle_route_tickets(')
print(text[idx+3000:idx+4500])
