with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect ticket list rendering for the subject column
idx = text.find('<td><?= ticket_category_badge($t[\'asset_category\']) ?>')
if idx != -1:
    print(text[idx:idx+1500])
else:
    # search Kendala / Subjek
    idx2 = text.find('Kendala / Subjek')
    print(text[idx2:idx2+1500])
