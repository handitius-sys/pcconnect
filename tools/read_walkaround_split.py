with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

import re
m = re.search(r'function handle_route_walkarounds\(', text)
start = m.start()
m2 = re.search(r'function handle_route_walkaround_form\(', text)
end = m2.start()
fn = text[start:end]
idx = fn.find('class="split"')
print(fn[idx-100:idx+800])
