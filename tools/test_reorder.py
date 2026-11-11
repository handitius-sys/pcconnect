with open('app/modules/asset_loan.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

def show_block(name, block):
    print(f"=== {name} ({len(block)} lines) ===")
    print("FIRST 2:")
    for l in block[:2]:
        print("  ", repr(l).encode('ascii', errors='backslashreplace').decode('ascii'))
    print("LAST 2:")
    for l in block[-2:]:
        print("  ", repr(l).encode('ascii', errors='backslashreplace').decode('ascii'))

show_block("part1", lines[694:729])
show_block("part2", lines[729:749])
show_block("part3", lines[749:816])
