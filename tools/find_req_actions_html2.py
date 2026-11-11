with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect handle_route_ticket_form around requested actions in HTML to see if there is any JS setting subject
idx = text.find('Daftar Tindakan / Pekerjaan Perbaikan')
print(text[idx:idx+1500])
