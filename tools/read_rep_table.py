with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

import re
m = re.search(r'function handle_route_corrective_repairs\(', text)
start = m.start()
m2 = re.search(r'function handle_route_walkarounds\(', text)
end = m2.start()
rep_fn = text[start:end]

idx = rep_fn.find('<table')
print(rep_fn[idx:idx+4000])
