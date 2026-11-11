with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx = c.find("function report_print")
print("Found report_print at:", idx)
print(c[idx:idx+2500].encode('ascii', errors='backslashreplace').decode('ascii'))

