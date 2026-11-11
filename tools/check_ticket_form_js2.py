with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('async function onDynTypeChange()')
print(text[idx+1000:idx+3500])
