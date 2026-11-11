with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's find the second FROM corrective_repairs cr
idx = text.find('FROM corrective_repairs cr')
idx2 = text.find('FROM corrective_repairs cr', idx+1)
print(text[idx2-200:idx2+600])
