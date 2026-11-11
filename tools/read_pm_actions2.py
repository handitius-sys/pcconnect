with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('>Preventive Maintenance</h1>')
print(text[idx+4500:idx+7500])
