with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect where corrective_repairs are selected in handle_route_corrective_repairs
idx = text.find('FROM corrective_repairs cr')
print(text[idx-200:idx+600])
