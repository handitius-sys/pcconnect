with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect ticket_detail around technician action form
idx = text.find('handle_route_ticket_detail')
print(text[idx+3000:idx+6500])
