with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect handle_route_ticket_form around title and form attributes
idx = text.find('Buat Tiket Kendala / Reparasi Baru')
print(text[idx-200:idx+800])
