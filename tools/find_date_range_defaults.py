import glob

for phpfile in ['app/modules/maintenance.php', 'app/modules/asset.php']:
    with open(phpfile, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()
        for line in content.splitlines():
            if 'firstDay' in line or 'lastDay' in line or 'valStart' in line or 'start_date' in line and 'date(' in line:
                print(f'{phpfile}: {line[:100]}')
