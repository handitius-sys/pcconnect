with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('handle_route_maintenance(')
print(text[idx+100:idx+3500])
