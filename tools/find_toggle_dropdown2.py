with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('function toggleMaintAssetDropdown')
print(text[idx+2000:idx+4500])
