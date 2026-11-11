with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect handle_route_ticket_form around line 1250-1310 where $subject is assigned
idx = text.find('$subject =')
print(text[idx-100:idx+300])
