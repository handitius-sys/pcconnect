with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect handle_route_corrective_repairs SQL again
idx = text.find('function handle_route_corrective_repairs')
print(text[idx+100:idx+1500])
