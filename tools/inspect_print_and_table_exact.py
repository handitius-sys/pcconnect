with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect print block
idx = text.find("if (!empty($_GET['print'])) {")
idx_end = text.find("render_report_print_standalone_page", idx)
idx_end = text.find("}", idx_end) + 1
print("Print block:")
print(text[idx:idx_end])

# Let's inspect web table thead & tbody
idx_tbl = text.find('<table class="report-table-plain report-target-table sortable-table"')
idx_tbl_end = text.find('</table>', idx_tbl) + 8
print("\nWeb table:")
print(text[idx_tbl:idx_tbl_end])
