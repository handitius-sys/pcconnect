with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect ticket list rendering for the subject column
idx = text.find('<strong><?= e($t[\'subject\']) ?></strong>')
print(text[idx-200:idx+400])
