with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('invoiceImageViewerModal')
while idx != -1:
    print('idx:', idx)
    print(text[idx-100:idx+300])
    print('='*50)
    idx = text.find('invoiceImageViewerModal', idx+1)
