with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect handle_route_ticket_form around where $allActions is fetched
idx = text.find('$allActions =')
print(text[idx-200:idx+300])
