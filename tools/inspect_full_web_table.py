with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx_web = text.find('<table class="report-table-plain report-target-table sortable-table"')
idx_end_tbody = text.find('</tbody>', idx_web)
print(text[idx_web:idx_end_tbody+8])
