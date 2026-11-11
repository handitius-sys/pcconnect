with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect handle_route_ticket_form around where JavaScript or HTML handles subject
import re
print([m.start() for m in re.finditer(r'subject', text)])
