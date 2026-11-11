with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx = c.find('function handle_route_reports')
print("handle_route_reports actions at:", idx)
print(c[idx+2000:idx+4500].encode('ascii', errors='backslashreplace').decode('ascii'))

