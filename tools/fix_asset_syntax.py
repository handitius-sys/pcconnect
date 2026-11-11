# -*- coding: utf-8 -*-
with open('app/modules/asset.php', 'r', encoding='utf-8') as f:
    text = f.read()

text = text.replace(". '</div>\\n\\n'\\n    // Form Filter", ". '</div>';\\n\\n    // Form Filter")
text = text.replace(". '</div>\n\n'\n    // Form Filter", ". '</div>';\n\n    // Form Filter")

with open('app/modules/asset.php', 'w', encoding='utf-8') as f:
    f.write(text)

print("Fixed syntax in asset.php")

