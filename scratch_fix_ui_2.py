import os

file_path = "app/modules/pc.php"
with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

search = """        . '<a class="btn" href="' . route_url('upload_analysis', ['pc_id' => $effectivePcId]) . '">📤 Upload JSON Analisa</a>'
        . '</div>'
        . '</div>'"""

replace = """        . '<a class="btn" href="' . route_url('upload_analysis', ['pc_id' => $effectivePcId]) . '">📤 Upload JSON Analisa</a>'
        . '</div>'
        . '</div>'
        . '<div style="margin-top:10px;padding:8px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;font-size:12px;color:#92400e;">'
        . '<strong>Catatan Manual:</strong> Jika mengunduh file Script PS1, jalankan lewat CMD Administrator dengan perintah:<br>'
        . '<code style="background:#fef3c7;padding:2px 4px;border-radius:4px;font-family:Consolas,monospace;">powershell -ExecutionPolicy Bypass -File ' . e($effectivePcId) . '.ps1</code>'
        . '</div>'"""

content = content.replace(search, replace)

with open(file_path, "w", encoding="utf-8") as f:
    f.write(content)

print("Added manual PS1 note.")

