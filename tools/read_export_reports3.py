with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find("output_tsv(['Tanggal Report'")
print(text[idx:idx+500])
