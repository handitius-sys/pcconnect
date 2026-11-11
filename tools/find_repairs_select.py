with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect where corrective_repairs are selected in handle_route_corrective_repairs
idx = text.find('function handle_route_corrective_repairs')
print(text[idx+100:idx+800])
