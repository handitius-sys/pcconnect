with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('function render_invoice_camera_modal_and_script')
print(text[idx:idx+2500])
