with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect ticket_detail around line 1920-1980
idx = text.find('Informasi Tiket & Maintenance Asset')
print(text[idx-200:idx+600])
