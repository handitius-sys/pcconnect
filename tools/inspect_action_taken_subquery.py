with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect where corrective_repairs query has action_taken
idx = text.find('COALESCE(cr.action_type')
print(text[idx:idx+300])
