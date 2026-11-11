# Check companies table
with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx = c.find('function company_options')
print("company_options at:", idx)
print(c[idx:idx+800].encode('ascii', errors='backslashreplace').decode('ascii'))

