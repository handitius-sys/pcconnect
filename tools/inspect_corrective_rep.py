with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx = c.find('function handle_route_corrective_repairs')
print("handle_route_corrective_repairs at:", idx)
print(c[idx:idx+2500].encode('ascii', errors='backslashreplace').decode('ascii'))

