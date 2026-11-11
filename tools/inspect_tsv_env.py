with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx1 = c.find('function output_tsv')
idx2 = c.find('function handle_route_reports')
print("Line around output_tsv:")
print(c[idx1-200:idx1+1000])

