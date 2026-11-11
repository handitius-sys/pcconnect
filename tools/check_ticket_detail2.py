with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('handle_route_ticket_detail')
print(text[idx+6500:idx+10500])
