with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect where handle_route_tickets renders action buttons in table
idx = text.find('title="Lihat Detail Tiket (View Only)"')
print(text[idx-200:idx+800])
