with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find("$type === 'corrective_repairs'")
print(text[idx+2000:idx+3500])
