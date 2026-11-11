with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect where subject placeholder or default value is set
idx = text.find('name="subject"')
print(text[idx-100:idx+200])
