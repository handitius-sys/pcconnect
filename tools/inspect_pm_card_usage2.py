with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('$renderPmCard = function(')
print(text[idx+2500:idx+4000])
