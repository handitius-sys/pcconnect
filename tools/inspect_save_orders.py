with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx1 = text.find('function get_sidebar_menu_orders')
idx2 = text.find('function render_header')
print(text[idx1:idx2].encode('ascii', errors='backslashreplace').decode('ascii'))

