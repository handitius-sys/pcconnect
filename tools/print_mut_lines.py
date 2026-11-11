# -*- coding: utf-8 -*-
with open('app/modules/asset_mutations.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i, l in enumerate(lines):
    if "render_header('Mutasi Aset'" in l:
        print("Starts at line", i+1)
        for j in range(i, i+30):
            clean = lines[j].encode('ascii', 'backslashreplace').decode('ascii')
            print(f"{j+1}: {clean}", end="")
        break

