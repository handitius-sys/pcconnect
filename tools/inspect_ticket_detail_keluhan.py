with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('Detail Keluhan & Foto')
print(text[idx-50:idx+2500])
