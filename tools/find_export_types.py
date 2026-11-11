with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    c = f.read()

import re
idx = c.find('function export_excel')
end_idx = c.find('function handle_route_reports')
if end_idx == -1: end_idx = idx + 25000
types = re.findall(r"if\s*\(\$type === '([^']+)'\)", c[idx:end_idx])
print('Export types in maintenance.php:', types)

