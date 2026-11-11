with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect handle_route_ticket_form from start to render_header
idx = text.find('function handle_route_ticket_form(')
idx_render = text.find('render_header(', idx)
print(text[idx:idx_render+300])
