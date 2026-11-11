with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = 144241
print(text[idx-300:idx+300])
