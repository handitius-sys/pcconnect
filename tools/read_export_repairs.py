with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find("$type === 'corrective_repairs'")
if idx != -1:
    print(text[idx-50:idx+1500])
else:
    print('Not found')
