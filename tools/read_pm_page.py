with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('>Preventive Maintenance</h1>')
print(text[max(0, idx-100):min(len(text), idx+3000)])
