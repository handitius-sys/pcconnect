with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect handle_route_ticket_form around requested actions in HTML
idx = text.find('requested_actions[]')
print(text[idx-400:idx+400])
