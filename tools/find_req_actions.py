with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect handle_route_ticket_form around requested_actions
idx = text.find('requested_actions')
while idx != -1:
    print('--- idx:', idx, '---')
    print(text[idx-50:idx+250])
    idx = text.find('requested_actions', idx+1)
