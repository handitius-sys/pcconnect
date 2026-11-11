import glob

for f in glob.glob('app/**/*.php', recursive=True):
    with open(f, 'r', encoding='utf-8', errors='ignore') as fp:
        lines = fp.readlines()
        for idx, l in enumerate(lines):
            if 'export_excel' in l:
                if 'function ' in l or 'case ' in l or 'handle_' in l:
                    print(f'{f}:{idx+1}: {l.strip()[:100]}')
