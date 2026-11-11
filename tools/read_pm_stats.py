with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('grid five')
print(text[max(0, idx-400):min(len(text), idx+1000)])
