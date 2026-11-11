with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('grid five')
while idx != -1:
    print(text[max(0, idx-100):min(len(text), idx+300)])
    print('='*50)
    idx = text.find('grid five', idx+10)
