import os

f = 'app/modules/pc.php'
with open(f, 'r', encoding='utf-8') as file:
    c = file.read()

# Remove Hapus PC from view
search_delete = """    if (has_regulation('pcs', 'delete')) {
        echo '<form method="post" action="' . route_url('pcs') . '" style="display:inline;" onsubmit="return confirm(\\'Hapus PC ' . e($pcId) . '? Semua relasi dan riwayat PC ini akan dihapus.\\');">'
            . csrf_field()
            . '<input type="hidden" name="action" value="delete_pc">'
            . '<input type="hidden" name="pc_id" value="' . e($pcId) . '">'
            . '<button type="submit" class="btn danger">Hapus PC</button>'
            . '</form>';
    }"""
replace_delete = """    if (has_regulation('pcs', 'delete')) {
        echo '';
    }"""
if search_delete in c:
    c = c.replace(search_delete, replace_delete)
    print("Removed Hapus PC from view")

# Remove Mobile QR URL
search_mobile = """    if ($hasSync && $assetCode !== '') {
        echo '<a class="btn" href="' . mobile_asset_url($assetCode) . '">Mobile QR URL</a>';
    }"""
replace_mobile = """    if ($hasSync && $assetCode !== '') {
        echo '';
    }"""
if search_mobile in c:
    c = c.replace(search_mobile, replace_mobile)
    print("Removed Mobile QR URL from view")

with open(f, 'w', encoding='utf-8') as file:
    file.write(c)

