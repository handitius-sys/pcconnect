with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('if (!empty($_GET[\'print\']))')
print(text[idx+1000:idx+2500])
