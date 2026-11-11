with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect where requested_action is selected in corrective_tickets
idx = text.find('function handle_route_corrective_repairs')
print(text[idx+500:idx+1500])
