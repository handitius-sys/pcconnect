# -*- coding: utf-8 -*-
with open('app/modules/asset_mutations.php', 'r', encoding='utf-8') as f:
    text = f.read()

pos_header = text.find("    echo '<section class=\"panel\">';")
end_header = text.find("    // Form Filter: Wajib Pilih Komoditas & Kategori", pos_header)

print("Found header from", pos_header, "to", end_header)
print(repr(text[pos_header:end_header]))

