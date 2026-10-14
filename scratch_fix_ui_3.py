import os

file_path = "app/modules/pc.php"
with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

import re

# Find the start of the block
start_str = "// Corrective Tickets & Repairs Panel"
end_str = "if (!empty($pc['latitude']) && !empty($pc['longitude'])) {"

start_idx = content.find(start_str)
end_idx = content.find(end_str, start_idx)

if start_idx != -1 and end_idx != -1:
    # also remove leading whitespaces before start_str if necessary
    actual_start = content.rfind("\n", 0, start_idx)
    if actual_start == -1: actual_start = 0
    
    # We want to keep end_str, so we just remove from actual_start to end_idx
    new_content = content[:actual_start] + "\n    " + content[end_idx:]
    with open(file_path, "w", encoding="utf-8") as f:
        f.write(new_content)
    print("Removed Corrective Tickets Panel.")
else:
    print("Could not find the block boundaries.")

