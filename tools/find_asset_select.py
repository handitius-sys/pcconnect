with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect handle_route_ticket_form around lines 1350 to 1420
idx = text.find('id="maintAssetSelect"')
print(text[idx-200:idx+400])
