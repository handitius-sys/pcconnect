with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find("if ($type === 'reports') {")
print(text[idx+2000:idx+4500])
