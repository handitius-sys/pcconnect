with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update line 262-264 in asset_items_table:
old_code_1 = """        $displayCategory = !empty($row['type_name']) && $row['type_name'] !== '-' 
            ? $row['type_name'] 
            : (!empty($row['asset_type']) ? $row['asset_type'] : (!empty($row['asset_category']) ? $row['asset_category'] : '-'));"""

new_code_1 = """        $displayCategory = !empty($row['type_name']) && $row['type_name'] !== '-' 
            ? $row['type_name'] 
            : (!empty($row['asset_type']) ? $row['asset_type'] : '-');"""

# 2. Update line 7753-7755 in handle_route_report_asset_items:
old_code_2 = """                $categoryName = !empty($item['display_type_name']) && $item['display_type_name'] !== '-' 
                    ? $item['display_type_name'] 
                    : (!empty($item['asset_type']) ? $item['asset_type'] : (!empty($item['asset_category']) ? $item['asset_category'] : '-'));"""

new_code_2 = """                $categoryName = !empty($item['display_type_name']) && $item['display_type_name'] !== '-' 
                    ? $item['display_type_name'] 
                    : (!empty($item['asset_type']) ? $item['asset_type'] : '-');"""

print("Target 1 found:", old_code_1 in content)
print("Target 2 found:", old_code_2 in content)

if old_code_1 in content and old_code_2 in content:
    content = content.replace(old_code_1, new_code_1, 1)
    content = content.replace(old_code_2, new_code_2, 1)
    with open('app/modules/asset.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print("SUCCESS: asset.php updated successfully!")
else:
    print("FAILED: some targets not found in asset.php")
