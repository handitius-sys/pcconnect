with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect first 40 lines
lines = text.splitlines(True)
print("First 15 lines:")
for i in range(min(15, len(lines))):
    print(f'{i+1}: {repr(lines[i])}')
