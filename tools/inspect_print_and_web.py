with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('if (!empty($_GET[\'print\'])) {')
idx_web = text.find('<table class="report-table-plain report-target-table sortable-table"', idx)
idx_end_tbody = text.find('</tbody>', idx_web)

print("Print logic:")
print(text[idx:idx+1500])
print("\nWeb table rows:")
print(text[idx_web:idx_web+2000])
