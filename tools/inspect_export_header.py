with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx1 = c.find('$filename = \'pcconnect-\'')
idx2 = c.find('exit;', idx1)
print(c[idx1:idx1+400].encode('ascii', errors='backslashreplace').decode('ascii'))

