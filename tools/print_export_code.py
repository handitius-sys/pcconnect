with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i, l in enumerate(lines):
    if 'function export_excel' in l:
        print("".join(lines[i:i+150]))
        break

