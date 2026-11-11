with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('function openInvoiceImageViewer')
if idx != -1:
    print(text[idx-100:idx+1500])
else:
    idx2 = text.find('openInvoiceImageViewer')
    print('idx2:', idx2)
    print(text[idx2-100:idx2+600])
