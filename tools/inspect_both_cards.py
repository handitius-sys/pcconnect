with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect where $renderCmCard is defined
idx = text.find('$renderCmCard = function(')
idx_end = text.find('};', idx) + 2
print("Current $renderCmCard:")
print(text[idx:idx_end])

# Let's check maintenance.php
with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f2:
    text2 = f2.read()

idx2 = text2.find('$renderPmCard = function(')
idx2_end = text2.find('};', idx2) + 2
print("\nCurrent $renderPmCard:")
print(text2[idx2:idx2_end])
