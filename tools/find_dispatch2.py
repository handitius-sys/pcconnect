with open('public/index.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx = c.find("case 'report_print'")
if idx == -1: idx = c.find("handle_route_")
print("Handler check:")
print(c[idx:idx+800])

