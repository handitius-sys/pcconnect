with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx1 = c.find('function output_tsv')
idx2 = c.find('function handle_route_reports', idx1)
print(c[idx1:idx2].encode('ascii', errors='backslashreplace').decode('ascii'))

