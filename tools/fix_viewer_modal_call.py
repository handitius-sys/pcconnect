with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Replace render_invoice_image_viewer_modal(); with render_invoice_camera_modal_and_script();
old_call = 'render_invoice_image_viewer_modal();'
new_call = 'render_invoice_camera_modal_and_script();'

if old_call in text:
    text = text.replace(old_call, new_call)
    with open('app/modules/corrective.php', 'w', encoding='utf-8') as f:
        f.write(text)
    print("Replaced successfully!")
else:
    print("Pattern not found!")
