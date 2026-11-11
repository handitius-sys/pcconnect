with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect handle_route_api_corrective_data
idx = text.find('function handle_route_api_corrective_data')
print(text[idx:idx+3000])
