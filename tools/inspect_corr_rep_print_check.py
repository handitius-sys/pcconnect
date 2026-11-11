with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx1 = c.find('function handle_route_corrective_repairs')
idx2 = c.find('render_header(', idx1)
print("Corrective repairs:")
print(c[idx2-200:idx2+400].encode('ascii', errors='backslashreplace').decode('ascii'))

