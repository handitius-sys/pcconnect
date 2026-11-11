with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect ticket_detail around line 1860-1920
idx = text.find('function handle_route_ticket_detail')
print(text[idx+100:idx+1200])
