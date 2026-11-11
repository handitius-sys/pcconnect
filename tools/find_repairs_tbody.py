with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect where $repairs table rows are printed in handle_route_corrective_repairs
idx = text.find('<tbody>')
# find tbody inside handle_route_corrective_repairs
idx_fn = text.find('function handle_route_corrective_repairs')
idx_tbody = text.find('<tbody>', idx_fn)
print(text[idx_tbody:idx_tbody+2500])
