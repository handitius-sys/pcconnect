with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('Kendala / Subjek')
print(text[idx+3000:idx+4500])
