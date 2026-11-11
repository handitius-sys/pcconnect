with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    c = f.read()

idx1 = c.find('function render_report_print_settings_modal')
idx2 = c.find('function render_header', idx1)
print(c[idx1:idx2])

