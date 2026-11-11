with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('title="Lihat Detail Tiket (View Only)"')
print(text[idx+1000:idx+2500])
