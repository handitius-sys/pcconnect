with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('function onMaintAssetChange()')
if idx == -1:
    idx = text.find('onMaintAssetChange')
print(text[idx:idx+2500])
