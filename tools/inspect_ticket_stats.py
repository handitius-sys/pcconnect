with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's search where corrective_tickets are queried in handle_route_tickets
idx = text.find('SELECT COUNT(*) AS total_tickets')
if idx == -1:
    idx = text.find('COUNT(CASE WHEN t.status')
print('idx:', idx)
if idx != -1:
    print(text[idx-100:idx+400])
