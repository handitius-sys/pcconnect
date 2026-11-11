# -*- coding: utf-8 -*-
with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect the exact block around addBtn
pos = text.find("    $addBtn = has_regulation('asset_items', 'create', $user)")
end_pos = text.find("    echo '<div style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:20px;\">';", pos)

print("Block found from", pos, "to", end_pos)
chunk = text[pos:end_pos]
print("Chunk length:", len(chunk))

