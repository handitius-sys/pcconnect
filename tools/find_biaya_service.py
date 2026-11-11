with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect handle_route_ticket_form around Biaya Service
idx = text.find('💰 Biaya Service')
print(text[idx:idx+1500])
