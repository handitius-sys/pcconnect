with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect handle_route_ticket_form around requested actions in POST
idx = text.find('Save requested actions')
print(text[idx+1000:idx+2500])
