with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i, l in enumerate(lines):
    if 'function export_excel' in l:
        for j in range(i, len(lines)):
            if lines[j].strip().startswith("if ($type ==="):
                print(lines[j].strip())
            if 'function handle_route_reports' in lines[j]:
                break
        break

