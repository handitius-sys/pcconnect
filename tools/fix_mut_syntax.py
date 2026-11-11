# -*- coding: utf-8 -*-
with open('app/modules/asset_mutations.php', 'r', encoding='utf-8') as f:
    text = f.read()

text = text.replace(". '</div>;' + \"\\n\\n\"", ". '</div>';\n\n")
with open('app/modules/asset_mutations.php', 'w', encoding='utf-8') as f:
    f.write(text)

print("Fixed syntax in asset_mutations.php")

