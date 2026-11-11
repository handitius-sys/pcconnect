with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = 44239
print(text[idx-100:idx+1500])
