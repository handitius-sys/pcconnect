with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('count_total')
print(text[idx-400:idx+400])
