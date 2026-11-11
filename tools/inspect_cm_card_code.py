with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect $renderCmCard in corrective.php
idx = text.find('$renderCmCard = function(')
print(text[idx:idx+1400])
