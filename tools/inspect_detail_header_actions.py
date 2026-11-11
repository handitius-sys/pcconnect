with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect handle_route_ticket_detail around the actions div
idx = text.find('h1>Tiket #<?= e($ticket[\'ticket_code\']) ?></h1>')
print(text[idx-50:idx+1200])
