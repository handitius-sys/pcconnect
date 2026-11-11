with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect around line 2470
idx = text.find('render_invoice_image_viewer_modal();')
print(text[idx-200:idx+200])
