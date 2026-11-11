with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('function($number, $label, $iconPath, $themeIdx)')
print("corrective index:", idx)
if idx != -1:
    print(repr(text[idx-20:idx+400]))

with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f2:
    text2 = f2.read()

idx2 = text2.find('function($number, $label, $iconPath, $themeIdx)')
print("maintenance index:", idx2)
if idx2 != -1:
    print(repr(text2[idx2-20:idx2+400]))
