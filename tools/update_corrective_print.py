# -*- coding: utf-8 -*-
with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

target1 = '<th style="text-align:center;">Foto Kwitansi / Invoice</th>'
replacement1 = '<th style="text-align:center;" class="no-print">Foto Kwitansi / Invoice</th>'

target2 = '<td style="text-align:center;">'
replacement2 = '<td style="text-align:center;" class="no-print">'

if target1 in text:
    text = text.replace(target1, replacement1, 1)
    text = text.replace(target2, replacement2)
    with open('app/modules/corrective.php', 'w', encoding='utf-8') as f:
        f.write(text)
    print('Updated corrective.php invoice column with no-print')
else:
    print('Target 1 not found')

