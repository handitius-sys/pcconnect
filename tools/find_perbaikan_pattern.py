import glob

for phpfile in glob.glob('app/**/*.php', recursive=True):
    with open(phpfile, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()
        if 'Perbaikan [' in content:
            print('Found in:', phpfile)
            for i, line in enumerate(content.splitlines()):
                if 'Perbaikan [' in line:
                    print(f'{i+1}: {line}')
