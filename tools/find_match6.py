with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect Match 6 in context
m = text.find("COALESCE(cr.action_type, 'Reparasi') AS action_taken")
print(text[m-200:m+600])
