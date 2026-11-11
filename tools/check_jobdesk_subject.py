with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect where requested_actions or subject is generated in ticket_form
idx = text.find('Perbaikan [Job Desk')
if idx == -1:
    idx = text.find('Job Desk Corrective')
print('idx:', idx)
if idx != -1:
    print(text[idx-200:idx+400])
