with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('>Preventive Maintenance</h1>')
print(text[idx-500:idx+2500])
