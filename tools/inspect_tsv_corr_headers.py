with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect export TSV in maintenance.php
with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f2:
    text2 = f2.read()

idx = text2.find("$type === 'corrective_repairs'")
print(text2[idx+1800:idx+3200])
