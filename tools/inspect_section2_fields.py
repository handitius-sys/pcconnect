with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('<h2>2. Rincian Masalah & Foto Bukti</h2>')
print(text[idx:idx+2500])
