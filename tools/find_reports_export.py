import re

with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    text = f.read()

pos = 0
while True:
    idx = text.find("'reports'", pos)
    if idx == -1:
        break
    print(f"Found at {idx}:")
    print(text[max(0, idx-50):min(len(text), idx+200)])
    print("-" * 50)
    pos = idx + 9
