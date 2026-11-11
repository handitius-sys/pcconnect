with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect handle_route_ticket_form around where $id is checked
idx = text.find('function handle_route_ticket_form(')
print(text[idx:idx+1500])
