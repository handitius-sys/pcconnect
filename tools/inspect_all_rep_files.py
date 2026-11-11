# Let's inspect each of the 5 report handlers around where data is fetched and render_header is called

# 1. app/modules/asset.php (report_asset_items & asset_movements)
with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    c_asset = f.read()

# 2. app/modules/asset_loan.php (report_asset_loans)
with open('app/modules/asset_loan.php', 'r', encoding='utf-8') as f:
    c_loan = f.read()

# 3. app/modules/maintenance.php (reports)
with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    c_maint = f.read()

# 4. app/modules/corrective.php (corrective_repairs)
with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    c_corr = f.read()

print("All 4 files read successfully")

