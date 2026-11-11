with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('TOTAL TIKET')
print(text[idx-200:idx+400])
