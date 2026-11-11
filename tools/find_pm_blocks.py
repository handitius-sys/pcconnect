with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's search stats in maintenance.php
idx = text.find('Total Jadwal')
if idx == -1:
    idx = text.find('stats[')
print('idx in maintenance:', idx)
if idx != -1:
    print(text[idx-200:idx+800])
