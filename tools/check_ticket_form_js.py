with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect javascript in handle_route_ticket_form
idx = text.find('function onDynGroupChange()')
if idx == -1:
    idx = text.find('dynGroupId')
print(text[idx:idx+2500])
