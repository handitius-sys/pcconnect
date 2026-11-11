with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('<h2>2. Rincian Masalah & Foto Bukti</h2>')
idx_next = text.find('<h2>3. Penanganan Oleh</h2>')
print(text[idx:idx_next])
