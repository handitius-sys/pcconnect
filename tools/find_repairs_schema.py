with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect where corrective_repairs has columns defined
idx = text.find('CREATE TABLE IF NOT EXISTS corrective_repairs')
if idx == -1:
    with open('app/lib/schema.php', 'r', encoding='utf-8') as f2:
        text2 = f2.read()
    idx2 = text2.find('CREATE TABLE IF NOT EXISTS corrective_repairs')
    print('schema.php:')
    print(text2[idx2:idx2+800])
else:
    print('corrective.php:')
    print(text[idx:idx+800])
