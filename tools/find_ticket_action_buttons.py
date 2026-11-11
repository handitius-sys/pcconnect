with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect the action buttons in handle_route_tickets
idx = text.find('route_url(\'ticket_detail\'')
while idx != -1:
    print('idx:', idx)
    print(text[idx-100:idx+300])
    print('='*50)
    idx = text.find('route_url(\'ticket_detail\'', idx+1)
