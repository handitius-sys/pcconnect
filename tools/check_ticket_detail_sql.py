with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect ticket_detail query
idx = text.find('function handle_route_ticket_detail')
print(text[idx+130:idx+700])
