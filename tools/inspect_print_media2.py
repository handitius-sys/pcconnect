with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx1 = c.find('@media print{')
idx2 = c.find('</style></head><body>', idx1)
print(c[idx1:idx2].encode('ascii', errors='backslashreplace').decode('ascii'))

