with open('public/index.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx = c.find("'report_print'")
print("Found at:", idx)
print(c[idx-50:idx+350])

# Also check dispatch
idx2 = c.find("$route === 'report_print'")
if idx2 != -1:
    print("Found dispatch at:", idx2)
    print(c[idx2-50:idx2+250])
else:
    # check handle_route_
    print("Check all route dispatches")

