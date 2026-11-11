with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('<table class="report-table-plain report-target-table sortable-table"')
print(text[idx:idx+1500])
