# -*- coding: utf-8 -*-
with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    text = f.read()

target1 = ". '<form method=\"get\" class=\"actions\" style=\"margin:16px 0 14px 0;align-items:flex-end;\">'"
replacement1 = ". '<form method=\"get\" class=\"actions no-print\" style=\"margin:16px 0 14px 0;align-items:flex-end;\">'"

if target1 in text:
    text = text.replace(target1, replacement1, 1)
    with open('app/modules/asset.php', 'w', encoding='utf-8') as f:
        f.write(text)
    print('Updated asset.php form class with no-print')
else:
    print('Target 1 not found')

with open('app/modules/asset_loan.php', 'r', encoding='utf-8') as f:
    ltext = f.read()

target2 = ". '<form method=\"get\" class=\"actions\" style=\"margin:16px 0 20px 0;align-items:flex-end;background:#f8fafc;padding:12px;border-radius:8px;border:1px solid #e2e8f0;\">'"
replacement2 = ". '<form method=\"get\" class=\"actions no-print\" style=\"margin:16px 0 20px 0;align-items:flex-end;background:#f8fafc;padding:12px;border-radius:8px;border:1px solid #e2e8f0;\">'"

if target2 in ltext:
    ltext = ltext.replace(target2, replacement2, 1)
    with open('app/modules/asset_loan.php', 'w', encoding='utf-8') as f:
        f.write(ltext)
    print('Updated asset_loan.php form class with no-print')
else:
    print('Target 2 not found')

with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    mtext = f.read()

target3 = "<form method=\"get\"><input type=\"hidden\" name=\"route\" value=\"reports\">"
replacement3 = "<form method=\"get\" class=\"no-print\"><input type=\"hidden\" name=\"route\" value=\"reports\">"

if target3 in mtext:
    mtext = mtext.replace(target3, replacement3, 1)
    with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
        f.write(mtext)
    print('Updated maintenance.php form class with no-print')
else:
    print('Target 3 not found')

