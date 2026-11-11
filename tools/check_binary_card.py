# Let's inspect line endings around $renderCmCard
with open('app/modules/corrective.php', 'rb') as f:
    btext = f.read()

idx = btext.find(b'$renderCmCard = function(')
print(repr(btext[idx:idx+300]))

# Let's find closing bracket
idx_end = btext.find(b'};\n', idx)
if idx_end == -1:
    idx_end = btext.find(b'};\r\n', idx)
print("End idx:", idx_end)
print(repr(btext[idx:idx_end+3]))
