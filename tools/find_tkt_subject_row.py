with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect ticket list table row for subject
idx = text.find('<strong><?= e($t[\'subject\']) ?></strong>')
print(text[idx-100:idx+200])
