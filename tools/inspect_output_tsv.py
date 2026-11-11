with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i, l in enumerate(lines):
    if 'function output_tsv' in l:
        print("output_tsv starts at line", i)
        print("".join(lines[i:i+40]))
        break

