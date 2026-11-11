with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

indices = [28571, 41164, 64821, 64853, 65018, 68902, 69371, 78446, 106466, 125890, 127797, 144241]
for i in indices:
    print(f'=== Index {i} ===')
    print(text[max(0, i-50):min(len(text), i+150)])
