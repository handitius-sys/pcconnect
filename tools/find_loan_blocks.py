import glob

# Let's inspect loan blocks in asset.php or asset_loan.php
for f in ['app/modules/asset.php', 'app/modules/maintenance.php', 'app/modules/corrective.php']:
    with open(f, 'r', encoding='utf-8') as fl:
        text = fl.read()
        if 'peminjaman' in text.lower():
            for line in text.splitlines():
                if 'grid' in line or 'card' in line or 'stat' in line:
                    if 'loan' in line or 'peminjaman' in line or 'badge' in line:
                        print(f'{f}: {line[:100]}')
