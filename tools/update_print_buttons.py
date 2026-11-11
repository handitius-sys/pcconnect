# Update all report files:
# 1. Replace button "Print Setting & Page Setup" with blue button "PRINT" onclick="window.print()"
# 2. Update modal calls if any

import re

# 1. app/modules/asset.php
with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    c_asset = f.read()

# Replace Print Setting & Page Setup in asset.php
# In asset_movements and report_asset_items
c_asset = c_asset.replace(
    '<button type="button" class="btn" onclick="openPrintSettings()">Print Setting & Page Setup</button>',
    '<button type="button" class="btn primary" onclick="window.print()" style="padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;">PRINT</button>'
)
with open('app/modules/asset.php', 'w', encoding='utf-8') as f:
    f.write(c_asset)

# 2. app/modules/asset_loan.php
with open('app/modules/asset_loan.php', 'r', encoding='utf-8') as f:
    c_loan = f.read()

c_loan = c_loan.replace(
    '<button type="button" class="btn" onclick="openPrintSettings()">Print Setting & Page Setup</button>',
    '<button type="button" class="btn primary" onclick="window.print()" style="padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;">PRINT</button>'
)
with open('app/modules/asset_loan.php', 'w', encoding='utf-8') as f:
    f.write(c_loan)

# 3. app/modules/maintenance.php
with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    c_maint = f.read()

c_maint = c_maint.replace(
    '<button type="button" class="btn" onclick="openPrintSettings()">Print Setting & Page Setup</button>',
    '<button type="button" class="btn primary" onclick="window.print()" style="padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;">PRINT</button>'
)
with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
    f.write(c_maint)

# 4. app/modules/corrective.php
with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    c_corr = f.read()

c_corr = c_corr.replace(
    '<button type="button" class="btn" onclick="openPrintSettings()">Print Setting & Page Setup</button>',
    '<button type="button" class="btn primary" onclick="window.print()" style="padding:8px 16px;font-weight:700;display:inline-flex;align-items:center;gap:6px;">PRINT</button>'
)
with open('app/modules/corrective.php', 'w', encoding='utf-8') as f:
    f.write(c_corr)

print("Updated print buttons to blue PRINT in all report files successfully")

