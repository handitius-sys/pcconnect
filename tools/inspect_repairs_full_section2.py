with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('function handle_route_corrective_repairs')
print(text[idx+3500:idx+7500])
